<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/PurchaseOrderService.php';

requireRole('admin');
requireScope('purchasing');

$suppliers = $pdo->query("SELECT id, supplier_code, company_name FROM suppliers WHERE status='active' ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
$locations = $pdo->query("SELECT id, location_name FROM inventory_locations WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

$supplierId = (int) ($_GET['supplier'] ?? ($_POST['supplier_id'] ?? 0));
$locationId = (int) ($_POST['location_id'] ?? ($locations[0]['id'] ?? 1));
$result = null; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('CSRF mismatch'); }
    $lines = [];
    foreach ($_POST['qty'] ?? [] as $spId => $qty) {
        if ((int) $qty > 0) $lines[] = ['supplier_product_id' => (int) $spId, 'qty' => (int) $qty];
    }
    if (empty($lines)) $error = 'Enter a quantity for at least one item.';
    else {
        $result = (new PurchaseOrderService($pdo))->create($supplierId, $locationId, $lines, $_SESSION['user_id']);
        if (!$result['success']) $error = $result['error'];
    }
}

$catalog = [];
if ($supplierId) {
    $st = $pdo->prepare("
        SELECT sp.id, sp.supplier_sku, sp.product_name, sp.cost_price, sp.available_quantity,
               pr.sku AS our_sku
        FROM supplier_products sp
        LEFT JOIN products pr ON sp.product_id = pr.id
        WHERE sp.supplier_id = ? AND sp.status='active'
        ORDER BY sp.product_name LIMIT 300
    ");
    $st->execute([$supplierId]);
    $catalog = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Purchase Order - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-4xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Create Purchase Order</h1>
    <p class="text-sm text-gray-500 mb-5">Order using the <b>supplier's SKU</b>; goods map to our products on receipt.</p>

    <?php if ($result && $result['success']): ?>
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-5">
            ✅ Created <b><?= htmlspecialchars($result['po_number']) ?></b> — total $<?= number_format($result['total'], 2) ?>.
            Receive it from the inventory receiving screen when it arrives.
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-5"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="GET" class="flex gap-2 mb-5">
        <select name="supplier" onchange="this.form.submit()" class="px-3 py-2 border rounded">
            <option value="0">— select supplier —</option>
            <?php foreach ($suppliers as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= $s['id']==$supplierId?'selected':'' ?>>
                    <?= htmlspecialchars($s['company_name'].' ('.$s['supplier_code'].')') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($supplierId && $catalog): ?>
    <form method="POST" class="bg-white rounded-lg shadow p-4">
        <?= Security::getCSRFInput() ?>
        <input type="hidden" name="supplier_id" value="<?= $supplierId ?>">
        <div class="flex items-center gap-2 mb-3 flex-wrap">
            <label class="text-sm text-gray-600">Deliver to</label>
            <select name="location_id" class="px-3 py-2 border rounded text-sm">
                <?php foreach ($locations as $l): ?>
                    <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['location_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <span class="text-gray-300">|</span>
            <label class="text-sm text-gray-600">Scan / type item</label>
            <input id="scanItem" placeholder="barcode, our SKU, or supplier SKU + Enter"
                   class="px-3 py-2 border rounded text-sm font-mono w-72">
            <span id="scanMsg" class="text-xs"></span>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b">
                    <th class="py-2">Supplier SKU</th>
                    <th class="py-2">Item</th>
                    <th class="py-2">Maps to (our SKU)</th>
                    <th class="py-2 text-right">Cost</th>
                    <th class="py-2 text-right">Avail</th>
                    <th class="py-2 text-right">Order qty</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($catalog as $c): ?>
                <tr class="border-b">
                    <td class="py-2 font-mono font-semibold"><?= htmlspecialchars($c['supplier_sku']) ?></td>
                    <td class="py-2"><?= htmlspecialchars($c['product_name']) ?></td>
                    <td class="py-2 font-mono text-gray-500"><?= htmlspecialchars($c['our_sku'] ?? '—') ?></td>
                    <td class="py-2 text-right">$<?= number_format((float)$c['cost_price'], 2) ?></td>
                    <td class="py-2 text-right text-gray-500"><?= (int)$c['available_quantity'] ?></td>
                    <td class="py-2 text-right">
                        <input type="number" name="qty[<?= (int)$c['id'] ?>]" min="0" value="0" class="w-20 px-2 py-1 border rounded text-right">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button class="mt-4 bg-blue-600 text-white px-6 py-2 rounded">Create Purchase Order</button>
    </form>
    <?php elseif ($supplierId): ?>
        <p class="text-gray-400">This supplier has no catalog items.</p>
    <?php endif; ?>
</div>

<?php if ($supplierId && $catalog): ?>
<script>
const SUPPLIER_ID = <?= (int)$supplierId ?>;
const scan = document.getElementById('scanItem');
const msg = document.getElementById('scanMsg');
scan && scan.addEventListener('keydown', async (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const code = scan.value.trim(); if (!code) return;
    scan.value = '';
    const res = await fetch(`api/po-item-lookup.php?code=${encodeURIComponent(code)}&supplier_id=${SUPPLIER_ID}`);
    const d = await res.json();
    if (!d.found || !d.supplier_product_id) {
        msg.textContent = d.found ? '⚠ found but not in this supplier catalog' : '✗ not found';
        msg.className = 'text-xs text-red-600';
        return;
    }
    // bump the matching row's quantity + highlight it
    const input = document.querySelector(`input[name="qty[${d.supplier_product_id}]"]`);
    if (input) {
        input.value = (parseInt(input.value)||0) + 1;
        const row = input.closest('tr');
        row.classList.add('bg-yellow-50');
        row.scrollIntoView({behavior:'smooth', block:'center'});
        setTimeout(()=>row.classList.remove('bg-yellow-50'), 1500);
        msg.textContent = `✓ ${d.supplier_sku} — ${d.product_name} (qty ${input.value})`;
        msg.className = 'text-xs text-green-700';
    }
    scan.focus();
});
</script>
<?php endif; ?>
</body>
</html>
