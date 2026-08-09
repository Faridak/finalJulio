<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/StockReplenishmentService.php';

requireRole('admin');
requireScope('warehouse');

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        die('CSRF token mismatch. Please refresh and try again.');
    }

    $service = new StockReplenishmentService($pdo);
    $result = $service->receiveStock([
        'product_id'   => (int) ($_POST['product_id'] ?? 0),
        'supplier_id'  => (int) ($_POST['supplier_id'] ?? 0),
        'location_id'  => (int) ($_POST['location_id'] ?? 0),
        'quantity'     => (int) ($_POST['quantity'] ?? 0),
        'unit_cost'    => (float) ($_POST['unit_cost'] ?? 0),
        'batch_number' => Security::sanitizeInput($_POST['batch_number'] ?? '', 'string') ?: null,
        'expiry_date'  => $_POST['expiry_date'] ?? null,
        'user_id'      => $_SESSION['user_id'],
    ]);

    if ($result['success']) {
        $notice = 'Stock received. Inventory, lot/expiry, and accounts payable updated.';
    } else {
        $error = $result['error'];
    }
}

$suppliers = $pdo->query("SELECT id, company_name FROM suppliers WHERE status = 'active' ORDER BY company_name")->fetchAll(PDO::FETCH_ASSOC);
$locations = $pdo->query("SELECT id, location_name FROM inventory_locations WHERE status = 'active' ORDER BY location_name")->fetchAll(PDO::FETCH_ASSOC);
$products  = $pdo->query("SELECT id, name FROM products ORDER BY name LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);

$recent = $pdo->query("
    SELECT b.batch_number, b.expiry_date, b.quantity_received, b.unit_cost, b.received_at,
           p.name AS product_name, s.company_name AS supplier
    FROM inventory_batches b
    LEFT JOIN products p ON b.product_id = p.id
    LEFT JOIN suppliers s ON b.supplier_id = s.id
    ORDER BY b.received_at DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Stock - VentDepot Admin</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
    <div class="max-w-4xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Receive Stock (Supplier Refill)</h1>
            <a href="inventory-batches.php" class="text-blue-600 hover:underline text-sm">View expiry &amp; inventory &rarr;</a>
        </div>

        <?php if ($notice): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($notice) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="bg-white rounded-lg shadow p-6 grid grid-cols-2 gap-4">
            <?= Security::getCSRFInput() ?>

            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Supplier</label>
                <select name="supplier_id" required class="w-full px-3 py-2 border rounded">
                    <option value="">— Select supplier —</option>
                    <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">Product</label>
                <select name="product_id" required class="w-full px-3 py-2 border rounded">
                    <option value="">— Select product —</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Warehouse / Location</label>
                <select name="location_id" required class="w-full px-3 py-2 border rounded">
                    <?php foreach ($locations as $l): ?>
                        <option value="<?= (int) $l['id'] ?>"><?= htmlspecialchars($l['location_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Quantity</label>
                <input type="number" name="quantity" min="1" required class="w-full px-3 py-2 border rounded">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Unit cost ($)</label>
                <input type="number" name="unit_cost" step="0.01" min="0" required class="w-full px-3 py-2 border rounded">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Batch / Lot number</label>
                <input type="text" name="batch_number" class="w-full px-3 py-2 border rounded" placeholder="optional">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Expiry date</label>
                <input type="date" name="expiry_date" class="w-full px-3 py-2 border rounded">
            </div>

            <div class="col-span-2 mt-2">
                <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                    Receive into Inventory
                </button>
            </div>
        </form>

        <h2 class="text-lg font-semibold text-gray-800 mt-10 mb-3">Recent receipts</h2>
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 bg-gray-50 border-b">
                        <th class="px-4 py-2">Product</th>
                        <th class="px-4 py-2">Supplier</th>
                        <th class="px-4 py-2">Batch</th>
                        <th class="px-4 py-2">Expiry</th>
                        <th class="px-4 py-2">Qty</th>
                        <th class="px-4 py-2">Unit cost</th>
                        <th class="px-4 py-2">Received</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent)): ?>
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No receipts yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent as $r): ?>
                    <tr class="border-b">
                        <td class="px-4 py-2"><?= htmlspecialchars($r['product_name'] ?? '—') ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($r['supplier'] ?? '—') ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($r['batch_number'] ?? '—') ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars($r['expiry_date'] ?? '—') ?></td>
                        <td class="px-4 py-2"><?= (int) $r['quantity_received'] ?></td>
                        <td class="px-4 py-2">$<?= number_format((float) $r['unit_cost'], 2) ?></td>
                        <td class="px-4 py-2"><?= htmlspecialchars(date('M j, Y', strtotime($r['received_at']))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
