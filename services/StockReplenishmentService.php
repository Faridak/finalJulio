<?php
/**
 * StockReplenishmentService
 *
 * Refilling stock = receiving goods from a supplier. A receipt:
 *   - logs an 'in' / 'purchase_order' inventory movement (with supplier, cost,
 *     batch number and expiry date);
 *   - creates a costed lot in inventory_batches (for expiry reporting / FEFO);
 *   - upserts product_inventory (quantity_on_hand, weighted average_cost, last_cost);
 *   - increases catalog stock (products.inventory);
 *   - posts Dr Inventory / Cr Accounts Payable to the general ledger and records
 *     an accounts_payable sub-ledger entry for the supplier.
 *
 * Also decrements lots FEFO (first-expiry-first-out) when stock is sold, so the
 * remaining quantity per lot stays accurate.
 */
require_once __DIR__ . '/AccountingService.php';
require_once __DIR__ . '/SlottingService.php';

class StockReplenishmentService
{
    private $pdo;
    private $accounting;
    private $slotting;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->accounting = new AccountingService($pdo);
        $this->slotting = new SlottingService($pdo);
    }

    /**
     * Receive stock from a supplier into a location.
     *
     * @param array $p keys: product_id, supplier_id, location_id, quantity,
     *                  unit_cost, batch_number?, expiry_date?, purchase_order_id?,
     *                  user_id?, post_accounting? (default true)
     * @return array{success:bool, batch_id?:int, movement_id?:int, error?:string}
     */
    public function receiveStock(array $p)
    {
        $productId  = (int) ($p['product_id'] ?? 0);
        $supplierId = !empty($p['supplier_id']) ? (int) $p['supplier_id'] : null;
        $locationId = (int) ($p['location_id'] ?? 0);
        $quantity   = (int) ($p['quantity'] ?? 0);
        $unitCost   = round((float) ($p['unit_cost'] ?? 0), 2);
        $batch      = $p['batch_number'] ?? null;
        $expiry     = !empty($p['expiry_date']) ? $p['expiry_date'] : null;
        $poId       = !empty($p['purchase_order_id']) ? (int) $p['purchase_order_id'] : null;
        $userId     = !empty($p['user_id']) ? (int) $p['user_id'] : null;
        $postAcct   = $p['post_accounting'] ?? true;

        if ($productId <= 0 || $locationId <= 0 || $quantity <= 0) {
            return ['success' => false, 'error' => 'Product, location and a positive quantity are required.'];
        }

        $totalCost = round($unitCost * $quantity, 2);

        try {
            $this->pdo->beginTransaction();

            // 1. Movement ledger (in / purchase_order).
            $move = $this->pdo->prepare("
                INSERT INTO inventory_movements
                    (product_id, location_id, movement_type, quantity, unit_cost, total_cost,
                     reference_type, reference_id, supplier_id, reason, batch_number, expiry_date, created_by, created_at)
                VALUES (?, ?, 'in', ?, ?, ?, 'purchase_order', ?, ?, 'Supplier receipt', ?, ?, ?, NOW())
            ");
            $move->execute([$productId, $locationId, $quantity, $unitCost, $totalCost,
                            $poId, $supplierId, $batch, $expiry, $userId]);
            $movementId = (int) $this->pdo->lastInsertId();

            // 2. Costed lot for expiry tracking.
            $batchStmt = $this->pdo->prepare("
                INSERT INTO inventory_batches
                    (product_id, location_id, supplier_id, purchase_order_id, batch_number,
                     expiry_date, quantity_received, quantity_remaining, unit_cost, created_by, received_at, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $batchStmt->execute([$productId, $locationId, $supplierId, $poId, $batch,
                                 $expiry, $quantity, $quantity, $unitCost, $userId]);
            $batchId = (int) $this->pdo->lastInsertId();

            // 3. Upsert product_inventory with weighted average cost.
            $this->upsertInventory($productId, $locationId, $quantity, $unitCost);

            // 4. Catalog stock.
            $this->pdo->prepare("UPDATE products SET inventory = inventory + ? WHERE id = ?")
                      ->execute([$quantity, $productId]);

            // 5. Accounting: Dr Inventory / Cr Accounts Payable + AP sub-ledger.
            if ($postAcct && $totalCost > 0) {
                $this->accounting->postJournal(
                    [
                        ['code' => '1200', 'debit'  => $totalCost], // Inventory
                        ['code' => '2000', 'credit' => $totalCost], // Accounts Payable
                    ],
                    'Stock receipt — product #' . $productId . ($supplierId ? ' from supplier #' . $supplierId : ''),
                    'stock_receipt',
                    $movementId
                );
                $this->recordPayable($supplierId, $totalCost, $batch, $poId);
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('receiveStock failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to receive stock: ' . $e->getMessage()];
        }

        // Auto put-away: suggest/assign an optimal bin for the received units.
        // Runs after commit (SlottingService manages its own transaction) and is
        // never fatal to the receipt.
        $slot = null;
        if (($p['auto_slot'] ?? true) && $quantity > 0) {
            try {
                $slot = $this->slotting->putAway($productId, $quantity, $locationId, $userId);
            } catch (Exception $e) {
                error_log('Auto put-away failed for product ' . $productId . ': ' . $e->getMessage());
            }
        }

        return [
            'success'     => true,
            'batch_id'    => $batchId,
            'movement_id' => $movementId,
            'bin'         => ($slot && !empty($slot['success'])) ? $slot['bin_address'] : null,
        ];
    }

    /**
     * Receive lines against a purchase order and advance its status.
     *
     * @param int   $poId
     * @param array $receipts [ ['po_item_id'=>int, 'quantity'=>int, 'batch_number'=>?, 'expiry_date'=>?], ... ]
     */
    public function receivePurchaseOrder($poId, array $receipts, $userId = null)
    {
        $po = $this->pdo->prepare("SELECT id, supplier_id, location_id, approval_status, ap_id FROM purchase_orders WHERE id = ? LIMIT 1");
        $po->execute([$poId]);
        $order = $po->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return ['success' => false, 'error' => 'Purchase order not found.'];
        }

        // If the PO was approved, its liability (A/P) is already booked and the
        // goods sit in Inventory In-Transit. Receiving just moves them on-hand:
        //   Dr Inventory (1200) / Cr Inventory In-Transit (1250)
        // For ad-hoc / non-approved receipts, receiveStock books A/P as usual.
        $preApproved = ($order['approval_status'] === 'approved' && !empty($order['ap_id']));
        $inTransitValue = 0.0;

        $received = 0;
        foreach ($receipts as $r) {
            $poItemId = (int) ($r['po_item_id'] ?? 0);
            $qty      = (int) ($r['quantity'] ?? 0);
            if ($poItemId <= 0 || $qty <= 0) {
                continue;
            }

            $li = $this->pdo->prepare("
                SELECT product_id, quantity_ordered, quantity_received, unit_cost
                FROM purchase_order_items WHERE id = ? AND purchase_order_id = ? LIMIT 1
            ");
            $li->execute([$poItemId, $poId]);
            $line = $li->fetch(PDO::FETCH_ASSOC);
            if (!$line || !$line['product_id']) {
                continue;
            }

            $outstanding = (int) $line['quantity_ordered'] - (int) $line['quantity_received'];
            $qty = min($qty, $outstanding);
            if ($qty <= 0) {
                continue;
            }

            $res = $this->receiveStock([
                'product_id'        => (int) $line['product_id'],
                'supplier_id'       => (int) $order['supplier_id'],
                'location_id'       => (int) $order['location_id'],
                'quantity'          => $qty,
                'unit_cost'         => (float) $line['unit_cost'],
                'batch_number'      => $r['batch_number'] ?? null,
                'expiry_date'       => $r['expiry_date'] ?? null,
                'purchase_order_id' => $poId,
                'user_id'           => $userId,
                'post_accounting'   => !$preApproved,   // approved POs already booked A/P
            ]);
            if (!$res['success']) {
                return $res;
            }
            $inTransitValue += round((float) $line['unit_cost'] * $qty, 2);

            // Advance the PO line.
            $newReceived = (int) $line['quantity_received'] + $qty;
            $lineStatus = $newReceived >= (int) $line['quantity_ordered'] ? 'received' : 'partial_received';
            $this->pdo->prepare("
                UPDATE purchase_order_items SET quantity_received = ?, status = ? WHERE id = ?
            ")->execute([$newReceived, $lineStatus, $poItemId]);
            $received += $qty;
        }

        // Move received value from In-Transit to on-hand Inventory (approved POs).
        if ($preApproved && $inTransitValue > 0) {
            try {
                $this->accounting->postJournal(
                    [['code' => '1200', 'debit' => $inTransitValue], ['code' => '1250', 'credit' => $inTransitValue]],
                    'PO received (in-transit -> inventory)', 'purchase_order_receipt', $poId);
            } catch (Exception $e) {
                error_log('In-transit reversal failed for PO ' . $poId . ': ' . $e->getMessage());
            }
        }

        $this->refreshPoStatus($poId);
        return ['success' => true, 'received' => $received];
    }

    /**
     * Consume lots first-expiry-first-out for a sold quantity. Called from the
     * sale path so remaining lot quantities stay accurate. Safe no-op when the
     * product has no tracked lots.
     */
    public function consumeFEFO($productId, $locationId, $quantity)
    {
        $lots = $this->pdo->prepare("
            SELECT id, quantity_remaining
            FROM inventory_batches
            WHERE product_id = ? AND location_id = ? AND quantity_remaining > 0
            ORDER BY (expiry_date IS NULL), expiry_date ASC, received_at ASC
        ");
        $lots->execute([$productId, $locationId]);

        $remaining = $quantity;
        $upd = $this->pdo->prepare("UPDATE inventory_batches SET quantity_remaining = quantity_remaining - ? WHERE id = ?");
        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, (int) $lot['quantity_remaining']);
            $upd->execute([$take, $lot['id']]);
            $remaining -= $take;
        }
    }

    private function upsertInventory($productId, $locationId, $quantity, $unitCost)
    {
        $sel = $this->pdo->prepare("
            SELECT id, quantity_on_hand, average_cost
            FROM product_inventory WHERE product_id = ? AND location_id = ? LIMIT 1
        ");
        $sel->execute([$productId, $locationId]);
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $oldQty  = (int) $row['quantity_on_hand'];
            $oldCost = (float) $row['average_cost'];
            $newQty  = $oldQty + $quantity;
            // Weighted average cost.
            $avgCost = $newQty > 0
                ? round((($oldQty * $oldCost) + ($quantity * $unitCost)) / $newQty, 2)
                : $unitCost;

            $this->pdo->prepare("
                UPDATE product_inventory
                SET quantity_on_hand = ?,
                    average_cost = ?, last_cost = ?, last_movement_at = NOW()
                WHERE id = ?
            ")->execute([$newQty, $avgCost, $unitCost, $row['id']]);   // quantity_available is generated
        } else {
            $this->pdo->prepare("
                INSERT INTO product_inventory
                    (product_id, location_id, quantity_on_hand, quantity_reserved,
                     average_cost, last_cost, last_movement_at, created_at)
                VALUES (?, ?, ?, 0, ?, ?, NOW(), NOW())
            ")->execute([$productId, $locationId, $quantity, $unitCost, $unitCost]);
        }
    }

    private function recordPayable($supplierId, $amount, $batch, $poId)
    {
        $vendorName = 'Supplier';
        $dueDays = 30;
        if ($supplierId) {
            $s = $this->pdo->prepare("SELECT company_name, payment_terms FROM suppliers WHERE id = ? LIMIT 1");
            $s->execute([$supplierId]);
            if ($sup = $s->fetch(PDO::FETCH_ASSOC)) {
                $vendorName = $sup['company_name'];
                if (preg_match('/net_(\d+)/', (string) $sup['payment_terms'], $m)) {
                    $dueDays = (int) $m[1];
                }
            }
        }

        $invoice = 'RCV-' . ($poId ? 'PO' . $poId . '-' : '') . strtoupper(bin2hex(random_bytes(3)));
        $this->pdo->prepare("
            INSERT INTO accounts_payable
                (vendor_name, invoice_number, invoice_date, due_date, amount, paid_amount, status, description, created_at)
            VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, 0, 'pending', ?, NOW())
        ")->execute([$vendorName, $invoice, $dueDays, $amount,
                     'Stock receipt' . ($batch ? " (batch $batch)" : '')]);
    }

    private function refreshPoStatus($poId)
    {
        $agg = $this->pdo->prepare("
            SELECT SUM(quantity_ordered) AS ordered, SUM(quantity_received) AS received
            FROM purchase_order_items WHERE purchase_order_id = ?
        ");
        $agg->execute([$poId]);
        $t = $agg->fetch(PDO::FETCH_ASSOC);

        $ordered  = (int) ($t['ordered'] ?? 0);
        $received = (int) ($t['received'] ?? 0);
        $status = $received <= 0 ? 'sent' : ($received >= $ordered ? 'received' : 'partial_received');

        $this->pdo->prepare("
            UPDATE purchase_orders
            SET status = ?, actual_delivery_date = IF(? = 'received', CURDATE(), actual_delivery_date)
            WHERE id = ?
        ")->execute([$status, $status, $poId]);
    }
}
