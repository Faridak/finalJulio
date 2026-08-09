<?php
/**
 * SlottingService — smart put-away.
 *
 * When VentDepot receives a product, decide the best bin to store it in, using
 * the product's real dimensions/weight (product_dimensions) against each bin's
 * dimensional + weight capacity (inventory_bins). Scoring balances:
 *   - consolidation  : keep a SKU together (same product already in the bin)
 *   - best-fit       : minimise wasted space (least leftover volume that still fits)
 *   - ergonomics     : heavy items to low levels, light items can go high
 *   - zone fit       : bulky/heavy -> bulk zone, small -> pick/storage
 *
 * findBestBin() returns ranked candidates with a human-readable reason.
 * putAway() assigns and updates bin occupancy (volume, weight, utilisation).
 */
class SlottingService
{
    private $pdo;

    // Fallbacks when a product has no measured dimensions (dummy-data phase).
    const DEFAULT_UNIT_VOLUME_CM3 = 1000;   // 10x10x10 cm
    const DEFAULT_UNIT_WEIGHT_KG  = 0.5;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Rank the best bins for putting `quantity` of a product away at a location.
     *
     * @return array{unit:array, candidates:array}
     */
    public function findBestBin($productId, $quantity, $locationId, $limit = 5)
    {
        $unit = $this->unitProfile($productId);
        $reqVol = $unit['volume_cm3'] * $quantity;
        $reqWt  = $unit['weight_kg'] * $quantity;

        // Pull candidate bins with remaining capacity in this location's storage zones.
        $stmt = $this->pdo->prepare("
            SELECT b.id, b.bin_address, b.level_number, b.length_cm, b.width_cm, b.height_cm,
                   b.max_volume_cm3, b.weight_capacity_kg, b.current_volume_cm3, b.current_weight_kg,
                   b.current_product_id, b.occupancy_status, z.zone_type,
                   (b.max_volume_cm3 - b.current_volume_cm3)   AS rem_vol,
                   (b.weight_capacity_kg - b.current_weight_kg) AS rem_wt
            FROM inventory_bins b
            JOIN storage_racks r ON b.rack_id = r.id
            JOIN warehouse_zones z ON r.zone_id = z.id
            WHERE z.location_id = ?
              AND b.status = 'active'
              AND z.zone_type IN ('storage','bulk','picking')
              AND b.occupancy_status <> 'blocked'
              AND (b.max_volume_cm3 - b.current_volume_cm3)   >= ?
              AND (b.weight_capacity_kg - b.current_weight_kg) >= ?
        ");
        $stmt->execute([$locationId, $reqVol, $reqWt]);
        $bins = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scored = [];
        foreach ($bins as $b) {
            // Dimensional fit: a single unit must physically fit the bin (if we know dims).
            if (!$this->unitFitsBin($unit, $b)) {
                continue;
            }

            $remVol = (float) $b['rem_vol'];
            $leftover = $remVol - $reqVol;                       // space left after placing
            $bestFit = 1 - min(1, $leftover / max(1, (float) $b['max_volume_cm3']));

            $consolidation = ((int) $b['current_product_id'] === (int) $productId) ? 1 : 0;

            // Heavy unit -> reward low levels (level 1 best). Normalise by 5 levels.
            $heavy = $unit['weight_kg'] >= 5 ? 1 : ($unit['weight_kg'] / 5);
            $ergonomics = $heavy * (1 - min(1, ((int) $b['level_number'] - 1) / 5));

            // Zone fit: bulky/heavy prefer bulk; small prefer picking/storage.
            $bulky = ($unit['volume_cm3'] >= 60000 || $unit['weight_kg'] >= 25);
            $zoneFit = ($bulky && $b['zone_type'] === 'bulk') ? 1
                     : (!$bulky && in_array($b['zone_type'], ['picking', 'storage'], true) ? 1 : 0.4);

            $score = 0.40 * $consolidation
                   + 0.30 * $bestFit
                   + 0.20 * $ergonomics
                   + 0.10 * $zoneFit;

            $b['score'] = round($score, 4);
            $b['reason'] = $this->reason($consolidation, $bestFit, $ergonomics, $zoneFit, $b);
            $scored[] = $b;
        }

        usort($scored, fn($a, $c) => $c['score'] <=> $a['score']);

        return [
            'unit'       => $unit,
            'required'   => ['volume_cm3' => $reqVol, 'weight_kg' => $reqWt],
            'candidates' => array_slice($scored, 0, $limit),
        ];
    }

    /**
     * Put `quantity` away into the best bin (or a specified bin). Updates the bin's
     * occupancy and logs a product_bin_assignments row. Idempotent per (product,bin).
     *
     * @return array{success:bool, bin_id?:int, bin_address?:string, error?:string}
     */
    public function putAway($productId, $quantity, $locationId, $userId = null, $binId = null)
    {
        $unit = $this->unitProfile($productId);
        $addVol = $unit['volume_cm3'] * $quantity;
        $addWt  = $unit['weight_kg'] * $quantity;

        if ($binId === null) {
            $best = $this->findBestBin($productId, $quantity, $locationId, 1);
            if (empty($best['candidates'])) {
                return ['success' => false, 'error' => 'No bin with enough capacity found.'];
            }
            $bin = $best['candidates'][0];
            $binId = (int) $bin['id'];
        } else {
            $bin = $this->pdo->query("SELECT id, bin_address FROM inventory_bins WHERE id=" . (int) $binId)->fetch(PDO::FETCH_ASSOC);
            if (!$bin) {
                return ['success' => false, 'error' => 'Bin not found.'];
            }
        }

        $manage = !$this->pdo->inTransaction();
        try {
            if ($manage) $this->pdo->beginTransaction();

            // Assignment (accumulate if the SKU is already in this bin).
            $this->pdo->prepare("
                INSERT INTO product_bin_assignments (product_id, bin_id, assignment_type, quantity, status, assigned_by, last_movement_at, created_at)
                VALUES (?, ?, 'primary', ?, 'active', ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), last_movement_at = NOW(), status='active'
            ")->execute([$productId, $binId, $quantity, $userId]);

            // Update bin occupancy.
            $this->pdo->prepare("
                UPDATE inventory_bins
                SET current_quantity   = current_quantity + ?,
                    current_volume_cm3  = current_volume_cm3 + ?,
                    current_weight_kg   = current_weight_kg + ?,
                    current_product_id  = COALESCE(current_product_id, ?),
                    utilization_percentage = LEAST(100, ROUND((current_volume_cm3 + ?) / GREATEST(1, max_volume_cm3) * 100, 2)),
                    occupancy_status = CASE
                        WHEN (current_volume_cm3 + ?) >= max_volume_cm3 * 0.98 THEN 'full'
                        ELSE 'partial' END
                WHERE id = ?
            ")->execute([$quantity, $addVol, $addWt, $productId, $addVol, $addVol, $binId]);

            if ($manage) $this->pdo->commit();
        } catch (Exception $e) {
            if ($manage && $this->pdo->inTransaction()) $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => true, 'bin_id' => $binId, 'bin_address' => $bin['bin_address'] ?? null];
    }

    /**
     * Pick (remove) a quantity of a product from bins, first-expiry/first-in order,
     * reducing assignments and bin occupancy. Used when stock ships/sells or on a
     * scan-out. Returns the bins drawn from.
     *
     * @return array{picked:int, from:array<array{bin_id:int,bin_address:string,qty:int}>}
     */
    public function pickFEFO($productId, $locationId, $quantity, $userId = null)
    {
        $unit = $this->unitProfile($productId);
        $rows = $this->pdo->prepare("
            SELECT a.id AS assignment_id, a.bin_id, a.quantity, b.bin_address
            FROM product_bin_assignments a
            JOIN inventory_bins b ON a.bin_id = b.id
            JOIN storage_racks r ON b.rack_id = r.id
            JOIN warehouse_zones z ON r.zone_id = z.id
            WHERE a.product_id = ? AND a.status='active' AND a.quantity > 0 AND z.location_id = ?
            ORDER BY b.level_number ASC, a.created_at ASC
        ");
        $rows->execute([$productId, $locationId]);

        $remaining = (int) $quantity;
        $from = [];
        foreach ($rows as $r) {
            if ($remaining <= 0) break;
            $take = min($remaining, (int) $r['quantity']);
            $this->reduceBin((int) $r['assignment_id'], (int) $r['bin_id'], $take, $unit);
            $from[] = ['bin_id' => (int) $r['bin_id'], 'bin_address' => $r['bin_address'], 'qty' => $take];
            $remaining -= $take;
        }
        return ['picked' => (int) $quantity - $remaining, 'from' => $from];
    }

    /** Pick from one specific bin (scan-out with a known bin). */
    public function pickFromBin($productId, $binId, $quantity, $userId = null)
    {
        $unit = $this->unitProfile($productId);
        $a = $this->pdo->prepare("SELECT id, quantity FROM product_bin_assignments WHERE product_id=? AND bin_id=? AND status='active' LIMIT 1");
        $a->execute([$productId, $binId]);
        $row = $a->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['picked' => 0, 'from' => []];
        $take = min((int) $quantity, (int) $row['quantity']);
        $this->reduceBin((int) $row['id'], (int) $binId, $take, $unit);
        $addr = $this->pdo->query("SELECT bin_address FROM inventory_bins WHERE id=" . (int) $binId)->fetchColumn();
        return ['picked' => $take, 'from' => [['bin_id' => (int) $binId, 'bin_address' => $addr, 'qty' => $take]]];
    }

    /** Reduce an assignment + its bin's occupancy by qty. */
    private function reduceBin($assignmentId, $binId, $qty, $unit)
    {
        $this->pdo->prepare("UPDATE product_bin_assignments SET quantity = GREATEST(quantity - ?, 0), last_movement_at = NOW() WHERE id = ?")
                  ->execute([$qty, $assignmentId]);
        $this->pdo->prepare("UPDATE product_bin_assignments SET status='inactive' WHERE id = ? AND quantity <= 0")
                  ->execute([$assignmentId]);

        $subVol = $unit['volume_cm3'] * $qty;
        $subWt  = $unit['weight_kg'] * $qty;
        $this->pdo->prepare("
            UPDATE inventory_bins
            SET current_quantity   = GREATEST(current_quantity - ?, 0),
                current_volume_cm3  = GREATEST(current_volume_cm3 - ?, 0),
                current_weight_kg   = GREATEST(current_weight_kg - ?, 0),
                utilization_percentage = LEAST(100, ROUND(GREATEST(current_volume_cm3 - ?,0) / GREATEST(1,max_volume_cm3) * 100, 2)),
                occupancy_status = CASE WHEN (current_quantity - ?) <= 0 THEN 'empty' ELSE 'partial' END,
                current_product_id = CASE WHEN (current_quantity - ?) <= 0 THEN NULL ELSE current_product_id END
            WHERE id = ?
        ")->execute([$qty, $subVol, $subWt, $subVol, $qty, $qty, $binId]);
    }

    /** Public per-unit volume/weight for a product (for capacity math / UI). */
    public function productUnit($productId)
    {
        return $this->unitProfile($productId);
    }

    /**
     * How many boxes of a product a bin can hold, limited by BOTH volume and
     * weight. Returns the binding constraint, current fill, and remaining room.
     */
    public function binCapacityForProduct($binId, $productId)
    {
        $unit = $this->unitProfile($productId);
        $b = $this->pdo->prepare("
            SELECT max_volume_cm3, weight_capacity_kg, current_volume_cm3, current_weight_kg
            FROM inventory_bins WHERE id = ? LIMIT 1
        ");
        $b->execute([$binId]);
        $bin = $b->fetch(PDO::FETCH_ASSOC);
        if (!$bin) return null;

        $uv = max(1, (float) $unit['volume_cm3']);
        $uw = max(0.001, (float) $unit['weight_kg']);

        $maxByVol = (int) floor($bin['max_volume_cm3'] / $uv);
        $maxByWt  = (int) floor($bin['weight_capacity_kg'] / $uw);
        $maxUnits = min($maxByVol, $maxByWt);
        $binding  = $maxByWt < $maxByVol ? 'weight' : 'volume';

        $remVol = max(0, $bin['max_volume_cm3'] - $bin['current_volume_cm3']);
        $remWt  = max(0, $bin['weight_capacity_kg'] - $bin['current_weight_kg']);
        $remUnits = min((int) floor($remVol / $uv), (int) floor($remWt / $uw));

        // Units of THIS product currently in the bin.
        $cur = $this->pdo->prepare("SELECT COALESCE(quantity,0) FROM product_bin_assignments WHERE product_id=? AND bin_id=? AND status='active'");
        $cur->execute([$productId, $binId]);

        return [
            'unit_volume_cm3' => round($uv, 1),
            'unit_weight_kg'  => round($uw, 3),
            'max_by_volume'   => $maxByVol,
            'max_by_weight'   => $maxByWt,
            'max_units'       => max(0, $maxUnits),
            'binding'         => $binding,
            'current_units'   => (int) $cur->fetchColumn(),
            'remaining_units' => max(0, $remUnits),
            'volume_pct'      => round($bin['current_volume_cm3'] / max(1, $bin['max_volume_cm3']) * 100, 1),
            'weight_pct'      => round($bin['current_weight_kg'] / max(0.001, $bin['weight_capacity_kg']) * 100, 1),
        ];
    }

    /** Per-unit volume/weight/dims, with dummy-data fallbacks. */
    private function unitProfile($productId)
    {
        $stmt = $this->pdo->prepare("
            SELECT weight_kg, length_cm, width_cm, height_cm, volume_cm3
            FROM product_dimensions WHERE product_id = ? LIMIT 1
        ");
        $stmt->execute([$productId]);
        $d = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $weight = (float) ($d['weight_kg'] ?? 0) ?: self::DEFAULT_UNIT_WEIGHT_KG;
        $vol = (float) ($d['volume_cm3'] ?? 0);
        if ($vol <= 0) {
            if (!empty($d['length_cm']) && !empty($d['width_cm']) && !empty($d['height_cm'])) {
                $vol = $d['length_cm'] * $d['width_cm'] * $d['height_cm'];
            } else {
                // Estimate from weight (approx packaged density), floored.
                $vol = max(self::DEFAULT_UNIT_VOLUME_CM3, $weight * 2000);
            }
        }

        return [
            'weight_kg'  => $weight,
            'volume_cm3' => $vol,
            'length_cm'  => (float) ($d['length_cm'] ?? 0),
            'width_cm'   => (float) ($d['width_cm'] ?? 0),
            'height_cm'  => (float) ($d['height_cm'] ?? 0),
        ];
    }

    /** True if a single unit physically fits the bin (skipped when dims unknown). */
    private function unitFitsBin(array $unit, array $bin)
    {
        if ($unit['length_cm'] <= 0 || $unit['width_cm'] <= 0 || $unit['height_cm'] <= 0) {
            return true; // no measured dims -> rely on volume/weight filter only
        }
        $p = [$unit['length_cm'], $unit['width_cm'], $unit['height_cm']];
        $b = [(float) $bin['length_cm'], (float) $bin['width_cm'], (float) $bin['height_cm']];
        sort($p);
        sort($b);
        return $p[0] <= $b[0] && $p[1] <= $b[1] && $p[2] <= $b[2];
    }

    private function reason($consolidation, $bestFit, $ergonomics, $zoneFit, $bin)
    {
        $bits = [];
        if ($consolidation) $bits[] = 'consolidates existing SKU';
        if ($bestFit > 0.7) $bits[] = 'tight fit (low waste)';
        if ($ergonomics > 0.5) $bits[] = 'low level for heavy item';
        if ($zoneFit >= 1) $bits[] = "correct zone ({$bin['zone_type']})";
        return $bits ? implode(', ', $bits) : 'available capacity';
    }
}
