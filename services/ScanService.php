<?php
/**
 * ScanService — barcode-driven inventory control.
 *
 * Every scan writes to the inventory_movements ledger (the source of truth) and
 * keeps the projections (product_inventory, product_bin_assignments) in sync.
 *
 *   scanIn()   : item into a bin (put-away / receive by scan)  -> movement 'in'
 *   scanOut()  : item leaves (sale / ship / shrink)            -> movement 'out'
 *   countBin() : physical count of a bin, variance vs system, optional adjust
 *
 * Bin references accept the bin QR payload (JSON {t:'bin',id,addr}), a full
 * bin_address, a short bin_code, or an id. Item references accept barcode or SKU.
 */
require_once __DIR__ . '/SkuBarcodeService.php';
require_once __DIR__ . '/SlottingService.php';
require_once __DIR__ . '/StockAdjustmentService.php';

class ScanService
{
    private $pdo;
    private $sku;
    private $slotting;
    private $adjustments;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sku = new SkuBarcodeService($pdo);
        $this->slotting = new SlottingService($pdo);
        $this->adjustments = new StockAdjustmentService($pdo);
    }

    /**
     * Suggest a counted quantity from a weight reading (for small parts like
     * nails): qty = round(total weight / unit weight). Non-destructive helper.
     */
    public function countByWeight($itemCode, $totalWeightKg)
    {
        $product = $this->sku->findByBarcode($this->clean($itemCode));
        if (!$product) return ['success' => false, 'error' => "Unknown item: $itemCode"];
        $w = $this->pdo->prepare("SELECT weight_kg FROM product_dimensions WHERE product_id=? LIMIT 1");
        $w->execute([$product['id']]);
        $unit = (float) $w->fetchColumn();
        if ($unit <= 0) return ['success' => false, 'error' => 'No unit weight on file for this item.'];
        return ['success' => true, 'sku' => $product['sku'], 'unit_weight_kg' => $unit,
                'qty' => (int) round(((float) $totalWeightKg) / $unit)];
    }

    /** Item INTO a bin. */
    public function scanIn($itemCode, $binCode, $qty = 1, $userId = null)
    {
        $qty = max(1, (int) $qty);
        $product = $this->sku->findByBarcode($this->clean($itemCode));
        if (!$product) return ['success' => false, 'error' => "Unknown item: $itemCode"];
        $bin = $this->resolveBin($binCode);
        if (!$bin) return ['success' => false, 'error' => "Unknown bin: $binCode"];

        $cost = $this->unitCost($product['id']);
        try {
            $this->pdo->beginTransaction();
            $this->movement($product['id'], $bin['location_id'], 'in', $qty, $cost, 'adjustment', null, 'Scan in', $userId);
            $this->adjustInventory($product['id'], $bin['location_id'], $qty, $cost);
            $this->slotting->putAway($product['id'], $qty, $bin['location_id'], $userId, $bin['id']);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
        return ['success' => true, 'action' => 'in', 'product' => $product['name'], 'sku' => $product['sku'],
                'qty' => $qty, 'bin' => $bin['bin_address']];
    }

    /** Item OUT (sale / ship / shrink). */
    public function scanOut($itemCode, $qty = 1, $userId = null, $binCode = null, $reason = 'Scan out')
    {
        $qty = max(1, (int) $qty);
        $product = $this->sku->findByBarcode($this->clean($itemCode));
        if (!$product) return ['success' => false, 'error' => "Unknown item: $itemCode"];

        $bin = $binCode ? $this->resolveBin($binCode) : null;
        $locationId = $bin['location_id'] ?? $this->primaryLocation($product['id']);
        if (!$locationId) return ['success' => false, 'error' => 'No stock location for this item.'];

        $cost = $this->unitCost($product['id']);
        try {
            $this->pdo->beginTransaction();
            $pick = $bin
                ? $this->slotting->pickFromBin($product['id'], $bin['id'], $qty, $userId)
                : $this->slotting->pickFEFO($product['id'], $locationId, $qty, $userId);
            $this->adjustInventory($product['id'], $locationId, -$qty, $cost);
            $this->movement($product['id'], $locationId, 'out', $qty, $cost, 'adjustment', null, $reason, $userId);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
        return ['success' => true, 'action' => 'out', 'product' => $product['name'], 'sku' => $product['sku'],
                'qty' => $qty, 'from' => $pick['from'] ?? []];
    }

    /**
     * Physical count of a bin. $counts = [ itemCode => countedQty, ... ].
     * Returns variance vs system; when $apply=true, posts adjustments and
     * rewrites the bin to the counted reality.
     */
    public function countBin($binCode, array $counts, $userId = null, $apply = false)
    {
        $bin = $this->resolveBin($binCode);
        if (!$bin) return ['success' => false, 'error' => "Unknown bin: $binCode"];

        // System view of this bin.
        $sys = [];
        $q = $this->pdo->prepare("SELECT product_id, quantity FROM product_bin_assignments WHERE bin_id=? AND status='active'");
        $q->execute([$bin['id']]);
        foreach ($q as $r) $sys[(int) $r['product_id']] = (int) $r['quantity'];

        // Counted view (resolve codes -> product ids).
        $counted = [];
        $names = [];
        foreach ($counts as $code => $cq) {
            $p = $this->sku->findByBarcode($this->clean($code));
            if (!$p) return ['success' => false, 'error' => "Unknown item in count: $code"];
            $counted[(int) $p['id']] = ($counted[(int) $p['id']] ?? 0) + max(0, (int) $cq);
            $names[(int) $p['id']] = $p['sku'] . ' · ' . $p['name'];
        }

        $variance = [];
        foreach (array_unique(array_merge(array_keys($sys), array_keys($counted))) as $pid) {
            $s = $sys[$pid] ?? 0; $c = $counted[$pid] ?? 0;
            if ($s !== $c) $variance[] = ['product' => $names[$pid] ?? ('#' . $pid), 'product_id' => $pid,
                                          'system' => $s, 'counted' => $c, 'delta' => $c - $s];
        }

        $pending = [];
        if ($apply && $variance) {
            foreach ($variance as &$v) {
                // Route through the adjustment engine: reason-coded, costed,
                // ledger-posted, and gated by approval for big/theft write-offs.
                $res = $this->adjustments->record(
                    $v['product_id'], $bin['location_id'], $v['delta'], 'count',
                    $userId, $bin['id'], 'Cycle count', $v['counted']
                );
                $v['status'] = $res['status'] ?? 'applied';
                $v['value'] = $res['value'] ?? 0;
                if (($res['status'] ?? '') === 'pending_approval') $pending[] = $v['product'];
            }
            unset($v);
        }

        // Stamp the bin as counted (feeds risk-weighted cycle counting).
        $this->pdo->prepare("UPDATE inventory_bins SET last_counted_at = NOW() WHERE id = ?")->execute([$bin['id']]);

        return ['success' => true, 'bin' => $bin['bin_address'], 'variance' => $variance,
                'matched' => empty($variance), 'applied' => $apply && !empty($variance),
                'pending_approval' => $pending];
    }

    // ---- helpers ----------------------------------------------------------

    private function resolveBin($code)
    {
        $code = trim((string) $code);
        // QR JSON payload?
        if ($code !== '' && $code[0] === '{') {
            $j = json_decode($code, true);
            if (isset($j['id'])) return $this->binById((int) $j['id']);
            if (isset($j['addr'])) $code = $j['addr'];
        }
        if (ctype_digit($code)) { $b = $this->binById((int) $code); if ($b) return $b; }

        $stmt = $this->pdo->prepare("
            SELECT b.id, b.bin_address, z.location_id
            FROM inventory_bins b JOIN storage_racks r ON b.rack_id=r.id JOIN warehouse_zones z ON r.zone_id=z.id
            WHERE b.bin_address = ? OR b.bin_code = ? OR b.bin_address LIKE ? LIMIT 1
        ");
        $stmt->execute([$code, $code, '%' . $code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function binById($id)
    {
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.bin_address, z.location_id
            FROM inventory_bins b JOIN storage_racks r ON b.rack_id=r.id JOIN warehouse_zones z ON r.zone_id=z.id
            WHERE b.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function movement($pid, $loc, $type, $qty, $cost, $refType, $refId, $reason, $userId)
    {
        $this->pdo->prepare("
            INSERT INTO inventory_movements
                (product_id, location_id, movement_type, quantity, unit_cost, total_cost, reference_type, reference_id, reason, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$pid, $loc, $type, $qty, $cost, round($cost * $qty, 2), $refType, $refId, $reason, $userId]);
    }

    private function adjustInventory($pid, $loc, $delta, $cost)
    {
        $this->pdo->prepare("
            INSERT INTO product_inventory (product_id, location_id, quantity_on_hand, quantity_reserved, quantity_available, average_cost, last_cost, last_movement_at, created_at)
            VALUES (?, ?, GREATEST(?,0), 0, GREATEST(?,0), ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                quantity_on_hand = GREATEST(quantity_on_hand + ?, 0),
                quantity_available = GREATEST(quantity_available + ?, 0),
                last_movement_at = NOW()
        ")->execute([$pid, $loc, $delta, $delta, $cost, $cost, $delta, $delta]);
        // Keep catalog mirror roughly in step.
        $this->pdo->prepare("UPDATE products SET inventory = GREATEST(inventory + ?, 0) WHERE id = ?")->execute([$delta, $pid]);
    }

    private function primaryLocation($pid)
    {
        $stmt = $this->pdo->prepare("SELECT location_id FROM product_inventory WHERE product_id=? ORDER BY quantity_on_hand DESC LIMIT 1");
        $stmt->execute([$pid]);
        return $stmt->fetchColumn() ?: null;
    }

    private function unitCost($pid)
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(NULLIF(cost_price,0), 0) FROM products WHERE id=? LIMIT 1");
        $stmt->execute([$pid]);
        return (float) $stmt->fetchColumn();
    }

    private function clean($code)
    {
        return trim((string) $code);
    }
}
