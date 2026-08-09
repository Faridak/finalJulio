<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/SupplierScorecardService.php';

requireRole('admin');
requireScope('purchasing');

$svc = new SupplierScorecardService($pdo);
$rows = $svc->scoreAll();   // recomputes + persists delivery_rating on every view

// Suppliers with no received POs yet (context, unscored).
$unscored = $pdo->query("
    SELECT s.id, s.company_name, s.lead_time_days,
           (SELECT COUNT(*) FROM purchase_orders po WHERE po.supplier_id = s.id) AS pos_total
    FROM suppliers s
    WHERE s.status='active' AND s.id NOT IN (
        SELECT DISTINCT supplier_id FROM purchase_orders
        WHERE actual_delivery_date IS NOT NULL AND expected_delivery_date IS NOT NULL)
    ORDER BY s.company_name
")->fetchAll(PDO::FETCH_ASSOC);

function stars($r) { $full = (int) round($r); return str_repeat('★', $full) . str_repeat('☆', 5 - $full); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Scorecard - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">Supplier Scorecard</h1>
        <a href="po-manage.php" class="text-blue-600 hover:underline text-sm">Purchase orders →</a>
    </div>
    <p class="text-sm text-gray-500 mb-6">Delivery performance measured on received POs: promised (expected delivery) vs actual. The rating feeds <code>suppliers.delivery_rating</code> — recomputed live on this page.</p>

    <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b">
                <th class="px-4 py-2">Supplier</th><th class="text-right">POs received</th>
                <th class="text-right">On-time</th><th class="text-right">Avg delay</th>
                <th>Last delivery</th><th class="text-right px-4">Rating</th>
            </tr></thead>
            <tbody>
                <?php if (empty($rows)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No received POs with delivery dates yet — receive a PO and the scorecard fills in.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr class="border-b">
                    <td class="px-4 py-3 font-medium"><?= htmlspecialchars($r['company_name']) ?></td>
                    <td class="text-right"><?= (int)$r['pos_received'] ?></td>
                    <td class="text-right">
                        <span class="<?= $r['on_time_pct'] >= 90 ? 'text-green-700' : ($r['on_time_pct'] >= 70 ? 'text-amber-700' : 'text-red-700') ?> font-medium"><?= $r['on_time_pct'] ?>%</span>
                    </td>
                    <td class="text-right <?= $r['avg_delay_days'] > 3 ? 'text-red-600' : 'text-gray-500' ?>"><?= $r['avg_delay_days'] ?>d</td>
                    <td class="text-gray-500"><?= htmlspecialchars($r['last_delivery'] ?? '—') ?></td>
                    <td class="text-right px-4"><span class="text-amber-500"><?= stars($r['delivery_rating']) ?></span> <span class="text-gray-500"><?= number_format($r['delivery_rating'], 2) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($unscored): ?>
    <h2 class="font-semibold text-gray-800 mb-2">Not yet scored</h2>
    <div class="bg-white rounded-lg shadow p-4 text-sm text-gray-600">
        <?php foreach ($unscored as $u): ?>
            <div class="flex justify-between border-b last:border-0 py-1.5">
                <span><?= htmlspecialchars($u['company_name']) ?></span>
                <span class="text-gray-400"><?= (int)$u['pos_total'] ?> PO(s), none received with dates · promised lead <?= (int)$u['lead_time_days'] ?>d</span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
