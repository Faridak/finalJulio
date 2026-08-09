<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole('admin');
requireScope('warehouse');

// Optional filter: show only near-expiry / expired lots.
$filter = $_GET['filter'] ?? 'all';

$where = "b.quantity_remaining > 0";
if ($filter === 'expiring') {
    $where .= " AND b.expiry_date IS NOT NULL AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($filter === 'expired') {
    $where .= " AND b.expiry_date IS NOT NULL AND b.expiry_date < CURDATE()";
}

// Table of expiry + product description + inventory, sourced from tracked lots.
$rows = $pdo->query("
    SELECT b.id, b.batch_number, b.expiry_date, b.quantity_received, b.quantity_remaining,
           b.unit_cost, b.received_at,
           p.name AS product_name, p.description AS product_description,
           s.company_name AS supplier, l.location_name,
           COALESCE(pi.quantity_on_hand, 0) AS on_hand,
           COALESCE(pi.quantity_available, 0) AS available
    FROM inventory_batches b
    LEFT JOIN products p ON b.product_id = p.id
    LEFT JOIN suppliers s ON b.supplier_id = s.id
    LEFT JOIN inventory_locations l ON b.location_id = l.id
    LEFT JOIN product_inventory pi ON pi.product_id = b.product_id AND pi.location_id = b.location_id
    WHERE $where
    ORDER BY (b.expiry_date IS NULL), b.expiry_date ASC, b.received_at DESC
    LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

function expiryClass($date) {
    if (!$date) return ['—', 'text-gray-400'];
    $days = (int) floor((strtotime($date) - time()) / 86400);
    if ($days < 0)   return [$date . ' (expired)', 'text-red-700 font-semibold'];
    if ($days <= 30) return [$date . " ($days d)", 'text-amber-600 font-medium'];
    return [$date, 'text-gray-700'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory &amp; Expiry - VentDepot Admin</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
    <div class="max-w-7xl mx-auto px-4 py-10">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Inventory &amp; Expiry</h1>
            <a href="receive-stock.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm">+ Receive Stock</a>
        </div>

        <div class="mb-4 flex gap-2 text-sm">
            <?php foreach (['all' => 'All lots', 'expiring' => 'Expiring ≤30d', 'expired' => 'Expired'] as $key => $label): ?>
                <a href="?filter=<?= $key ?>"
                   class="px-3 py-1 rounded-full <?= $filter === $key ? 'bg-blue-600 text-white' : 'bg-white border text-gray-600' ?>">
                    <?= $label ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 bg-gray-50 border-b">
                        <th class="px-4 py-3">Product / Description</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Location</th>
                        <th class="px-4 py-3">Batch</th>
                        <th class="px-4 py-3">Expiry</th>
                        <th class="px-4 py-3">Lot remaining</th>
                        <th class="px-4 py-3">On hand</th>
                        <th class="px-4 py-3">Available</th>
                        <th class="px-4 py-3">Unit cost</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="px-4 py-6 text-center text-gray-400">No stock lots to show.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <?php [$expLabel, $expCls] = expiryClass($r['expiry_date']); ?>
                        <tr class="border-b align-top">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($r['product_name'] ?? '—') ?></div>
                                <div class="text-gray-500 text-xs max-w-md truncate">
                                    <?= htmlspecialchars(mb_strimwidth((string) ($r['product_description'] ?? ''), 0, 90, '…')) ?>
                                </div>
                            </td>
                            <td class="px-4 py-3"><?= htmlspecialchars($r['supplier'] ?? '—') ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($r['location_name'] ?? '—') ?></td>
                            <td class="px-4 py-3"><?= htmlspecialchars($r['batch_number'] ?? '—') ?></td>
                            <td class="px-4 py-3 <?= $expCls ?>"><?= htmlspecialchars($expLabel) ?></td>
                            <td class="px-4 py-3"><?= (int) $r['quantity_remaining'] ?> / <?= (int) $r['quantity_received'] ?></td>
                            <td class="px-4 py-3"><?= (int) $r['on_hand'] ?></td>
                            <td class="px-4 py-3"><?= (int) $r['available'] ?></td>
                            <td class="px-4 py-3">$<?= number_format((float) $r['unit_cost'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
