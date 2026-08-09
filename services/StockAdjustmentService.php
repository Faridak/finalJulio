<?php
/**
 * StockAdjustmentService — turns every stock discrepancy into a reason-coded,
 * costed, audited, ledger-posted event. This is the "real money" control:
 *
 *   shortage  -> Dr Inventory Shrinkage (5200)  / Cr Inventory (1200)
 *   overage   -> Dr Inventory (1200)            / Cr Inventory Adj Gain (4300)
 *
 * Anti-theft controls:
 *   - theft is NEVER auto-applied; it waits for approval.
 *   - any write-off above AUTO_APPLY_MAX_VALUE waits for approval (segregation
 *     of duties: a counter can't silently erase expensive stock).
 */
require_once __DIR__ . '/AccountingService.php';
require_once __DIR__ . '/SlottingService.php';

class StockAdjustmentService
{
    private $pdo;
    private $accounting;
    private $slotting;

    const AUTO_APPLY_MAX_VALUE = 250.00;   // write-offs above this need approval

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->accounting = new AccountingService($pdo);
        $this->slotting = new SlottingService($pdo);
    }

    /**
     * Record a discrepancy. delta = counted - system (negative = shortage/loss).
     * Applies immediately unless it needs approval (theft or high value).
     *
     * @return array{success:bool, adjustment_id?:int, status?:string, value?:float, error?:string}
     */
    public function record($productId, $locationId, $delta, $reason = 'count', $userId = null, $binId = null, $notes = null, $countedQty = null)
    {
        $delta = (int) $delta;
        if ($delta === 0) return ['success' => true, 'status' => 'noop', 'value' => 0];

        $unitCost = $this->unitCost($productId);
        $value = round(abs($delta) * $unitCost, 2);
        $systemQty = $this->systemQty($productId, $locationId);

        $needsApproval = ($reason === 'theft') || ($delta < 0 && $value > self::AUTO_APPLY_MAX_VALUE);
        $status = $needsApproval ? 'pending_approval' : 'applied';

        // Audit row first (system of record for the discrepancy).
        $this->pdo->prepare("
            INSERT INTO inventory_adjustments
                (product_id, location_id, bin_id, reason, system_qty, counted_qty, delta, unit_cost, value, status, notes, counted_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$productId, $locationId, $binId, $reason, $systemQty, $countedQty, $delta, $unitCost, $value, $status, $notes, $userId]);
        $adjId = (int) $this->pdo->lastInsertId();

        if (!$needsApproval) {
            $this->apply($adjId);
        }

        return ['success' => true, 'adjustment_id' => $adjId, 'status' => $status, 'value' => $value,
                'needs_approval' => $needsApproval];
    }

    /** Convenience wrappers. */
    public function reportDamage($productId, $locationId, $qty, $userId, $binId = null, $notes = null)
    { return $this->record($productId, $locationId, -abs((int)$qty), 'damage', $userId, $binId, $notes); }
    public function reportTheft($productId, $locationId, $qty, $userId, $binId = null, $notes = null)
    { return $this->record($productId, $locationId, -abs((int)$qty), 'theft', $userId, $binId, $notes); }
    public function reportLoss($productId, $locationId, $qty, $userId, $binId = null, $notes = null)
    { return $this->record($productId, $locationId, -abs((int)$qty), 'loss', $userId, $binId, $notes); }

    /** Approve (and apply) a pending adjustment. */
    public function approve($adjustmentId, $approverId)
    {
        $adj = $this->load($adjustmentId);
        if (!$adj || $adj['status'] !== 'pending_approval') {
            return ['success' => false, 'error' => 'Not a pending adjustment.'];
        }
        $this->apply($adjustmentId, $approverId);
        return ['success' => true, 'value' => (float) $adj['value']];
    }

    public function reject($adjustmentId, $approverId)
    {
        $this->pdo->prepare("UPDATE inventory_adjustments SET status='rejected', approved_by=?, approved_at=NOW() WHERE id=? AND status='pending_approval'")
                  ->execute([$approverId, $adjustmentId]);
        return ['success' => true];
    }

    /** Apply an adjustment: stock + bin + ledger, atomically. */
    private function apply($adjustmentId, $approverId = null)
    {
        $adj = $this->load($adjustmentId);
        if (!$adj) return;

        $pid = (int) $adj['product_id'];
        $loc = (int) $adj['location_id'];
        $delta = (int) $adj['delta'];
        $value = (float) $adj['value'];
        $unitCost = (float) $adj['unit_cost'];
        $reason = $adj['reason'];
        $binId = $adj['bin_id'] ? (int) $adj['bin_id'] : null;

        $manage = !$this->pdo->inTransaction();
        try {
            if ($manage) $this->pdo->beginTransaction();

            // Movement ledger (reason-coded).
            $refType = in_array($reason, ['theft', 'damage'], true) ? $reason : 'adjustment';
            $this->pdo->prepare("
                INSERT INTO inventory_movements
                    (product_id, location_id, movement_type, quantity, unit_cost, total_cost, reference_type, reference_id, reason, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ")->execute([$pid, $loc, $delta < 0 ? 'out' : 'in', abs($delta), $unitCost, $value, $refType, $adjustmentId, ucfirst($reason), $approverId ?? $adj['counted_by']]);

            // Projections: product_inventory + catalog mirror.
            $this->pdo->prepare("
                UPDATE product_inventory
                SET quantity_on_hand = GREATEST(quantity_on_hand + ?, 0),
                    quantity_available = GREATEST(quantity_available + ?, 0),
                    last_movement_at = NOW()
                WHERE product_id = ? AND location_id = ?
            ")->execute([$delta, $delta, $pid, $loc]);
            $this->pdo->prepare("UPDATE products SET inventory = GREATEST(inventory + ?, 0) WHERE id = ?")->execute([$delta, $pid]);

            // Bin.
            if ($binId) {
                if ($delta < 0) $this->slotting->pickFromBin($pid, $binId, abs($delta), $approverId);
                else            $this->slotting->putAway($pid, $delta, $loc, $approverId, $binId);
            }

            // Ledger — the real money.
            if ($value > 0) {
                if ($delta < 0) {
                    $this->accounting->postJournal(
                        [['code' => '5200', 'debit' => $value], ['code' => '1200', 'credit' => $value]],
                        'Inventory ' . $reason . ' write-off', 'inventory_adjustment', $adjustmentId);
                } else {
                    $this->accounting->postJournal(
                        [['code' => '1200', 'debit' => $value], ['code' => '4300', 'credit' => $value]],
                        'Inventory found/overage', 'inventory_adjustment', $adjustmentId);
                }
            }

            $this->pdo->prepare("UPDATE inventory_adjustments SET status='applied', approved_by=?, approved_at=NOW() WHERE id=?")
                      ->execute([$approverId, $adjustmentId]);

            if ($manage) $this->pdo->commit();
        } catch (Exception $e) {
            if ($manage && $this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Adjustment apply failed #' . $adjustmentId . ': ' . $e->getMessage());
            throw $e;
        }
    }

    private function load($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM inventory_adjustments WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function systemQty($pid, $loc)
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(quantity_on_hand,0) FROM product_inventory WHERE product_id=? AND location_id=? LIMIT 1");
        $stmt->execute([$pid, $loc]);
        return (int) $stmt->fetchColumn();
    }

    private function unitCost($pid)
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(NULLIF(cost_price,0),0) FROM products WHERE id=? LIMIT 1");
        $stmt->execute([$pid]);
        return (float) $stmt->fetchColumn();
    }
}
