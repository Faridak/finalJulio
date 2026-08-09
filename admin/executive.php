<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole('admin');   // full admins only — the whole-business view

$m = fn($n) => '$' . number_format((float) $n, 2);
$scalar = function ($sql) use ($pdo) { return $pdo->query($sql)->fetchColumn() ?: 0; };

// Sales windows (paid orders)
$sales = fn($days) => $pdo->query("
    SELECT COALESCE(SUM(total),0) s, COUNT(*) n FROM orders
    WHERE payment_status='paid' AND created_at >= NOW() - INTERVAL $days DAY")->fetch(PDO::FETCH_ASSOC);
$today = $pdo->query("SELECT COALESCE(SUM(total),0) s, COUNT(*) n FROM orders WHERE payment_status='paid' AND DATE(created_at)=CURDATE()")->fetch(PDO::FETCH_ASSOC);
$d7 = $sales(7); $d30 = $sales(30);

// GMV split: own vs marketplace (30d)
$split = $pdo->query("
    SELECT COALESCE(p.ownership,'own') own, COALESCE(SUM(oi.total),0) s
    FROM order_items oi JOIN orders o ON oi.order_id=o.id AND o.payment_status='paid' AND o.created_at >= NOW() - INTERVAL 30 DAY
    LEFT JOIN products p ON oi.product_id=p.id GROUP BY 1")->fetchAll(PDO::FETCH_KEY_PAIR);

// Money position
$cash = $scalar("SELECT COALESCE(SUM(debit_amount)-SUM(credit_amount),0) FROM general_ledger gl JOIN chart_of_accounts ca ON gl.account_id=ca.id WHERE ca.account_code='1000'");
$ar   = $scalar("SELECT COALESCE(SUM(debit_amount)-SUM(credit_amount),0) FROM general_ledger gl JOIN chart_of_accounts ca ON gl.account_id=ca.id WHERE ca.account_code='1100'");
$merchantOwed = $scalar("SELECT COALESCE(SUM(net_amount),0) FROM merchant_commissions WHERE status='pending_payout'");

// Ops alerts
$lowStock = $scalar("SELECT COUNT(*) FROM products WHERE inventory <= 3 AND inventory > 0");
$outStock = $scalar("SELECT COUNT(*) FROM products WHERE inventory = 0");
$pendingOxxo = $scalar("SELECT COUNT(*) FROM oxxo_payments WHERE status='pending'");
$openTickets = $scalar("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open','pending')");
$pendingPOs = $scalar("SELECT COUNT(*) FROM purchase_orders WHERE approval_status='pending'");
$failedEmails = $scalar("SELECT COUNT(*) FROM email_queue WHERE status='failed'");

// Top products (30d)
$top = $pdo->query("
    SELECT p.name, SUM(oi.quantity) qty, SUM(oi.total) rev
    FROM order_items oi JOIN orders o ON oi.order_id=o.id AND o.payment_status='paid' AND o.created_at >= NOW() - INTERVAL 30 DAY
    JOIN products p ON oi.product_id=p.id
    GROUP BY p.id, p.name ORDER BY rev DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

// 14-day sales sparkline
$daily = $pdo->query("
    SELECT DATE(created_at) d, COALESCE(SUM(total),0) s FROM orders
    WHERE payment_status='paid' AND created_at >= NOW() - INTERVAL 14 DAY
    GROUP BY DATE(created_at) ORDER BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
$maxDay = max(1, max($daily ?: [1]));

$alerts = array_filter([
    $failedEmails ? "✉️ $failedEmails failed email(s) in the queue" : null,
    $pendingPOs ? "📦 $pendingPOs purchase order(s) awaiting approval" : null,
    $pendingOxxo ? "💵 $pendingOxxo OXXO voucher(s) awaiting cash" : null,
    $openTickets ? "🎫 $openTickets open customer ticket(s)" : null,
    $outStock > 50 ? "⚠️ $outStock products out of stock" : null,
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Executive Overview</h1>
    <p class="text-sm text-gray-500 mb-6">The 7am view — sales, money position, and what needs attention. Live from the ledger and orders.</p>

    <?php if ($alerts): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6 text-sm text-amber-900 space-y-1">
        <?php foreach ($alerts as $a): ?><div><?= $a ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Sales -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Today</div>
            <div class="text-2xl font-bold text-gray-900"><?= $m($today['s']) ?></div><div class="text-xs text-gray-400"><?= (int)$today['n'] ?> order(s)</div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">7 days</div>
            <div class="text-2xl font-bold text-gray-900"><?= $m($d7['s']) ?></div><div class="text-xs text-gray-400"><?= (int)$d7['n'] ?> order(s)</div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">30 days</div>
            <div class="text-2xl font-bold text-gray-900"><?= $m($d30['s']) ?></div><div class="text-xs text-gray-400"><?= (int)$d30['n'] ?> order(s)</div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">GMV split (30d)</div>
            <div class="text-sm mt-1">Own: <b><?= $m($split['own'] ?? 0) ?></b></div>
            <div class="text-sm">Marketplace: <b><?= $m($split['marketplace'] ?? 0) ?></b></div></div>
    </div>

    <!-- Money + sparkline -->
    <div class="grid md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Money position</h2>
            <table class="w-full text-sm">
                <tr class="border-b"><td class="py-2 text-gray-600">Cash (ledger 1000)</td><td class="text-right font-bold <?= $cash >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $m($cash) ?></td></tr>
                <tr class="border-b"><td class="py-2 text-gray-600">Accounts receivable</td><td class="text-right"><?= $m($ar) ?></td></tr>
                <tr><td class="py-2 text-gray-600">Owed to sellers (pending payouts)</td><td class="text-right text-amber-700"><?= $m($merchantOwed) ?></td></tr>
            </table>
            <a href="accounting-hub.php" class="text-blue-600 text-sm hover:underline mt-2 inline-block">Full accounting →</a>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Sales — last 14 days</h2>
            <?php if (empty($daily)): ?><p class="text-gray-400 text-sm">No paid orders yet in this window.</p>
            <?php else: ?>
            <div class="flex items-end gap-1 h-28">
                <?php foreach ($daily as $d => $s): ?>
                    <div class="flex-1 flex flex-col items-center justify-end h-full" title="<?= $d ?>: <?= $m($s) ?>">
                        <div class="w-full bg-blue-500 rounded-t" style="height:<?= max(3, round($s / $maxDay * 100)) ?>%"></div>
                        <div class="text-[9px] text-gray-400 mt-0.5"><?= date('j', strtotime($d)) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top products + inventory health -->
    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Top products (30d revenue)</h2>
            <?php if (empty($top)): ?><p class="text-gray-400 text-sm">No sales in the window.</p><?php endif; ?>
            <?php foreach ($top as $t): ?>
                <div class="flex justify-between border-b last:border-0 py-1.5 text-sm">
                    <span class="truncate pr-3"><?= htmlspecialchars(mb_substr($t['name'], 0, 45)) ?></span>
                    <span class="whitespace-nowrap text-gray-600"><?= (int)$t['qty'] ?> u · <b class="text-gray-900"><?= $m($t['rev']) ?></b></span>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Operations snapshot</h2>
            <table class="w-full text-sm">
                <tr class="border-b"><td class="py-2 text-gray-600">Products live</td><td class="text-right"><?= number_format($scalar("SELECT COUNT(*) FROM products WHERE inventory>0")) ?></td></tr>
                <tr class="border-b"><td class="py-2 text-gray-600">Low stock (≤3)</td><td class="text-right <?= $lowStock ? 'text-amber-700 font-medium' : '' ?>"><?= $lowStock ?></td></tr>
                <tr class="border-b"><td class="py-2 text-gray-600">Out of stock</td><td class="text-right"><?= $outStock ?></td></tr>
                <tr class="border-b"><td class="py-2 text-gray-600">Active sellers</td><td class="text-right"><?= $scalar("SELECT COUNT(*) FROM users WHERE role='merchant'") ?></td></tr>
                <tr><td class="py-2 text-gray-600">Registered customers</td><td class="text-right"><?= $scalar("SELECT COUNT(*) FROM users WHERE role NOT IN ('admin','merchant','staff')") ?></td></tr>
            </table>
            <a href="reorder-alerts.php" class="text-blue-600 text-sm hover:underline mt-2 inline-block">Reorder alerts →</a>
        </div>
    </div>
</div>
</body>
</html>
