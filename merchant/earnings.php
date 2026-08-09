<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/SupplierCredibilityService.php';

requireRole('merchant');
$mid = (int) $_SESSION['user_id'];

// Lifetime + pending figures from commission lines.
$sum = $pdo->prepare("
    SELECT
        COALESCE(SUM(gross_amount),0) gross,
        COALESCE(SUM(commission_amount),0) commission,
        COALESCE(SUM(net_amount),0) net,
        COALESCE(SUM(CASE WHEN status <> 'paid_out' THEN net_amount ELSE 0 END),0) pending,
        COALESCE(SUM(CASE WHEN status = 'paid_out' THEN net_amount ELSE 0 END),0) paid,
        COUNT(*) sale_count
    FROM merchant_commissions WHERE merchant_id = ?
");
$sum->execute([$mid]);
$s = $sum->fetch(PDO::FETCH_ASSOC);

// This month vs last month net.
$mo = $pdo->prepare("
    SELECT DATE_FORMAT(created_at,'%Y-%m') ym, SUM(net_amount) net, COUNT(*) n
    FROM merchant_commissions WHERE merchant_id = ?
    GROUP BY ym ORDER BY ym DESC LIMIT 6
");
$mo->execute([$mid]);
$months = $mo->fetchAll(PDO::FETCH_ASSOC);

// Recent commission lines (per order).
$lines = $pdo->prepare("
    SELECT mc.*, o.created_at oc
    FROM merchant_commissions mc JOIN orders o ON mc.order_id = o.id
    WHERE mc.merchant_id = ? ORDER BY mc.id DESC LIMIT 25
");
$lines->execute([$mid]);
$recent = $lines->fetchAll(PDO::FETCH_ASSOC);

// Payout history.
$po = $pdo->prepare("SELECT * FROM merchant_payouts WHERE merchant_id = ? ORDER BY id DESC LIMIT 12");
$po->execute([$mid]);
$payouts = $po->fetchAll(PDO::FETCH_ASSOC);

// Credibility tier + fees (what the seller pays).
$cred = $pdo->prepare("SELECT score, tier, subscription_fee, insurance_rate FROM merchant_credibility WHERE merchant_id = ?");
$cred->execute([$mid]);
$tier = $cred->fetch(PDO::FETCH_ASSOC);

$m = fn($n) => '$' . number_format((float) $n, 2);
$tierColor = ['A'=>'bg-green-100 text-green-800','B'=>'bg-blue-100 text-blue-800','C'=>'bg-amber-100 text-amber-800','D'=>'bg-red-100 text-red-800'];
$maxNet = max(array_map(fn($x) => (float) $x['net'], $months ?: [['net' => 1]]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings - VentDepot Seller</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<nav class="bg-gray-900 text-white">
    <div class="max-w-6xl mx-auto px-4 h-12 flex items-center gap-4 text-sm">
        <a href="dashboard.php" class="font-bold">VentDepot <span class="text-blue-400">Seller</span></a>
        <a href="dashboard.php" class="hover:text-blue-300">Dashboard</a>
        <a href="products.php" class="hover:text-blue-300">Products</a>
        <a href="orders.php" class="hover:text-blue-300">Orders</a>
        <a href="earnings.php" class="text-blue-300 font-medium">Earnings</a>
        <a href="store-builder.php" class="hover:text-blue-300">My Store</a>
        <a href="../logout.php" class="ml-auto hover:text-blue-300">Logout</a>
    </div>
</nav>
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">My Earnings</h1>
    <p class="text-sm text-gray-500 mb-6">You earn the sale amount minus VentDepot's commission. Balances are paid out by VentDepot on a rolling basis.</p>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Gross sales</div><div class="text-xl font-bold"><?= $m($s['gross']) ?></div><div class="text-xs text-gray-400"><?= (int)$s['sale_count'] ?> sale(s)</div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Commission</div><div class="text-xl font-bold text-gray-500">−<?= $m($s['commission']) ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Net earned</div><div class="text-xl font-bold text-green-700"><?= $m($s['net']) ?></div></div>
        <div class="bg-white rounded-lg shadow p-4 ring-2 ring-blue-200"><div class="text-xs uppercase text-gray-400">Pending balance</div><div class="text-xl font-bold text-blue-700"><?= $m($s['pending']) ?></div><div class="text-xs text-gray-400">next payout</div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Paid out</div><div class="text-xl font-bold"><?= $m($s['paid']) ?></div></div>
    </div>

    <div class="grid md:grid-cols-2 gap-6 mb-8">
        <!-- Monthly net chart -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-4">Net earnings by month</h2>
            <?php if (empty($months)): ?><p class="text-gray-400 text-sm">No sales yet.</p><?php else: ?>
            <div class="flex items-end gap-3 h-36">
                <?php foreach (array_reverse($months) as $mm): $h = $maxNet > 0 ? max(4, round($mm['net'] / $maxNet * 100)) : 4; ?>
                    <div class="flex-1 flex flex-col items-center justify-end h-full">
                        <div class="text-xs text-gray-600 mb-1"><?= $m($mm['net']) ?></div>
                        <div class="w-full bg-blue-500 rounded-t" style="height:<?= $h ?>%"></div>
                        <div class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($mm['ym']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- My tier & fees -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-4">My seller standing</h2>
            <?php if ($tier): ?>
                <div class="flex items-center gap-3 mb-3">
                    <span class="px-3 py-1 rounded-full font-bold <?= $tierColor[$tier['tier']] ?? 'bg-gray-100' ?>">Tier <?= htmlspecialchars($tier['tier']) ?></span>
                    <span class="text-sm text-gray-500">score <?= (int)$tier['score'] ?>/100</span>
                </div>
                <table class="w-full text-sm">
                    <tr class="border-b"><td class="py-2 text-gray-600">Monthly subscription</td><td class="text-right font-medium"><?= $m($tier['subscription_fee']) ?></td></tr>
                    <tr class="border-b"><td class="py-2 text-gray-600">Insurance rate</td><td class="text-right font-medium"><?= number_format($tier['insurance_rate'], 3) ?>%</td></tr>
                </table>
                <p class="text-xs text-gray-400 mt-3">Better ratings and fewer refunds move you up a tier — higher tiers pay lower fees.</p>
            <?php else: ?><p class="text-gray-400 text-sm">Not scored yet — your tier appears after your first sales.</p><?php endif; ?>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Recent sales -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-5 py-3 border-b font-semibold text-gray-800">Recent sales</div>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 bg-gray-50 border-b"><th class="px-4 py-2">Order</th><th class="text-right">Gross</th><th class="text-right">Commission</th><th class="text-right">Net</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if (empty($recent)): ?><tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No sales yet.</td></tr><?php endif; ?>
                    <?php foreach ($recent as $r): ?>
                    <tr class="border-b">
                        <td class="px-4 py-2">#VD-<?= str_pad($r['order_id'], 6, '0', STR_PAD_LEFT) ?><div class="text-xs text-gray-400"><?= date('M j', strtotime($r['oc'])) ?></div></td>
                        <td class="text-right"><?= $m($r['gross_amount']) ?></td>
                        <td class="text-right text-gray-500">−<?= $m($r['commission_amount']) ?> <span class="text-xs">(<?= rtrim(rtrim(number_format($r['commission_rate'] * 100, 1), '0'), '.') ?>%)</span></td>
                        <td class="text-right font-medium text-green-700"><?= $m($r['net_amount']) ?></td>
                        <td><span class="px-2 py-0.5 rounded-full text-xs <?= $r['status'] === 'paid_out' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>"><?= $r['status'] === 'paid_out' ? 'paid' : 'pending' ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Payout history -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-5 py-3 border-b font-semibold text-gray-800">Payout history</div>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 bg-gray-50 border-b"><th class="px-4 py-2">Reference</th><th class="text-right">Net paid</th><th class="text-right">Orders</th><th>Date</th></tr></thead>
                <tbody>
                    <?php if (empty($payouts)): ?><tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No payouts yet — your pending balance is paid on the next cycle.</td></tr><?php endif; ?>
                    <?php foreach ($payouts as $p): ?>
                    <tr class="border-b">
                        <td class="px-4 py-2 font-mono text-xs"><?= htmlspecialchars($p['reference']) ?></td>
                        <td class="text-right font-medium"><?= $m($p['net_amount']) ?></td>
                        <td class="text-right"><?= (int)$p['order_count'] ?></td>
                        <td class="text-gray-500"><?= $p['paid_at'] ? date('M j, Y', strtotime($p['paid_at'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
