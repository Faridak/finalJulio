<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole('admin');
requireScope('warehouse');

$locations = $pdo->query("SELECT id, location_code, location_name FROM inventory_locations WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$locationId = (int) ($_GET['loc'] ?? ($locations[0]['id'] ?? 1));
$search = trim($_GET['q'] ?? '');

// Bins + occupancy for this location.
$binStmt = $pdo->prepare("
    SELECT z.zone_name, z.zone_type, r.rack_code AS aisle,
           b.bin_address, b.level_number, b.position_number,
           b.occupancy_status, b.utilization_percentage, b.current_quantity,
           b.current_volume_cm3, b.max_volume_cm3, b.current_weight_kg, b.weight_capacity_kg,
           DATEDIFF(NOW(), b.last_counted_at) AS days_since_count,
           (SELECT COALESCE(SUM(pa.quantity * pr.cost_price), 0)
            FROM product_bin_assignments pa JOIN products pr ON pa.product_id = pr.id
            WHERE pa.bin_id = b.id AND pa.status='active') AS bin_value
    FROM inventory_bins b
    JOIN storage_racks r ON b.rack_id = r.id
    JOIN warehouse_zones z ON r.zone_id = z.id
    WHERE z.location_id = ?
    ORDER BY z.zone_type, r.rack_code, b.level_number, b.position_number
");
$binStmt->execute([$locationId]);
$bins = $binStmt->fetchAll(PDO::FETCH_ASSOC);

// Optional "capacity for this product" mode: pass its unit volume/weight so the
// client can compute how many boxes fit / remain per bin.
require_once __DIR__ . '/../services/SlottingService.php';
$itemId = (int) ($_GET['item'] ?? 0);
$unit = null; $unitProduct = null;
if ($itemId > 0) {
    $unit = (new SlottingService($pdo))->productUnit($itemId);
    $up = $pdo->prepare("SELECT name, sku FROM products WHERE id=? LIMIT 1");
    $up->execute([$itemId]);
    $unitProduct = $up->fetch(PDO::FETCH_ASSOC);
}
$productOpts = $pdo->query("SELECT id, name, sku FROM products WHERE ownership='own' ORDER BY name LIMIT 400")->fetchAll(PDO::FETCH_ASSOC);

// Bin contents for click popovers.
$contents = [];
$cst = $pdo->prepare("
    SELECT b.bin_address, pr.name, pr.sku, a.quantity
    FROM product_bin_assignments a
    JOIN inventory_bins b ON a.bin_id = b.id
    JOIN storage_racks r ON b.rack_id = r.id
    JOIN warehouse_zones z ON r.zone_id = z.id
    JOIN products pr ON a.product_id = pr.id
    WHERE z.location_id = ? AND a.status='active'
");
$cst->execute([$locationId]);
foreach ($cst as $row) {
    $contents[$row['bin_address']][] = ['name' => $row['name'], 'sku' => $row['sku'], 'qty' => (int) $row['quantity']];
}

// Bins that hold the searched product (for 3D highlight).
$highlight = [];
if ($search !== '') {
    $hs = $pdo->prepare("
        SELECT DISTINCT b.bin_address
        FROM product_bin_assignments a
        JOIN inventory_bins b ON a.bin_id = b.id
        JOIN storage_racks r ON b.rack_id = r.id
        JOIN warehouse_zones z ON r.zone_id = z.id
        JOIN products pr ON a.product_id = pr.id
        WHERE z.location_id = ? AND a.status='active'
          AND (pr.name LIKE ? OR pr.sku LIKE ? OR pr.legacy_id LIKE ?)
    ");
    $like = "%$search%";
    $hs->execute([$locationId, $like, $like, $like]);
    $highlight = $hs->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse 3D - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
    <style>
        #scene { width: 100%; height: 78vh; background: #0f172a; border-radius: 12px; }
        .chip { position:absolute; }
    </style>
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Warehouse 3D</h1>
        <div class="flex items-center gap-2">
            <a href="warehouse-map.php?loc=<?= $locationId ?>" class="text-blue-600 hover:underline text-sm">2D map →</a>
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="loc" value="<?= $locationId ?>">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="find product…"
                       class="px-3 py-2 border rounded text-sm">
                <button class="bg-blue-600 text-white px-3 py-2 rounded text-sm">Locate</button>
            </form>
            <form method="GET">
                <select name="loc" onchange="this.form.submit()" class="px-3 py-2 border rounded text-sm">
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= (int)$l['id'] ?>" <?= $l['id']==$locationId?'selected':'' ?>><?= htmlspecialchars($l['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <form method="GET" class="flex items-center gap-1">
                <input type="hidden" name="loc" value="<?= $locationId ?>">
                <select name="item" onchange="this.form.submit()" class="px-3 py-2 border rounded text-sm max-w-[200px]">
                    <option value="0">— capacity for item… —</option>
                    <?php foreach ($productOpts as $po): ?>
                        <option value="<?= (int)$po['id'] ?>" <?= $itemId==$po['id']?'selected':'' ?>><?= htmlspecialchars($po['sku'].' · '.$po['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($itemId): ?>
                <input type="number" name="need" min="0" value="<?= (int)($_GET['need'] ?? 0) ?>" placeholder="boxes" title="incoming boxes"
                       onchange="this.form.submit()" class="w-20 px-2 py-2 border rounded text-sm">
                <?php endif; ?>
            </form>
        </div>
    </div>

    <?php if ($unit && $unitProduct): ?>
    <div class="mb-2 text-sm bg-indigo-50 border border-indigo-200 rounded px-3 py-2">
        Capacity mode: <b><?= htmlspecialchars($unitProduct['sku'].' · '.$unitProduct['name']) ?></b>
        — unit <?= round($unit['volume_cm3']) ?> cm³, <?= round($unit['weight_kg'],3) ?> kg.
        <span id="capVerdict"></span>
    </div>
    <?php endif; ?>
    <div id="weightAlert" class="mb-2 text-sm hidden bg-red-50 border border-red-200 text-red-800 rounded px-3 py-2"></div>

    <div class="flex items-center gap-4 text-xs text-gray-600 mb-2">
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block" style="background:#475569"></span> empty</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block" style="background:#22c55e"></span> &lt;50%</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block" style="background:#f59e0b"></span> 50–90%</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block" style="background:#ef4444"></span> full</span>
        <span class="flex items-center gap-1"><span class="w-3 h-3 inline-block" style="background:#3b82f6"></span> search match</span>
        <button onclick="randomBin()" class="ml-auto bg-purple-600 text-white px-3 py-1 rounded text-xs">🎲 Random bin to count</button>
        <span class="text-gray-400">drag = orbit · scroll = zoom · click a bin</span>
    </div>

    <div class="relative">
        <div id="scene"></div>
    </div>
</div>

<!-- Bin detail modal: item list + spot-count action -->
<div id="binModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50" onclick="closeBin()">
    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full" onclick="event.stopPropagation()">
        <div class="flex items-start justify-between mb-1">
            <h3 id="mTitle" class="font-mono font-bold text-lg"></h3>
            <button onclick="closeBin()" class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
        </div>
        <div id="mMeta" class="text-sm text-gray-500 mb-3"></div>
        <div id="mBody" class="text-sm"></div>
        <div id="mCap" class="text-sm mt-3"></div>
        <a id="mCount" href="#" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded text-sm">📋 Spot-count this bin</a>
    </div>
</div>

<script src="../public/vendor/js/three.min.js"></script>
<script src="../public/vendor/js/OrbitControls.js"></script>
<script>
const BINS = <?= json_encode($bins, JSON_UNESCAPED_UNICODE) ?>;
const CONTENTS = <?= json_encode($contents, JSON_UNESCAPED_UNICODE) ?>;
const HIGHLIGHT = new Set(<?= json_encode($highlight) ?>);

const host = document.getElementById('scene');
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0f172a);

const camera = new THREE.PerspectiveCamera(55, host.clientWidth/host.clientHeight, 0.1, 2000);
camera.position.set(60, 55, 90);

const renderer = new THREE.WebGLRenderer({antialias:true});
renderer.setSize(host.clientWidth, host.clientHeight);
host.appendChild(renderer.domElement);

const controls = new THREE.OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;

scene.add(new THREE.AmbientLight(0xffffff, 0.75));
const dir = new THREE.DirectionalLight(0xffffff, 0.6); dir.position.set(50,80,40); scene.add(dir);

// --- Layout: group zones, aisles within a zone, positions along X, levels up Y. ---
const zones = {};           // zoneName -> {type, aisles:Set}
BINS.forEach(b => { (zones[b.zone_name] = zones[b.zone_name] || {type:b.zone_type, aisles:new Set()}).aisles.add(b.aisle); });
const zoneNames = Object.keys(zones);

// assign each zone a Z block, each aisle a Z row, each position an X, each level a Y
const GAP_ZONE = 6, BIN = 1.6, AISLE_GAP = 2.2, POS_GAP = 0.15, LEVEL = 1.7;
const aisleZ = {};   // key zone|aisle -> z
let zCursor = 0;
zoneNames.forEach(zn => {
    const aisles = Array.from(zones[zn].aisles).sort();
    aisles.forEach(a => { aisleZ[zn+'|'+a] = zCursor; zCursor += (BIN + AISLE_GAP); });
    zCursor += GAP_ZONE;
});
const totalZ = zCursor;

const UNIT = <?= ($unit && $unitProduct) ? json_encode(['vol'=>round($unit['volume_cm3'],1),'wt'=>round($unit['weight_kg'],3),'sku'=>$unitProduct['sku']]) : 'null' ?>;
const NEED = <?= (int)($_GET['need'] ?? 0) ?>;
const WEIGHT_ALERT_PCT = 85;
const weightBins = [];

// How full a bin's SPACE is (binding of volume% and weight%).
function fillFrac(b){
    const vp = (parseFloat(b.current_volume_cm3)||0) / Math.max(1, parseFloat(b.max_volume_cm3)||1);
    const wp = (parseFloat(b.current_weight_kg)||0) / Math.max(0.001, parseFloat(b.weight_capacity_kg)||1);
    return Math.min(1, Math.max(vp, wp));
}
// How many boxes of the selected item fit / still fit in this bin.
function capacityFor(b){
    if (!UNIT) return null;
    const maxV = Math.floor(b.max_volume_cm3 / UNIT.vol), maxW = Math.floor(b.weight_capacity_kg / UNIT.wt);
    const maxU = Math.max(0, Math.min(maxV, maxW));
    const remV = Math.floor(Math.max(0, b.max_volume_cm3 - b.current_volume_cm3) / UNIT.vol);
    const remW = Math.floor(Math.max(0, b.weight_capacity_kg - b.current_weight_kg) / UNIT.wt);
    const remU = Math.max(0, Math.min(remV, remW));
    return { maxU, remU, binding: (maxW < maxV ? 'weight' : 'volume') };
}
function colorFor(b){
    if (HIGHLIGHT.has(b.bin_address)) return 0x3b82f6;
    if (UNIT){
        const c = b._cap; if (!c || c.remU <= 0) return 0xef4444;      // no room for this item
        const ratio = c.maxU ? c.remU / c.maxU : 0;
        return ratio > 0.5 ? 0x22c55e : ratio > 0.2 ? 0x84cc16 : 0xf59e0b;
    }
    if (b.occupancy_status === 'empty') return 0x475569;
    const u = parseFloat(b.utilization_percentage)||0;
    if (b.occupancy_status === 'full' || u >= 90) return 0xef4444;
    if (u >= 50) return 0xf59e0b;
    return 0x22c55e;
}

const shellGeo = new THREE.BoxGeometry(BIN, LEVEL*0.9, BIN);
const meshes = [];
let maxX = 0;
BINS.forEach(b => {
    const x = (b.position_number-1) * (BIN + POS_GAP);
    const yCenter = (b.level_number-1) * LEVEL + LEVEL/2;
    const z = aisleZ[b.zone_name+'|'+b.aisle] || 0;
    const binH = LEVEL*0.9, bottom = yCenter - binH/2;
    b._cap = capacityFor(b);
    b._fill = fillFrac(b);

    // translucent shell = full bin envelope
    const shell = new THREE.Mesh(shellGeo, new THREE.MeshLambertMaterial({color:0x94a3b8, transparent:true, opacity:0.10}));
    shell.position.set(x, yCenter, z);
    shell.userData = b;
    scene.add(shell); meshes.push(shell);

    // solid fill scaled to how full the space is (bottom-aligned)
    const fillH = binH * Math.max(0.02, b._fill);
    b._wpct = (parseFloat(b.current_weight_kg)||0) / Math.max(0.001, parseFloat(b.weight_capacity_kg)||1) * 100;
    const fmat = new THREE.MeshLambertMaterial({color: colorFor(b)});
    if (b._wpct >= WEIGHT_ALERT_PCT){ fmat.emissive = new THREE.Color(0xb91c1c); fmat.emissiveIntensity = 0.7; weightBins.push(b); }
    const fill = new THREE.Mesh(new THREE.BoxGeometry(BIN*0.86, fillH, BIN*0.86), fmat);
    fill.position.set(x, bottom + fillH/2, z);
    fill.userData = b;
    scene.add(fill); meshes.push(fill);

    if (HIGHLIGHT.has(b.bin_address)) shell.scale.set(1.18,1.18,1.18);
    maxX = Math.max(maxX, x);
});

// Weight-cap safety alert (heavy bins glow red regardless of space).
if (weightBins.length){
    const el = document.getElementById('weightAlert'); el.classList.remove('hidden');
    el.innerHTML = `⚠ ${weightBins.length} bin(s) at ≥${WEIGHT_ALERT_PCT}% WEIGHT capacity (safety risk): `+
        weightBins.slice(0,8).map(b=>b.bin_address).join(', ') + (weightBins.length>8?'…':'');
}
// Receiving capacity verdict: can the warehouse hold the incoming boxes?
if (UNIT){
    let totalRem = 0; BINS.forEach(b => { const c = b._cap || capacityFor(b); totalRem += c ? c.remU : 0; });
    const v = document.getElementById('capVerdict');
    if (NEED > 0){
        const ok = totalRem >= NEED;
        v.innerHTML = ` Room for <b>${totalRem}</b> more — incoming <b>${NEED}</b> → ` +
            (ok ? '<b class="text-green-700">FITS ✅</b>' : `<b class="text-red-700">SHORT by ${NEED-totalRem} ❌</b>`);
    } else {
        v.innerHTML = ` Total room: <b>${totalRem}</b> more boxes. Enter incoming boxes to check a receipt.`;
    }
}

// floor
const floor = new THREE.Mesh(new THREE.PlaneGeometry(maxX+20, totalZ+20),
    new THREE.MeshBasicMaterial({color:0x1e293b}));
floor.rotation.x = -Math.PI/2; floor.position.set(maxX/2, 0, totalZ/2-GAP_ZONE/2);
scene.add(floor);

// center the view
controls.target.set(maxX/2, 6, totalZ/2);
camera.position.set(maxX/2 + 40, 45, totalZ + 30);

// zone labels via sprites
zoneNames.forEach(zn => {
    const aisles = Array.from(zones[zn].aisles).sort();
    const zMid = (aisleZ[zn+'|'+aisles[0]] + aisleZ[zn+'|'+aisles[aisles.length-1]]) / 2;
    const cvs = document.createElement('canvas'); cvs.width=256; cvs.height=64;
    const ctx = cvs.getContext('2d'); ctx.fillStyle='#e2e8f0'; ctx.font='bold 28px sans-serif';
    ctx.fillText(zn, 8, 40);
    const tex = new THREE.CanvasTexture(cvs);
    const spr = new THREE.Sprite(new THREE.SpriteMaterial({map:tex, transparent:true}));
    spr.position.set(-6, 10, zMid); spr.scale.set(16,4,1); scene.add(spr);
});

// --- Interaction: click a bin -> open detail modal for a spot count ---
const ray = new THREE.Raycaster(), mouse = new THREE.Vector2();
function openBin(b){
    const items = CONTENTS[b.bin_address] || [];
    const spacePct = Math.round((b._fill||0)*100);
    document.getElementById('mTitle').textContent = b.bin_address;
    const wpct = Math.round(b._wpct||0);
    document.getElementById('mMeta').innerHTML =
        `${b.zone_name} · aisle ${b.aisle} · L${b.level_number} P${b.position_number} · space ${spacePct}% · weight ${wpct}%`+
        (wpct>=WEIGHT_ALERT_PCT ? ' <span class="text-red-600 font-semibold">⚠ near weight cap</span>' : '');
    document.getElementById('mBody').innerHTML = items.length
        ? '<div class="font-semibold text-gray-700 mb-1">System says this bin holds:</div>'+
          '<table class="w-full"><tr class="text-gray-400 text-left"><th>SKU</th><th>Item</th><th class="text-right">Qty</th></tr>'+
          items.map(i=>`<tr class="border-t"><td class="font-mono">${i.sku||''}</td><td>${i.name}</td><td class="text-right font-semibold">${i.qty}</td></tr>`).join('')+
          '</table>'
        : '<div class="text-gray-400">System shows this bin as empty.</div>';
    let cap = '';
    if (UNIT){ const c = b._cap || capacityFor(b);
        cap = `<div class="p-2 bg-indigo-50 rounded text-indigo-900">Holds <b>${c.maxU}</b> × ${UNIT.sku} (${c.binding}-limited) · room for <b>${c.remU}</b> more</div>`; }
    document.getElementById('mCap').innerHTML = cap;
    document.getElementById('mCount').href = 'scan.php?mode=count&bin=' + encodeURIComponent(b.bin_address);
    const m = document.getElementById('binModal'); m.classList.remove('hidden'); m.classList.add('flex');
}
function closeBin(){ const m=document.getElementById('binModal'); m.classList.add('hidden'); m.classList.remove('flex'); }

renderer.domElement.addEventListener('click', (e) => {
    const r = renderer.domElement.getBoundingClientRect();
    mouse.x = ((e.clientX-r.left)/r.width)*2-1;
    mouse.y = -((e.clientY-r.top)/r.height)*2+1;
    ray.setFromCamera(mouse, camera);
    const hit = ray.intersectObjects(meshes)[0];
    if (hit) openBin(hit.object.userData);
});

// Risk-weighted smart pick: prioritise high-value, long-uncounted bins (ABC
// cycle counting). Never-counted bins get the highest staleness weight.
function riskScore(b){
    const value = parseFloat(b.bin_value)||0;
    const days = (b.days_since_count===null || b.days_since_count===undefined) ? 400 : (parseInt(b.days_since_count)+1);
    return value * days;
}
function randomBin(){
    const occupied = BINS.filter(b => (CONTENTS[b.bin_address]||[]).length > 0);
    if (!occupied.length) return;
    // rank by risk, then pick randomly among the top 8 (smart-random)
    occupied.sort((a,c)=>riskScore(c)-riskScore(a));
    const pool = occupied.slice(0, Math.min(8, occupied.length));
    const b = pool[Math.floor(Math.random()*pool.length)];
    const x = (b.position_number-1)*(BIN+POS_GAP);
    const y = (b.level_number-1)*LEVEL + LEVEL/2;
    const z = aisleZ[b.zone_name+'|'+b.aisle] || 0;
    controls.target.set(x, y, z);
    camera.position.set(x+8, y+6, z+12);
    openBin(b);
}

window.addEventListener('resize', () => {
    camera.aspect = host.clientWidth/host.clientHeight; camera.updateProjectionMatrix();
    renderer.setSize(host.clientWidth, host.clientHeight);
});

(function animate(){ requestAnimationFrame(animate); controls.update(); renderer.render(scene, camera); })();
</script>
</body>
</html>
