<?php
/**
 * Dummy warehouse generator — builds a physical layout for a location so the
 * 2D/3D map and the slotting engine have real structure to work with.
 *
 * Layout (IKEA-style):  location -> zone -> rack(aisle) -> bin(level x position)
 * Bins carry real dimensional + weight capacity so put-away can be dimension-aware.
 *
 * Idempotent (INSERT IGNORE on unique codes).  Usage: php migration/seed_warehouse.php [locationId]
 */

$DB = ['host' => '127.0.0.1', 'name' => 'ventdepot', 'user' => 'root', 'pass' => ''];
$locationId = (int) ($argv[1] ?? 1);

$pdo = new PDO("mysql:host={$DB['host']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$loc = $pdo->prepare("SELECT location_code, location_name FROM inventory_locations WHERE id = ?");
$loc->execute([$locationId]);
$location = $loc->fetch();
if (!$location) {
    exit("Location #$locationId not found.\n");
}
$locCode = $location['location_code'];
echo "Building warehouse for {$location['location_name']} ($locCode)\n";

// Zone blueprint: code => [name, type, aisles, levels, positions, bin dims cm (LxWxH), weight kg]
$zones = [
    'RCV' => ['Receiving Dock',   'receiving', 2, 1, 6,  [200,150,180], 1000],
    'STG' => ['Main Storage',     'storage',   12,5, 12, [40,40,40],     50],
    'BLK' => ['Bulk / Pallet',    'bulk',      4, 3, 8,  [120,100,120],  500],
    'PCK' => ['Pick Face',        'picking',   6, 4, 10, [30,30,30],     20],
];

$zoneIns = $pdo->prepare("INSERT IGNORE INTO warehouse_zones (location_id, zone_code, zone_name, zone_type) VALUES (?,?,?,?)");
$rackIns = $pdo->prepare("INSERT IGNORE INTO storage_racks (zone_id, rack_code, rack_name, rack_type, aisle, levels, positions_per_level) VALUES (?,?,?,?,?,?,?)");
$binIns  = $pdo->prepare("INSERT IGNORE INTO inventory_bins
    (rack_id, bin_code, bin_address, level_number, position_number,
     length_cm, width_cm, height_cm, max_volume_cm3, volume_liters, weight_capacity_kg)
    VALUES (?,?,?,?,?,?,?,?,?,?,?)");

$totalBins = 0;

foreach ($zones as $zCode => $z) {
    [$zName, $zType, $aisles, $levels, $positions, $dims, $maxKg] = $z;
    [$L, $W, $H] = $dims;
    $volCm3 = $L * $W * $H;
    $volL = round($volCm3 / 1000, 2);

    $zoneIns->execute([$locationId, $zCode, $zName, $zType]);
    $zoneId = $pdo->query("SELECT id FROM warehouse_zones WHERE location_id=$locationId AND zone_code='$zCode'")->fetchColumn();

    $rackType = $zType === 'bulk' ? 'pallet' : ($zType === 'receiving' ? 'floor' : 'shelving');

    for ($a = 1; $a <= $aisles; $a++) {
        $rackCode = sprintf('%s%02d', substr($zCode, 0, 1), $a); // e.g. S01, B01
        $rackIns->execute([$zoneId, $rackCode, "Aisle $rackCode", $rackType, $rackCode, $levels, $positions]);
        $rackId = $pdo->query("SELECT id FROM storage_racks WHERE zone_id=$zoneId AND rack_code='$rackCode'")->fetchColumn();

        for ($lvl = 1; $lvl <= $levels; $lvl++) {
            for ($pos = 1; $pos <= $positions; $pos++) {
                $binCode = sprintf('%s-L%d-P%02d', $rackCode, $lvl, $pos);
                $binAddr = sprintf('%s-%s-%s-L%d-P%02d', $locCode, $zCode, $rackCode, $lvl, $pos);
                $binIns->execute([$rackId, $binCode, $binAddr, $lvl, $pos, $L, $W, $H, $volCm3, $volL, $maxKg]);
                $totalBins++;
            }
        }
    }
    echo "  Zone $zCode: {$aisles} aisles x {$levels} levels x {$positions} pos\n";
}

// Roll up nominal capacity onto the location (sum of bin volumes in liters).
$capL = (int) $pdo->query("
    SELECT COALESCE(SUM(volume_liters),0)
    FROM inventory_bins b JOIN storage_racks r ON b.rack_id=r.id
    JOIN warehouse_zones z ON r.zone_id=z.id WHERE z.location_id=$locationId
")->fetchColumn();
$pdo->prepare("UPDATE inventory_locations SET total_capacity=? WHERE id=?")->execute([$capL, $locationId]);

$binCount = $pdo->query("
    SELECT COUNT(*) FROM inventory_bins b JOIN storage_racks r ON b.rack_id=r.id
    JOIN warehouse_zones z ON r.zone_id=z.id WHERE z.location_id=$locationId
")->fetchColumn();

echo "Done. Bins in this location: $binCount | nominal capacity: {$capL} L\n";
