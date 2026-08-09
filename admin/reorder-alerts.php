<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/ReorderService.php';

requireRole('admin');
requireScope('purchasing');

$locations = $pdo->query("SELECT id, location_name FROM inventory_locations WHERE status='active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$locationId = (int) ($_GET['loc'] ?? ($locations[0]['id'] ?? 1));
$alerts = (new ReorderService($pdo))->alerts($locationId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reorder Alerts - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">Reorder Alerts</h1>
        <form method="GET">
            <select name="loc" onchange="this.form.submit()" class="px-3 py-2 border rounded text-sm">
                <?php foreach ($locations as $l): ?>
                    <option value="<?= (int)$l['id'] ?>" <?= $l['id']==$locationId?'selected':'' ?>><?= htmlspecialchars($l['location_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <p class="text-sm text-gray-500 mb-5">Reorder point = daily demand × (supplier lead time + customs) + safety stock. Flagged when on-hand + on-order ≤ reorder point.</p>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 bg-gray-50 border-b">
                    <th class="px-4 py-3">SKU</th><th>Item</th><th>Supplier</th>
                    <th class="text-right px-2">On hand</th><th class="text-right px-2">On order</th>
                    <th class="text-right px-2">Reorder pt</th><th class="text-right px-2">Days cover</th>
                    <th class="text-right px-2">Suggest</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($alerts)): ?>
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">All stocked items are healthy — nothing to reorder. ✅<br><span class="text-xs">(Alerts need outbound sales history to compute demand.)</span></td></tr>
                <?php endif; ?>
                <?php foreach ($alerts as $a): ?>
                <tr class="border-b <?= $a['stockout_risk'] ? 'bg-red-50' : '' ?>">
                    <td class="px-4 py-2 font-mono"><?= htmlspecialchars($a['sku']) ?></td>
                    <td><?= htmlspecialchars($a['name']) ?></td>
                    <td class="text-gray-600"><?= htmlspecialchars($a['supplier']) ?></td>
                    <td class="text-right px-2"><?= $a['on_hand'] ?></td>
                    <td class="text-right px-2 text-blue-600"><?= $a['on_order'] ?></td>
                    <td class="text-right px-2"><?= $a['reorder_point'] ?></td>
                    <td class="text-right px-2 <?= $a['stockout_risk']?'text-red-700 font-bold':'' ?>">
                        <?= $a['days_cover'] ?>d<?= $a['stockout_risk'] ? ' ⚠' : '' ?>
                    </td>
                    <td class="text-right px-2 font-semibold"><?= $a['suggest_qty'] ?></td>
                    <td class="text-right px-3">
                        <?php if ($a['supplier_id']): ?>
                            <a href="create-po.php?supplier=<?= (int)$a['supplier_id'] ?>" class="bg-blue-600 text-white text-xs px-3 py-1 rounded">Create PO</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-3">⚠ = will run out before a replenishment could arrive (days cover &lt; lead + customs time).</p>
</div>
</body>
</html>
