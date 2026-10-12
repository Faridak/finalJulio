<?php
/**
 * ReturnService
 *
 * Customer-initiated returns (RMA). Completing a return connects three systems:
 *   - Payment:    records a refund against the order's payment transaction.
 *   - Inventory:  restocks returned units (movement 'in' / 'return').
 *   - Accounting: reverses revenue/tax and COGS/inventory in the general ledger.
 *
 * Each completion side effect is idempotent and flag-guarded on the
 * return_requests row, so completeReturn() is safe to re-run.
 */
require_once __DIR__ . '/AccountingService.php';
require_once __DIR__ . '/InventoryService.php';

class ReturnService
{
    private $pdo;
    private $accounting;
    private $inventory;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->accounting = new AccountingService($pdo);
        $this->inventory  = new InventoryService($pdo);
    }

    /**
     * Create a return request for selected order items.
     *
     * @param int   $orderId
     * @param int   $userId  the requesting customer (must own the order)
     * @param array $items   [ ['order_item_id'=>int, 'quantity'=>int], ... ]
     * @param string $reason
     * @param bool   $enforceWindow  false for admin-resolved disputes (may be raised after the window)
     * @return array{success:bool,return_id?:int,refund_amount?:float,error?:string}
     */
    public function createReturn($orderId, $userId, array $items, $reason = '', $enforceWindow = true)
    {
        // Verify order ownership and that it is in a returnable state.
        $orderStmt = $this->pdo->prepare("
            SELECT id, user_id, subtotal, tax, total, status, created_at
            FROM orders WHERE id = ? LIMIT 1
        ");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

        if (!$order || (int) $order['user_id'] !== (int) $userId) {
            return ['success' => false, 'error' => 'Order not found.'];
        }
        if (in_array($order['status'], ['pending', 'cancelled'], true)) {
            return ['success' => false, 'error' => 'This order is not eligible for return.'];
        }

        // Enforce the return window: store default 30 days, or the longest
        // per-product window among the ordered items (product return terms).
        $win = $this->pdo->prepare("
            SELECT COALESCE(MAX(NULLIF(p.return_window_days,0)), 30) AS window_days
            FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?
        ");
        $win->execute([$orderId]);
        $windowDays = (int) ($win->fetchColumn() ?: 30);
        $daysSince = (int) floor((time() - strtotime($order['created_at'])) / 86400);
        if ($enforceWindow && $daysSince > $windowDays) {
            return ['success' => false, 'error' => "The {$windowDays}-day return window has passed (ordered {$daysSince} days ago)."];
        }

        // Build validated line list from the order's own items.
        $lines = [];
        $refundMerchandise = 0.0;
        foreach ($items as $req) {
            $orderItemId = (int) ($req['order_item_id'] ?? 0);
            $qty = (int) ($req['quantity'] ?? 0);
            if ($orderItemId <= 0 || $qty <= 0) {
                continue;
            }

            $oiStmt = $this->pdo->prepare("
                SELECT id, product_id, quantity, price_at_purchase
                FROM order_items WHERE id = ? AND order_id = ? LIMIT 1
            ");
            $oiStmt->execute([$orderItemId, $orderId]);
            $oi = $oiStmt->fetch(PDO::FETCH_ASSOC);
            if (!$oi) {
                continue;
            }

            $qty = min($qty, (int) $oi['quantity'] - $this->alreadyReturnedQty($orderItemId));
            if ($qty <= 0) {
                continue;
            }

            $unitPrice = (float) $oi['price_at_purchase'];
            $lineRefund = round($unitPrice * $qty, 2);
            $refundMerchandise += $lineRefund;

            $lines[] = [
                'order_item_id' => $orderItemId,
                'product_id'    => (int) $oi['product_id'],
                'quantity'      => $qty,
                'unit_price'    => $unitPrice,
                'line_refund'   => $lineRefund,
            ];
        }

        if (empty($lines)) {
            return ['success' => false, 'error' => 'No returnable items selected.'];
        }

        // Refund the proportional tax on the returned merchandise.
        $subtotal = (float) $order['subtotal'];
        $tax      = (float) $order['tax'];
        $taxRefund = $subtotal > 0 ? round($tax * ($refundMerchandise / $subtotal), 2) : 0.0;
        $refundAmount = round($refundMerchandise + $taxRefund, 2);

        try {
            $this->pdo->beginTransaction();

            $ins = $this->pdo->prepare("
                INSERT INTO return_requests (order_id, user_id, status, reason, refund_amount, created_at)
                VALUES (?, ?, 'requested', ?, ?, NOW())
            ");
            $ins->execute([$orderId, $userId, $reason, $refundAmount]);
            $returnId = (int) $this->pdo->lastInsertId();

            $itemIns = $this->pdo->prepare("
                INSERT INTO return_request_items
                    (return_request_id, order_item_id, product_id, quantity, unit_price, line_refund, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            foreach ($lines as $l) {
                $itemIns->execute([
                    $returnId, $l['order_item_id'], $l['product_id'],
                    $l['quantity'], $l['unit_price'], $l['line_refund'],
                ]);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("createReturn failed for order $orderId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Could not create the return request.'];
        }

        return [
            'success'       => true,
            'return_id'     => $returnId,
            'refund_amount' => $refundAmount,
        ];
    }

    /**
     * Admin decision: approve or reject a requested return.
     */
    public function setStatus($returnId, $status)
    {
        $allowed = ['approved', 'rejected', 'received', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            return ['success' => false, 'error' => 'Invalid status.'];
        }
        $stmt = $this->pdo->prepare("UPDATE return_requests SET status = ? WHERE id = ?");
        $stmt->execute([$status, $returnId]);
        return ['success' => true];
    }

    /**
     * Complete an approved/received return: refund + restock + ledger reversal.
     * Idempotent per side effect. Marks the return 'refunded'.
     */
    public function completeReturn($returnId)
    {
        $ret = $this->loadReturn($returnId);
        if (!$ret) {
            return ['success' => false, 'error' => 'Return not found.'];
        }
        if (in_array($ret['status'], ['rejected', 'cancelled'], true)) {
            return ['success' => false, 'error' => 'Return is not in a completable state.'];
        }

        $orderId = (int) $ret['order_id'];
        $errors = [];

        try {
            $this->pdo->beginTransaction();

            // 1. Refund record against the order's payment transaction.
            if (!$ret['refund_posted']) {
                $this->recordRefund($orderId, (float) $ret['refund_amount'], $ret['reason']);
                $this->setFlag($returnId, 'refund_posted');
            }

            // 2. Restock inventory + accumulate cost basis for COGS reversal.
            $restockCost = 0.0;
            if ($ret['restock'] && !$ret['inventory_restocked']) {
                foreach ($this->returnItems($returnId) as $item) {
                    $restockCost += $this->inventory->restockReturn(
                        $orderId,
                        (int) $item['product_id'],
                        (int) $item['quantity'],
                        $returnId,
                        (int) $ret['user_id']
                    );
                }
                $this->setFlag($returnId, 'inventory_restocked');
            } else {
                $restockCost = $this->restockedCost($returnId);
            }

            // 3. Reverse the accounting ledger.
            if (!$ret['accounting_reversed'] && !$this->accounting->isPosted('return', $returnId)) {
                $this->postReversal($ret, $restockCost);
                $this->setFlag($returnId, 'accounting_reversed');
            }

            $upd = $this->pdo->prepare("
                UPDATE return_requests SET status = 'refunded', processed_at = NOW() WHERE id = ?
            ");
            $upd->execute([$returnId]);

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("completeReturn failed for return $returnId: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to complete the return.'];
        }

        return ['success' => true, 'refund_amount' => (float) $ret['refund_amount']];
    }

    /**
     * Reversal journal (mirror of the sale posting):
     *   Dr Sales Revenue     (refunded merchandise)
     *   Dr Sales Tax Payable (refunded tax)
     *   Cr Cash              (refund amount)
     * plus COGS reversal for restocked cost:
     *   Dr Inventory
     *   Cr Cost of Goods Sold
     */
    private function postReversal(array $ret, $restockCost)
    {
        $refundAmount = round((float) $ret['refund_amount'], 2);

        // Split refund back into merchandise vs tax using the order ratio.
        $order = $this->pdo->prepare("SELECT subtotal, tax FROM orders WHERE id = ? LIMIT 1");
        $order->execute([$ret['order_id']]);
        $o = $order->fetch(PDO::FETCH_ASSOC) ?: ['subtotal' => 0, 'tax' => 0];

        $merchTotal = (float) $o['subtotal'];
        $taxTotal   = (float) $o['tax'];
        $grossTotal = $merchTotal + $taxTotal;

        if ($grossTotal > 0) {
            $merchandise = round($refundAmount * ($merchTotal / $grossTotal), 2);
            $taxPortion  = round($refundAmount - $merchandise, 2);
        } else {
            $merchandise = $refundAmount;
            $taxPortion  = 0.0;
        }

        // Reverse each returned line the way the sale was booked: own products
        // came out of Sales Revenue (4000); marketplace products were Merchant
        // Payable (2200, seller net) + Commission Revenue (4100, platform fee).
        // Partial refunds scale every line by the same factor.
        $items = $this->pdo->prepare("
            SELECT rri.line_refund, p.ownership, p.merchant_id
            FROM return_request_items rri JOIN products p ON p.id = rri.product_id
            WHERE rri.return_request_id = ?
        ");
        $items->execute([$ret['id']]);
        $items = $items->fetchAll(PDO::FETCH_ASSOC);
        $linesTotal = array_sum(array_map(fn($i) => (float) $i['line_refund'], $items));
        $factor = $linesTotal > 0 ? $merchandise / $linesTotal : 0.0;

        $perSeller = [];   // merchant_id => [gross, rate, fee, net]
        foreach ($items as $i) {
            if ($i['ownership'] !== 'marketplace') continue;
            $mid = (int) $i['merchant_id'];
            $amt = round((float) $i['line_refund'] * $factor, 2);
            $rate = $this->commissionRate((int) $ret['order_id'], $mid);
            $perSeller[$mid] = $perSeller[$mid] ?? ['gross' => 0.0, 'rate' => $rate, 'fee' => 0.0, 'net' => 0.0];
            $fee = round($amt * $rate, 2);
            $perSeller[$mid]['gross'] += $amt;
            $perSeller[$mid]['fee']   += $fee;
            $perSeller[$mid]['net']   += round($amt - $fee, 2);
        }
        $sellerNet = round(array_sum(array_column($perSeller, 'net')), 2);
        $commission = round(array_sum(array_column($perSeller, 'fee')), 2);
        $ownRev = round($merchandise - $sellerNet - $commission, 2);   // rounding remainder stays on own revenue

        $this->accounting->postJournal(
            [
                ['code' => '4000', 'debit'  => $ownRev],       // Sales Revenue (own lines, reversed)
                ['code' => '2200', 'debit'  => $sellerNet],    // Merchant Payable (seller's share, reversed)
                ['code' => '4100', 'debit'  => $commission],   // Commission Revenue (platform fee, reversed)
                ['code' => '2100', 'debit'  => $taxPortion],   // Sales Tax Payable (reversed)
                ['code' => '1000', 'credit' => $refundAmount], // Cash out
            ],
            'Refund — return #' . $ret['id'] . ' (order #' . $ret['order_id'] . ')',
            'return',
            (int) $ret['id']
        );

        // Take the refund out of each seller's balance: a negative commission
        // line, netted into their next payout. If they were already paid for
        // this sale, it stays pending as an amount the seller owes.
        foreach ($perSeller as $mid => $s) {
            $ref = 'REFUND-R' . (int) $ret['id'];
            $dup = $this->pdo->prepare("SELECT 1 FROM merchant_commissions WHERE merchant_id = ? AND payout_reference = ? LIMIT 1");
            $dup->execute([$mid, $ref]);
            if ($dup->fetchColumn()) continue;   // idempotent re-run
            $this->pdo->prepare("
                INSERT INTO merchant_commissions
                    (order_id, merchant_id, gross_amount, commission_rate, commission_amount, net_amount, status, payout_reference, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending_payout', ?, NOW())
            ")->execute([$ret['order_id'], $mid, -round($s['gross'], 2), $s['rate'], -round($s['fee'], 2), -round($s['net'], 2), $ref]);
        }

        if ($restockCost > 0) {
            $this->accounting->postJournal(
                [
                    ['code' => '1200', 'debit'  => $restockCost], // Inventory back
                    ['code' => '5000', 'credit' => $restockCost], // COGS reversed
                ],
                'Restock COGS reversal — return #' . $ret['id'],
                'return_cogs',
                (int) $ret['id']
            );
        }
    }

    private function recordRefund($orderId, $amount, $reason)
    {
        // Link to the order's payment transaction when available.
        $txStmt = $this->pdo->prepare("SELECT payment_transaction_id FROM orders WHERE id = ? LIMIT 1");
        $txStmt->execute([$orderId]);
        $transactionId = $txStmt->fetchColumn() ?: null;

        $stmt = $this->pdo->prepare("
            INSERT INTO payment_refunds (transaction_id, amount, gateway_refund_id, reason, status, created_at)
            VALUES (?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([$transactionId, $amount, 'rf_' . bin2hex(random_bytes(6)), $reason]);
    }

    private function alreadyReturnedQty($orderItemId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(rri.quantity), 0)
            FROM return_request_items rri
            JOIN return_requests rr ON rri.return_request_id = rr.id
            WHERE rri.order_item_id = ? AND rr.status <> 'rejected' AND rr.status <> 'cancelled'
        ");
        $stmt->execute([$orderItemId]);
        return (int) $stmt->fetchColumn();
    }

    /** Commission rate charged on this seller's portion of the order (as recorded at sale). */
    private function commissionRate($orderId, $merchantId)
    {
        $s = $this->pdo->prepare("
            SELECT commission_rate FROM merchant_commissions
            WHERE order_id = ? AND merchant_id = ? AND gross_amount > 0 ORDER BY id LIMIT 1
        ");
        $s->execute([$orderId, $merchantId]);
        $rate = $s->fetchColumn();
        if ($rate === false) {
            require_once __DIR__ . '/MarketplacePayoutService.php';
            return MarketplacePayoutService::DEFAULT_COMMISSION;
        }
        return (float) $rate;
    }

    private function returnItems($returnId)
    {
        $stmt = $this->pdo->prepare("SELECT product_id, quantity FROM return_request_items WHERE return_request_id = ?");
        $stmt->execute([$returnId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function restockedCost($returnId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(total_cost), 0)
            FROM inventory_movements
            WHERE reference_type = 'return' AND reference_id = ?
        ");
        $stmt->execute([$returnId]);
        return (float) $stmt->fetchColumn();
    }

    private function loadReturn($returnId)
    {
        $stmt = $this->pdo->prepare("
            SELECT id, order_id, user_id, status, reason, refund_amount, restock,
                   refund_posted, inventory_restocked, accounting_reversed
            FROM return_requests WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$returnId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function setFlag($returnId, $column)
    {
        $allowed = ['refund_posted', 'inventory_restocked', 'accounting_reversed'];
        if (!in_array($column, $allowed, true)) {
            return;
        }
        $stmt = $this->pdo->prepare("UPDATE return_requests SET `$column` = 1 WHERE id = ?");
        $stmt->execute([$returnId]);
    }
}
