<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/SlottingService.php';

requireRole('admin');
requireScope('warehouse');

// --- Location selection ---
$locations = $pdo->query("SELECT id, location_code, location_name FROM inventory_locations WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$locationId = (int) ($_GET['loc'] ?? ($locations[0]['id'] ?? 1));

// --- Product locator ---
$search = trim($_GET['q'] ?? '');
$foundBins = [];
if ($search !== '') {
    $st = $pdo->prepare("
        SELECT pr.name, pr.sku, a.quantity, b.bin_address, z.zone_name,
               r.rack_code AS aisle, b.level_number, b.position_number
        FROM product_bin_assignments a
        JOIN products pr ON a.product_id = pr.id
        JOIN inventory_bins b ON a.bin_id = b.id
        JOIN storage_racks r ON b.rack_id = r.id
        JOIN warehouse_zones z ON r.zone_id = z.id
        WHERE z.location_id = ? AND a.status='active'
          AND (pr.name LIKE ? OR pr.sku LIKE ? OR pr.legacy_id LIKE ?)
        ORDER BY pr.name LIMIT 100
    ");
    $like = "%$search%";
    $st->execute([$locationId, $like, $like, $like]);
    $foundBins = $st->fetchAll(PDO::FETCH_ASSOC);
}
$highlightBins = array_column($foundBins, 'bin_address');

// --- Put-away recommender ---
$reco = null; $recoProduct = null;
if (isset($_GET['reco_product']) && $_GET['reco_product'] !== '') {
    $recoProduct = (int) $_GET['reco_product'];
    $qty = max(1, (int) ($_GET['reco_qty'] ?? 1));
    $reco = (new SlottingService($pdo))->findBestBin($recoProduct, $qty, $locationId, 5);
}
$products = $pdo->query("SELECT id, name, sku FROM products WHERE legacy_id IS NOT NULL ORDER BY name LIMIT 400")->fetchAll(PDO::FETCH_ASSOC);

// --- Warehouse structure + occupancy ---
$bins = $pdo->prepare("
    SELECT z.zone_name, z.zone_type, r.rack_code AS aisle,
           b.bin_code, b.bin_address, b.level_number, b.position_number,
           b.occupancy_status, b.utilization_percentage, b.current_quantity,
           b.max_volume_cm3, b.current_volume_cm3
    FROM inventory_bins b
    JOIN storage_racks r ON b.rack_id = r.id
    JOIN warehouse_zones z ON r.zone_id = z.id
    WHERE z.location_id = ?
    ORDER BY z.zone_type, r.rack_code, b.level_number DESC, b.position_number
");
$bins->execute([$locationId]);

// Group: zone -> aisle -> level -> [bins]
$tree = [];
$maxLevel = []; $maxPos = [];
foreach ($bins as $b) {
    $z = $b['zone_name']; $a = $b['aisle'];
    $tree[$z]['type'] = $b['zone_type'];
    $tree[$z]['aisles'][$a][(int)$b['level_number']][(int)$b['position_number']] = $b;
    $maxLevel[$z.$a] = max($maxLevel[$z.$a] ?? 0, (int)$b['level_number']);
    $maxPos[$z.$a]   = max($maxPos[$z.$a] ?? 0, (int)$b['position_number']);
}

// Bin contents (for click popovers) keyed by bin_address.
$contents = [];
$cst = $pdo->prepare("
    SELECT b.bin_address, pr.name, pr.sku, a.quantity
    FROM product_bin_assignments a
    JOIN inventory_bins b ON a.bin_id=b.id
    JOIN storage_racks r ON b.rack_id=r.id
    JOIN warehouse_zones z ON r.zone_id=z.id
    JOIN products pr ON a.product_id = pr.id
    WHERE z.location_id=? AND a.status='active'
");
$cst->execute([$locationId]);
foreach ($cst as $row) {
    $contents[$row['bin_address']][] = ['name'=>$row['name'], 'sku'=>$row['sku'], 'qty'=>(int)$row['quantity']];
}

// Zone summary
$summary = $pdo->prepare("
    SELECT z.zone_name, z.zone_type, COUNT(*) bins,
           SUM(b.occupancy_status<>'empty') used, ROUND(AVG(b.utilization_percentage),1) util
    FROM inventory_bins b JOIN storage_racks r ON b.rack_id=r.id JOIN warehouse_zones z ON r.zone_id=z.id
    WHERE z.location_id=? GROUP BY z.id ORDER BY z.zone_type
");
$summary->execute([$locationId]);
$zoneSummary = $summary->fetchAll(PDO::FETCH_ASSOC);

function binClass($status, $util) {
    if ($status === 'empty')  return 'bg-gray-100 border-gray-200 text-gray-400';
    if ($status === 'full' || $util >= 90) return 'bg-red-500 border-red-600 text-white';
    if ($util >= 50) return 'bg-amber-400 border-amber-500 text-white';
    return 'bg-green-400 border-green-500 text-white';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Map - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Warehouse Map</h1>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-sm text-gray-600">Location</label>
            <select name="loc" onchange="this.form.submit()" class="px-3 py-2 border rounded">
                <?php foreach ($locations as $l): ?>
                    <option value="<?= (int)$l['id'] ?>" <?= $l['id']==$locationId?'selected':'' ?>>
                        <?= htmlspecialchars($l['location_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- Zone summary -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php foreach ($zoneSummary as $s): ?>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-xs uppercase text-gray-400"><?= htmlspecialchars($s['zone_type']) ?></div>
                <div class="font-semibold text-gray-800"><?= htmlspecialchars($s['zone_name']) ?></div>
                <div class="text-sm text-gray-500 mt-1"><?= (int)$s['used'] ?>/<?= (int)$s['bins'] ?> bins · <?= $s['util'] ?>% full</div>
                <div class="w-full bg-gray-200 rounded h-2 mt-2">
                    <div class="bg-blue-500 h-2 rounded" style="width: <?= min(100,(float)$s['util']) ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid md:grid-cols-3 gap-6 mb-6">
        <!-- Product locator -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">🔎 Where is my product?</h2>
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="loc" value="<?= $locationId ?>">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="name / SKU / legacy id"
                       class="flex-1 px-3 py-2 border rounded">
                <button class="bg-blue-600 text-white px-4 rounded">Find</button>
            </form>
            <?php if ($search !== ''): ?>
                <div class="mt-3 text-sm">
                    <?php if (empty($foundBins)): ?>
                        <p class="text-gray-400">No placements found.</p>
                    <?php else: foreach ($foundBins as $f): ?>
                        <div class="border-b py-2">
                            <div class="font-medium"><?= htmlspecialchars($f['name']) ?> <span class="text-gray-400">(<?= htmlspecialchars($f['sku']) ?>)</span></div>
                            <div class="text-gray-600">📍 <?= htmlspecialchars($f['bin_address']) ?> — <?= htmlspecialchars($f['zone_name']) ?>, aisle <?= htmlspecialchars($f['aisle']) ?>, level <?= (int)$f['level_number'] ?>, pos <?= (int)$f['position_number'] ?> · qty <?= (int)$f['quantity'] ?></div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Put-away recommender -->
        <div class="bg-white rounded-lg shadow p-5 md:col-span-2">
            <h2 class="font-semibold text-gray-800 mb-3">📦 Receive &amp; slot — best bin recommendation</h2>
            <form method="GET" class="flex flex-wrap gap-2 items-end">
                <input type="hidden" name="loc" value="<?= $locationId ?>">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs text-gray-500 mb-1">Product</label>
                    <select name="reco_product" class="w-full px-3 py-2 border rounded">
                        <?php foreach ($products as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= $recoProduct==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['sku']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Qty</label>
                    <input type="number" name="reco_qty" min="1" value="<?= (int)($_GET['reco_qty'] ?? 10) ?>" class="w-24 px-3 py-2 border rounded">
                </div>
                <button class="bg-green-600 text-white px-4 py-2 rounded">Recommend bin</button>
            </form>
            <?php if ($reco): ?>
                <div class="mt-3 text-sm">
                    <p class="text-gray-500 mb-2">Needs vol <?= number_format($reco['required']['volume_cm3']) ?>cm³, wt <?= $reco['required']['weight_kg'] ?>kg. Top bins:</p>
                    <?php if (empty($reco['candidates'])): ?>
                        <p class="text-red-500">No bin with enough capacity — warehouse full for this item.</p>
                    <?php else: foreach ($reco['candidates'] as $i => $c): ?>
                        <div class="flex items-center gap-3 py-1">
                            <span class="w-6 text-center font-bold text-gray-400">#<?= $i+1 ?></span>
                            <span class="font-mono"><?= htmlspecialchars($c['bin_address']) ?></span>
                            <span class="text-gray-500">score <?= $c['score'] ?></span>
                            <span class="text-blue-600"><?= htmlspecialchars($c['reason']) ?></span>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Legend -->
    <div class="flex items-center gap-4 text-xs text-gray-500 mb-3">
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block bg-gray-100 border border-gray-200"></span> empty</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block bg-green-400"></span> &lt;50%</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block bg-amber-400"></span> 50–90%</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block bg-red-500"></span> full</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block ring-2 ring-blue-600"></span> search match</span>
    </div>

    <!-- 2D grid: zone -> aisle -> (level rows x position cols) -->
    <?php foreach ($tree as $zoneName => $zone): ?>
        <div class="mb-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-2"><?= htmlspecialchars($zoneName) ?>
                <span class="text-xs font-normal text-gray-400">(<?= htmlspecialchars($zone['type']) ?>)</span></h3>
            <?php foreach ($zone['aisles'] as $aisle => $levels): krsort($levels); ?>
                <div class="mb-4">
                    <div class="text-sm text-gray-500 mb-1">Aisle <?= htmlspecialchars($aisle) ?></div>
                    <div class="inline-block bg-white rounded-lg shadow p-2">
                        <?php foreach ($levels as $lvl => $positions): ksort($positions); ?>
                            <div class="flex items-center gap-1 mb-1">
                                <span class="w-8 text-[10px] text-gray-400 text-right pr-1">L<?= $lvl ?></span>
                                <?php foreach ($positions as $pos => $b):
                                    $hl = in_array($b['bin_address'], $highlightBins, true);
                                    $cls = binClass($b['occupancy_status'], (float)$b['utilization_percentage']); ?>
                                    <div class="w-10 h-10 border rounded flex items-center justify-center text-[9px] cursor-pointer <?= $cls ?> <?= $hl?'ring-2 ring-blue-600 ring-offset-1':'' ?>"
                                         title="<?= htmlspecialchars($b['bin_address']) ?> — <?= (int)$b['utilization_percentage'] ?>% (<?= (int)$b['current_quantity'] ?> units)"
                                         onclick='showBin(<?= json_encode($b['bin_address']) ?>)'>
                                        <?= (int)$b['utilization_percentage'] ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<!-- Bin content popover -->
<div id="binModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center" onclick="this.classList.add('hidden');this.classList.remove('flex')">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full" onclick="event.stopPropagation()">
        <h3 id="binTitle" class="font-bold text-lg mb-3"></h3>
        <div id="binBody" class="text-sm"></div>
        <button onclick="document.getElementById('binModal').classList.add('hidden')" class="mt-4 bg-gray-200 px-4 py-2 rounded">Close</button>
    </div>
</div>
<script>
    const BIN_CONTENTS = <?= json_encode($contents, JSON_UNESCAPED_UNICODE) ?>;
    function showBin(addr){
        const items = BIN_CONTENTS[addr] || [];
        document.getElementById('binTitle').textContent = addr;
        document.getElementById('binBody').innerHTML = items.length
            ? '<table class="w-full"><tr class="text-gray-400 text-left"><th>Product</th><th>SKU</th><th>Qty</th></tr>' +
              items.map(i=>`<tr class="border-t"><td class="py-1">${i.name}</td><td>${i.sku||''}</td><td>${i.qty}</td></tr>`).join('') + '</table>'
            : '<p class="text-gray-400">Empty bin.</p>';
        const m=document.getElementById('binModal'); m.classList.remove('hidden'); m.classList.add('flex');
    }
</script>
</body>
</html>
