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
     * @return array{success:bool,return_id?:int,refund_amount?:float,error?:string}
     */
    public function createReturn($orderId, $userId, array $items, $reason = '')
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
        if ($daysSince > $windowDays) {
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

        $this->accounting->postJournal(
            [
                ['code' => '4000', 'debit'  => $merchandise], // Sales Revenue (reversed)
                ['code' => '2100', 'debit'  => $taxPortion],  // Sales Tax Payable (reversed)
                ['code' => '1000', 'credit' => $refundAmount], // Cash out
            ],
            'Refund — return #' . $ret['id'] . ' (order #' . $ret['order_id'] . ')',
            'return',
            (int) $ret['id']
        );

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
