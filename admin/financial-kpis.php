<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/FinancialMetricsService.php';

requireRole('admin');
requireScope('finance');
$k = (new FinancialMetricsService($pdo))->kpis();
$pl = $k['pnl'];
function money($n){ return '$'.number_format($n,2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial KPIs - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Financial KPIs</h1>
            <p class="text-sm text-gray-500">Automated from the general ledger · <?= htmlspecialchars($pl['from']) ?> to <?= htmlspecialchars($pl['to']) ?></p>
        </div>
        <a href="bank-reconciliation.php" class="text-blue-600 hover:underline text-sm">Bank reconciliation →</a>
    </div>

    <!-- Headline P&L -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <?php
        $cards = [
            ['Revenue', money($pl['revenue']), 'text-gray-900', ''],
            ['Gross profit', money($pl['gross_profit']), 'text-green-700', $pl['gross_margin_pct'].'% margin'],
            ['Net income', money($pl['net_income']), $pl['net_income']>=0?'text-green-700':'text-red-700', $pl['net_margin_pct'].'% net'],
            ['Cash', money($k['cash']), 'text-blue-700', 'on hand'],
        ];
        foreach ($cards as $c): ?>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-xs uppercase text-gray-400"><?= $c[0] ?></div>
                <div class="text-2xl font-bold <?= $c[2] ?>"><?= $c[1] ?></div>
                <div class="text-xs text-gray-400"><?= $c[3] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="grid md:grid-cols-3 gap-6">
        <!-- P&L breakdown -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold mb-3">Profit &amp; Loss</h2>
            <table class="w-full text-sm">
                <tr class="border-b"><td class="py-1">Revenue</td><td class="text-right font-medium"><?= money($pl['revenue']) ?></td></tr>
                <tr class="border-b"><td class="py-1 text-gray-500">– COGS</td><td class="text-right text-gray-500"><?= money($pl['cogs']) ?></td></tr>
                <tr class="border-b font-semibold"><td class="py-1">Gross profit</td><td class="text-right"><?= money($pl['gross_profit']) ?> <span class="text-xs text-gray-400"><?= $pl['gross_margin_pct'] ?>%</span></td></tr>
                <tr class="border-b"><td class="py-1 text-gray-500">– Operating expenses</td><td class="text-right text-gray-500"><?= money($pl['opex']) ?></td></tr>
                <tr class="font-bold"><td class="py-1">Net income</td><td class="text-right <?= $pl['net_income']>=0?'text-green-700':'text-red-700' ?>"><?= money($pl['net_income']) ?></td></tr>
            </table>
        </div>

        <!-- A/R aging -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold mb-3">Accounts Receivable <span class="text-xs text-gray-400">DSO <?= $k['dso_days'] ?>d</span></h2>
            <div class="text-2xl font-bold mb-2"><?= money($k['ar']['total']) ?></div>
            <?php foreach (['current'=>'Current','d1_30'=>'1–30d','d31_60'=>'31–60d','d60_plus'=>'60d+'] as $b=>$lbl): ?>
                <div class="flex justify-between text-sm border-b py-1"><span class="<?= $b==='d60_plus'&&$k['ar'][$b]>0?'text-red-600':'text-gray-600' ?>"><?= $lbl ?></span><span><?= money($k['ar'][$b]) ?></span></div>
            <?php endforeach; ?>
        </div>

        <!-- A/P aging -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold mb-3">Accounts Payable <span class="text-xs text-gray-400">DPO <?= $k['dpo_days'] ?>d</span></h2>
            <div class="text-2xl font-bold mb-2"><?= money($k['ap']['total']) ?></div>
            <?php foreach (['current'=>'Current','d1_30'=>'1–30d','d31_60'=>'31–60d','d60_plus'=>'60d+'] as $b=>$lbl): ?>
                <div class="flex justify-between text-sm border-b py-1"><span class="<?= $b==='d60_plus'&&$k['ap'][$b]>0?'text-red-600':'text-gray-600' ?>"><?= $lbl ?></span><span><?= money($k['ap'][$b]) ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Ratios -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Current ratio</div><div class="text-xl font-bold <?= ($k['current_ratio']??0)>=1?'text-green-700':'text-red-700' ?>"><?= $k['current_ratio'] ?? '—' ?></div><div class="text-xs text-gray-400">assets ÷ liabilities</div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">DSO</div><div class="text-xl font-bold"><?= $k['dso_days'] ?>d</div><div class="text-xs text-gray-400">days to collect</div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">DPO</div><div class="text-xl font-bold"><?= $k['dpo_days'] ?>d</div><div class="text-xs text-gray-400">days to pay</div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Shrinkage</div><div class="text-xl font-bold text-red-700"><?= money($k['shrinkage']) ?></div><div class="text-xs text-gray-400">inventory loss</div></div>
    </div>
</div>
</body>
</html>
