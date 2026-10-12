<?php
/**
 * MarketplacePayoutService — commission split + seller payouts.
 *
 * For SELLER-owned (ownership='marketplace') order lines, the platform acts as
 * an agent: it recognises only COMMISSION as revenue and owes the seller the
 * rest (Merchant Payable). Own (VentDepot) lines stay full retail revenue.
 *
 *   Sale split (marketplace lines):  Cr Merchant Payable (net) + Cr Commission Revenue (fee)
 *   Payout run:                      Dr Merchant Payable / Cr Cash
 *
 * commission_rate defaults to the platform rate; can be per-merchant later.
 */
require_once __DIR__ . '/AccountingService.php';

class MarketplacePayoutService
{
    private $pdo;
    private $accounting;
    const DEFAULT_COMMISSION = 0.10;   // 10%

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->accounting = new AccountingService($pdo);
    }

    /**
     * Split an order's revenue between own retail and marketplace sellers.
     * @return array own_subtotal, marketplace_subtotal, commission, seller_net, per_seller[]
     */
    public function orderSplit($orderId)
    {
        $stmt = $this->pdo->prepare("
            SELECT p.merchant_id, p.ownership, SUM(oi.total) AS line_total
            FROM order_items oi JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
            GROUP BY p.merchant_id, p.ownership
        ");
        $stmt->execute([$orderId]);

        $ownSub = 0; $mktSub = 0; $commission = 0; $sellerNet = 0; $perSeller = [];
        foreach ($stmt as $r) {
            $amt = (float) $r['line_total'];
            if ($r['ownership'] === 'marketplace') {
                $rate = self::DEFAULT_COMMISSION;
                $fee = round($amt * $rate, 2);
                $net = round($amt - $fee, 2);
                $mktSub += $amt; $commission += $fee; $sellerNet += $net;
                $perSeller[(int) $r['merchant_id']] = [
                    'gross' => $amt, 'rate' => $rate, 'commission' => $fee, 'net' => $net,
                ];
            } else {
                $ownSub += $amt;
            }
        }
        return [
            'own_subtotal' => round($ownSub, 2),
            'marketplace_subtotal' => round($mktSub, 2),
            'commission' => round($commission, 2),
            'seller_net' => round($sellerNet, 2),
            'per_seller' => $perSeller,
        ];
    }

    /** Record per-seller commission rows for a paid order (idempotent). */
    public function recordCommissions($orderId)
    {
        $chk = $this->pdo->prepare("SELECT 1 FROM merchant_commissions WHERE order_id=? LIMIT 1");
        $chk->execute([$orderId]);
        if ($chk->fetchColumn()) return;

        $split = $this->orderSplit($orderId);
        $ins = $this->pdo->prepare("
            INSERT INTO merchant_commissions (order_id, merchant_id, gross_amount, commission_rate, commission_amount, net_amount, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending_payout', NOW())
        ");
        foreach ($split['per_seller'] as $mid => $s) {
            $ins->execute([$orderId, $mid, $s['gross'], $s['rate'], $s['commission'], $s['net']]);
        }
    }

    /** Seller's unpaid balance owed. */
    public function pendingPayable($merchantId)
    {
        $s = $this->pdo->prepare("SELECT COALESCE(SUM(net_amount),0) FROM merchant_commissions WHERE merchant_id=? AND status='pending_payout'");
        $s->execute([$merchantId]);
        return (float) $s->fetchColumn();
    }

    /** Pay a seller their accumulated net. Dr Merchant Payable / Cr Cash. */
    public function runPayout($merchantId, $userId = null, $method = 'bank_transfer')
    {
        $rows = $this->pdo->prepare("
            SELECT id, gross_amount, commission_amount, net_amount, order_id
            FROM merchant_commissions WHERE merchant_id=? AND status='pending_payout'
        ");
        $rows->execute([$merchantId]);
        $lines = $rows->fetchAll(PDO::FETCH_ASSOC);
        if (!$lines) return ['success' => false, 'error' => 'Nothing pending for this seller.'];

        $gross = array_sum(array_column($lines, 'gross_amount'));
        $comm = array_sum(array_column($lines, 'commission_amount'));
        $net = array_sum(array_column($lines, 'net_amount'));
        if (round($net, 2) <= 0) {
            // Refund lines (negative net) exceed sales: nothing to pay; the
            // balance carries forward against future sales.
            return ['success' => false, 'error' => 'Refunds exceed sales — seller owes $' . number_format(-$net, 2) . '; nothing to pay out.'];
        }
        $orders = count(array_unique(array_column($lines, 'order_id')));
        $ref = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        try {
            $this->pdo->beginTransaction();

            $this->pdo->prepare("
                INSERT INTO merchant_payouts (merchant_id, gross_amount, commission_amount, net_amount, order_count, status, method, reference, paid_at, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, 'paid', ?, ?, NOW(), ?, NOW())
            ")->execute([$merchantId, $gross, $comm, $net, $orders, $method, $ref, $userId]);
            $payoutId = (int) $this->pdo->lastInsertId();

            $ids = implode(',', array_map('intval', array_column($lines, 'id')));
            $this->pdo->query("UPDATE merchant_commissions SET status='paid_out', payout_reference='$ref', payout_date=NOW() WHERE id IN ($ids)");

            if ($net > 0) {
                $this->accounting->postJournal(
                    [['code' => '2200', 'debit' => $net], ['code' => '1000', 'credit' => $net]],
                    'Seller payout ' . $ref . ' (merchant #' . $merchantId . ')', 'merchant_payout', $payoutId);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Tell the seller their money is on the way (outside the transaction).
        require_once __DIR__ . '/SellerNotificationService.php';
        (new SellerNotificationService($this->pdo))->payoutSent($merchantId, $payoutId, $net, $ref);

        return ['success' => true, 'payout_id' => $payoutId, 'net' => round($net, 2), 'commission' => round($comm, 2), 'reference' => $ref];
    }

    /** All sellers with a pending balance. */
    public function pendingSellers()
    {
        return $this->pdo->query("
            SELECT mc.merchant_id, u.email, COUNT(*) AS line_count, SUM(mc.gross_amount) gross,
                   SUM(mc.commission_amount) commission, SUM(mc.net_amount) net
            FROM merchant_commissions mc JOIN users u ON mc.merchant_id=u.id
            WHERE mc.status='pending_payout'
            GROUP BY mc.merchant_id ORDER BY net DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
}
