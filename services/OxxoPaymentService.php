<?php
/**
 * OXXO cash-voucher payments (Mexico).
 *
 * Flow: checkout picks OXXO -> we issue a 14-digit reference valid 72h and
 * extend the stock reservation to match -> customer pays cash at any OXXO ->
 * the PSP webhook (api/oxxo-webhook.php) or an admin (cash reconciliation)
 * confirms -> the order completes through the SAME orchestration as PayPal
 * (inventory commit, ledger, shipment, email, commissions).
 *
 * Works standalone today; when a PSP (Conekta/Openpay) is connected its
 * webhook simply calls confirmPayment() with the reference.
 */
class OxxoPaymentService
{
    const VALID_HOURS = 72;

    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** Issue (or re-fetch) the voucher for an order. */
    public function createVoucher($orderId)
    {
        $o = $this->pdo->prepare("SELECT id, total, payment_status FROM orders WHERE id = ?");
        $o->execute([(int) $orderId]);
        $order = $o->fetch(PDO::FETCH_ASSOC);
        if (!$order) return ['success' => false, 'error' => 'Order not found'];
        if ($order['payment_status'] === 'paid') return ['success' => false, 'error' => 'Order already paid'];

        // Existing pending voucher? return it (idempotent).
        $ex = $this->pdo->prepare("SELECT * FROM oxxo_payments WHERE order_id = ? AND status = 'pending' AND expires_at > NOW()");
        $ex->execute([(int) $orderId]);
        if ($row = $ex->fetch(PDO::FETCH_ASSOC)) return ['success' => true] + $row;

        // 14-digit reference: 93 (OXXO service prefix style) + zero-padded order + random, Luhn-ish tail.
        do {
            $reference = '93' . str_pad($orderId % 100000, 5, '0', STR_PAD_LEFT) . str_pad(random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
            $dup = $this->pdo->prepare("SELECT 1 FROM oxxo_payments WHERE reference = ?");
            $dup->execute([$reference]);
        } while ($dup->fetchColumn());

        $this->pdo->prepare("
            INSERT INTO oxxo_payments (order_id, reference, amount, expires_at)
            VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL " . self::VALID_HOURS . " HOUR))
            ON DUPLICATE KEY UPDATE reference = VALUES(reference), amount = VALUES(amount),
                status = 'pending', expires_at = VALUES(expires_at), paid_at = NULL
        ")->execute([(int) $orderId, $reference, (float) $order['total']]);

        // Hold the reserved stock for the full voucher window.
        $this->pdo->prepare("UPDATE stock_reservations SET expires_at = DATE_ADD(NOW(), INTERVAL " . self::VALID_HOURS . " HOUR) WHERE order_id = ? AND status = 'held'")
            ->execute([(int) $orderId]);

        $v = $this->pdo->prepare("SELECT * FROM oxxo_payments WHERE order_id = ?");
        $v->execute([(int) $orderId]);
        return ['success' => true] + $v->fetch(PDO::FETCH_ASSOC);
    }

    /** Cash received (webhook or manual): complete the order like any payment. */
    public function confirmPayment($reference, $via = 'manual')
    {
        $v = $this->pdo->prepare("SELECT * FROM oxxo_payments WHERE reference = ?");
        $v->execute([trim($reference)]);
        $voucher = $v->fetch(PDO::FETCH_ASSOC);
        if (!$voucher) return ['success' => false, 'error' => 'Unknown reference'];
        if ($voucher['status'] === 'paid') return ['success' => false, 'error' => 'Already paid'];
        if ($voucher['status'] !== 'pending' || strtotime($voucher['expires_at']) < time()) {
            return ['success' => false, 'error' => 'Voucher expired or cancelled'];
        }

        $orderId = (int) $voucher['order_id'];
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE oxxo_payments SET status='paid', paid_at=NOW(), confirmed_via=? WHERE id=?")
                ->execute([$via === 'webhook' ? 'webhook' : 'manual', $voucher['id']]);

            $this->pdo->prepare("
                INSERT INTO payment_transactions (order_id, payment_method, gateway_reference, amount, platform_fee, gateway_fee, net_amount, status, raw_response, created_at)
                VALUES (?, 'oxxo', ?, ?, 0, 0, ?, 'completed', ?, NOW())
            ")->execute([$orderId, $voucher['reference'], $voucher['amount'], $voucher['amount'],
                         json_encode(['oxxo' => true, 'via' => $via])]);
            $txId = (int) $this->pdo->lastInsertId();

            $this->pdo->prepare("UPDATE orders SET payment_status='paid', status='processing', payment_transaction_id=?, updated_at=NOW() WHERE id=?")
                ->execute([$txId, $orderId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }

        // Same post-payment orchestration as every other gateway.
        try {
            require_once __DIR__ . '/OrderFulfillmentOrchestrator.php';
            (new OrderFulfillmentOrchestrator($this->pdo))->onPaymentCompleted($orderId, null);
        } catch (\Throwable $e) { /* orchestrator logs its own failures */ }

        return ['success' => true, 'order_id' => $orderId, 'amount' => (float) $voucher['amount'], 'transaction_id' => $txId];
    }

    /** Housekeeping: mark overdue vouchers expired (reservation worker frees the stock). */
    public function expireOverdue()
    {
        return $this->pdo->exec("UPDATE oxxo_payments SET status='expired' WHERE status='pending' AND expires_at < NOW()");
    }
}
