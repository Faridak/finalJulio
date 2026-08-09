<?php
/**
 * InventoryService
 *
 * Connects sales to the inventory ledger. When an order is paid the
 * orchestrator calls commitSaleForOrder(), which:
 *   - writes an `inventory_movements` row (type 'out', reference 'sale_order')
 *     per line, capturing unit cost so COGS can be derived;
 *   - syncs `product_inventory` (quantity_on_hand / quantity_available) when a
 *     costed inventory row exists for the product;
 *   - returns the total cost of goods sold for the order.
 *
 * Catalog stock (`products.inventory`) is deducted at order creation by
 * OrderProcessor, so this service does NOT deduct it again — it records the
 * movement ledger and the accounting cost basis.
 */
class InventoryService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Record the inventory ledger + product_inventory sync for a paid order.
     * Idempotent: if movements already exist for this order it is a no-op.
     *
     * @return float total cost of goods sold (COGS) for the order
     */
    public function commitSaleForOrder($orderId, $userId = null)
    {
        // Idempotency guard.
        $check = $this->pdo->prepare("
            SELECT 1 FROM inventory_movements
            WHERE reference_type = 'sale_order' AND reference_id = ? LIMIT 1
        ");
        $check->execute([$orderId]);
        if ($check->fetchColumn()) {
            return $this->existingCogs($orderId);
        }

        $items = $this->pdo->prepare("
            SELECT oi.product_id, oi.quantity
            FROM order_items oi
            WHERE oi.order_id = ?
        ");
        $items->execute([$orderId]);

        $moveStmt = $this->pdo->prepare("
            INSERT INTO inventory_movements
                (product_id, location_id, movement_type, quantity, unit_cost, total_cost,
                 reference_type, reference_id, reason, created_by, created_at)
            VALUES (?, ?, 'out', ?, ?, ?, 'sale_order', ?, 'Order fulfilment', ?, NOW())
        ");

        $totalCogs = 0.0;

        foreach ($items as $item) {
            $productId = (int) $item['product_id'];
            $qty       = (int) $item['quantity'];

            [$locationId, $unitCost] = $this->resolveCost($productId);
            $lineCost = round($unitCost * $qty, 2);
            $totalCogs += $lineCost;

            $moveStmt->execute([$productId, $locationId, $qty, $unitCost, $lineCost, $orderId, $userId]);

            // Sync per-location inventory when a row exists.
            if ($locationId !== null) {
                $sync = $this->pdo->prepare("
                    UPDATE product_inventory
                    SET quantity_on_hand = GREATEST(quantity_on_hand - ?, 0),
                        quantity_available = GREATEST(quantity_available - ?, 0),
                        last_movement_at = NOW()
                    WHERE product_id = ? AND location_id = ?
                ");
                $sync->execute([$qty, $qty, $productId, $locationId]);

                // Draw down costed lots first-expiry-first-out (no-op if untracked).
                $this->consumeBatchesFEFO($productId, $locationId, $qty);

                // Pick from physical bins so warehouse contents stay accurate.
                $this->pickFromBins($productId, $locationId, $qty);
            }
        }

        return $totalCogs;
    }

    /**
     * Determine unit cost and a location for a product.
     * Prefers a costed product_inventory layer; falls back to products.cost_price.
     *
     * @return array{0:?int,1:float} [locationId, unitCost]
     */
    private function resolveCost($productId)
    {
        $stmt = $this->pdo->prepare("
            SELECT location_id, average_cost, last_cost
            FROM product_inventory
            WHERE product_id = ?
            ORDER BY quantity_on_hand DESC
            LIMIT 1
        ");
        $stmt->execute([$productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $cost = (float) ($row['average_cost'] ?: $row['last_cost'] ?: 0);
            if ($cost > 0) {
                return [(int) $row['location_id'], $cost];
            }
        }

        // Fallback: catalog cost_price (added in migration 010), else 0.
        $stmt = $this->pdo->prepare("SELECT cost_price FROM products WHERE id = ? LIMIT 1");
        $stmt->execute([$productId]);
        $cost = (float) ($stmt->fetchColumn() ?: 0);

        $locationId = $row ? (int) $row['location_id'] : null;
        return [$locationId, $cost];
    }

    /**
     * Restock a returned line: put stock back and log an 'in' / 'return' movement.
     * Uses the original sale movement's unit cost as the cost basis so the COGS
     * reversal matches the original COGS. Returns the total cost restocked.
     *
     * @return float cost basis of the restocked units (for COGS reversal)
     */
    public function restockReturn($orderId, $productId, $quantity, $returnId, $userId = null)
    {
        // Cost basis: the unit cost recorded when the item was sold.
        $stmt = $this->pdo->prepare("
            SELECT unit_cost, location_id
            FROM inventory_movements
            WHERE reference_type = 'sale_order' AND reference_id = ? AND product_id = ?
            ORDER BY id LIMIT 1
        ");
        $stmt->execute([$orderId, $productId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sale) {
            $unitCost   = (float) $sale['unit_cost'];
            $locationId = $sale['location_id'] !== null ? (int) $sale['location_id'] : null;
        } else {
            [$locationId, $unitCost] = $this->resolveCost($productId);
        }

        $totalCost = round($unitCost * $quantity, 2);

        // Movement ledger (in / return).
        $move = $this->pdo->prepare("
            INSERT INTO inventory_movements
                (product_id, location_id, movement_type, quantity, unit_cost, total_cost,
                 reference_type, reference_id, reason, created_by, created_at)
            VALUES (?, ?, 'in', ?, ?, ?, 'return', ?, 'Customer return restock', ?, NOW())
        ");
        $move->execute([$productId, $locationId, $quantity, $unitCost, $totalCost, $returnId, $userId]);

        // Put catalog stock back.
        $cat = $this->pdo->prepare("UPDATE products SET inventory = inventory + ? WHERE id = ?");
        $cat->execute([$quantity, $productId]);

        // Put per-location stock back when a row exists.
        if ($locationId !== null) {
            $sync = $this->pdo->prepare("
                UPDATE product_inventory
                SET quantity_on_hand = quantity_on_hand + ?,
                    quantity_available = quantity_available + ?,
                    last_movement_at = NOW()
                WHERE product_id = ? AND location_id = ?
            ");
            $sync->execute([$quantity, $quantity, $productId, $locationId]);
        }

        return $totalCost;
    }

    /**
     * Draw down inventory_batches first-expiry-first-out for a sold quantity.
     * Safe no-op when the product has no tracked lots (table absent or empty).
     */
    private function consumeBatchesFEFO($productId, $locationId, $quantity)
    {
        try {
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
        } catch (Exception $e) {
            // inventory_batches may not exist yet; lot tracking is optional.
        }
    }

    /**
     * Reduce physical bin assignments when stock ships/sells (guarded no-op if
     * the product isn't binned). Keeps bin contents in sync with the ledger.
     */
    private function pickFromBins($productId, $locationId, $quantity)
    {
        try {
            require_once __DIR__ . '/SlottingService.php';
            $slotter = new SlottingService($this->pdo);
            $slotter->pickFEFO($productId, $locationId, $quantity);
        } catch (Exception $e) {
            // Bin tracking is optional; never block the sale.
            error_log('pickFromBins failed for product ' . $productId . ': ' . $e->getMessage());
        }
    }

    private function existingCogs($orderId)
    {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(total_cost), 0)
            FROM inventory_movements
            WHERE reference_type = 'sale_order' AND reference_id = ?
        ");
        $stmt->execute([$orderId]);
        return (float) $stmt->fetchColumn();
    }
}
