<?php
/**
 * Put-away runner (demo/dummy) — gives every stocked product a physical home so
 * the 2D/3D warehouse map is populated and the slotting engine is exercised.
 *
 *   1. Enrich product_dimensions with DUMMY L/W/H/volume derived from weight
 *      (only where dimensions are missing). Replace with real data later.
 *   2. Slot each product's on-hand stock into its optimal bin via SlottingService.
 *
 * Idempotent-ish: skips products already assigned to a bin. Usage:
 *   php migration/run_putaway.php [locationId]
 */

require_once __DIR__ . '/../services/SlottingService.php';

$DB = ['host' => '127.0.0.1', 'name' => 'ventdepot', 'user' => 'root', 'pass' => ''];
$locationId = (int) ($argv[1] ?? 1);

$pdo = new PDO("mysql:host={$DB['host']};dbname={$DB['name']};charset=utf8mb4", $DB['user'], $DB['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ---------------------------------------------------------------------------
// 1. Dummy dimensions from weight (where missing). Rough size classes so the
//    slotting engine has variety (small parts -> big appliances).
// ---------------------------------------------------------------------------
echo "Enriching product_dimensions with dummy L/W/H (where missing)...\n";
$rows = $pdo->query("
    SELECT pd.product_id, pd.weight_kg
    FROM product_dimensions pd
    WHERE (pd.volume_cm3 IS NULL OR pd.volume_cm3 = 0)
")->fetchAll();

$upd = $pdo->prepare("UPDATE product_dimensions SET length_cm=?, width_cm=?, height_cm=?, volume_cm3=? WHERE product_id=?");
$enriched = 0;
foreach ($rows as $r) {
    $w = (float) $r['weight_kg'];
    // crude size classes by weight
    if ($w <= 0.2)      { $l=12;$wd=8;$h=4; }
    elseif ($w <= 1)    { $l=20;$wd=15;$h=10; }
    elseif ($w <= 5)    { $l=35;$wd=25;$h=20; }
    elseif ($w <= 25)   { $l=60;$wd=45;$h=40; }
    else                { $l=110;$wd=80;$h=90; }
    // jitter so bins fill unevenly (more realistic)
    $l += rand(-2,2); $wd += rand(-2,2); $h += rand(-2,2);
    $vol = $l * $wd * $h;
    $upd->execute([$l, $wd, $h, $vol, $r['product_id']]);
    $enriched++;
}
echo "  enriched $enriched products.\n";

// ---------------------------------------------------------------------------
// 2. Slot on-hand stock into optimal bins.
// ---------------------------------------------------------------------------
$slotter = new SlottingService($pdo);

$already = $pdo->query("SELECT DISTINCT product_id FROM product_bin_assignments WHERE status='active'")->fetchAll(PDO::FETCH_COLUMN);
$already = array_flip($already);

$stock = $pdo->prepare("
    SELECT pi.product_id, pi.quantity_on_hand
    FROM product_inventory pi
    WHERE pi.location_id = ? AND pi.quantity_on_hand > 0
");
$stock->execute([$locationId]);
$items = $stock->fetchAll();

$slotted = 0; $overflow = 0; $skipped = 0;
foreach ($items as $it) {
    $pid = (int) $it['product_id'];
    if (isset($already[$pid])) { $skipped++; continue; }

    // Cap the put-away quantity so a single SKU doesn't monopolise the dummy
    // warehouse; represents one replenishment case.
    $qty = min((int) $it['quantity_on_hand'], 30);

    $res = $slotter->putAway($pid, $qty, $locationId, null);
    if ($res['success']) { $slotted++; }
    else { $overflow++; }

    if (($slotted + $overflow) % 500 === 0) echo "  ...processed " . ($slotted + $overflow) . "\n";
}

echo "\n=== Put-away complete ===\n";
echo "Slotted : $slotted\n";
echo "Overflow (no capacity): $overflow\n";
echo "Skipped (already placed): $skipped\n";
