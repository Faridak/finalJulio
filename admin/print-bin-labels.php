<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole('admin');
requireScope('warehouse');

$locations = $pdo->query("SELECT id, location_code, location_name FROM inventory_locations WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$locationId = (int) ($_GET['loc'] ?? ($locations[0]['id'] ?? 1));
$zoneFilter = trim($_GET['zone'] ?? '');

$sql = "
    SELECT b.id, b.bin_address, b.level_number, b.position_number,
           z.zone_code, z.zone_name, r.rack_code, il.location_code
    FROM inventory_bins b
    JOIN storage_racks r ON b.rack_id = r.id
    JOIN warehouse_zones z ON r.zone_id = z.id
    JOIN inventory_locations il ON z.location_id = il.id
    WHERE z.location_id = ?
";
$params = [$locationId];
if ($zoneFilter !== '') { $sql .= " AND z.zone_code = ?"; $params[] = $zoneFilter; }
$sql .= " ORDER BY z.zone_code, r.rack_code, b.level_number, b.position_number LIMIT 500";
$st = $pdo->prepare($sql);
$st->execute($params);
$bins = $st->fetchAll(PDO::FETCH_ASSOC);

$zones = $pdo->prepare("SELECT DISTINCT zone_code, zone_name FROM warehouse_zones WHERE location_id=? ORDER BY zone_code");
$zones->execute([$locationId]);
$zoneList = $zones->fetchAll(PDO::FETCH_ASSOC);

// Compact location code: Zone-Rack-Level-Bin (our 4-part scheme; extend to
// Zone-Rack-Bay-Level-Bin-Location when bay/sub-bin are added).
function locCode($b) {
    return sprintf('%s-%s-L%d-P%02d', $b['zone_code'], $b['rack_code'], $b['level_number'], $b['position_number']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Bin Labels - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
    <script src="../public/vendor/js/qrcode.min.js"></script>
    <style>
        @media print { .no-print{display:none} body{background:#fff} .label{break-inside:avoid} }
        .label { width: 3.6in; }
    </style>
</head>
<body class="bg-gray-100">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="no-print mb-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">Bin Location Labels</h1>
            <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded">🖨️ Print</button>
        </div>
        <form method="GET" class="mt-3 flex gap-2 items-center">
            <select name="loc" class="px-3 py-2 border rounded">
                <?php foreach ($locations as $l): ?>
                    <option value="<?= (int)$l['id'] ?>" <?= $l['id']==$locationId?'selected':'' ?>><?= htmlspecialchars($l['location_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="zone" class="px-3 py-2 border rounded">
                <option value="">All zones</option>
                <?php foreach ($zoneList as $z): ?>
                    <option value="<?= htmlspecialchars($z['zone_code']) ?>" <?= $zoneFilter===$z['zone_code']?'selected':'' ?>>
                        <?= htmlspecialchars($z['zone_code'].' — '.$z['zone_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="bg-gray-800 text-white px-4 py-2 rounded">Load</button>
            <span class="text-sm text-gray-500"><?= count($bins) ?> label(s)</span>
        </form>
    </div>

    <div class="flex flex-wrap gap-3">
        <?php foreach ($bins as $b): $code = locCode($b);
            $qr = json_encode(['t'=>'bin','id'=>(int)$b['id'],'addr'=>$b['bin_address']]); ?>
            <div class="label bg-white border-2 border-black rounded p-2 flex items-center gap-2">
                <div class="flex-1">
                    <div class="text-[10px] font-semibold text-gray-600">Zone-Rack-Bay-Level-Bin-Location</div>
                    <div class="text-3xl font-extrabold tracking-tight"><?= htmlspecialchars($code) ?></div>
                    <div class="text-[9px] text-gray-400 mt-1"><?= htmlspecialchars($b['bin_address']) ?></div>
                </div>
                <div class="qr shrink-0" data-code='<?= htmlspecialchars($qr) ?>'></div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($bins)): ?>
        <p class="text-gray-400 text-center py-12">No bins for this selection.</p>
    <?php endif; ?>
</div>

<script>
    document.querySelectorAll('.qr').forEach(el => {
        new QRCode(el, { text: el.dataset.code, width: 78, height: 78, correctLevel: QRCode.CorrectLevel.M });
    });
</script>
</body>
</html>
