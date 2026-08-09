<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/FinancialMetricsService.php';

requireRole('admin');
requireScope('finance');
$fin = new FinancialMetricsService($pdo);

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_GET['to'] ?? date('Y-m-d');

$pnl = $fin->profitAndLoss($from, $to);
$bs  = $fin->balanceSheet();
$cf  = $fin->cashFlow($from, $to);
$sv  = $fin->solvency();

// ---- CSV export (opens in Excel too) -------------------------------------
if (($_GET['export'] ?? '') === 'csv') {
    $stmt = $_GET['stmt'] ?? 'pnl';
    $rows = [];
    if ($stmt === 'pnl') {
        $rows = [['Profit & Loss', "$from to $to"], ['Revenue', $pnl['revenue']], ['COGS', $pnl['cogs']],
                 ['Gross profit', $pnl['gross_profit']], ['Gross margin %', $pnl['gross_margin_pct']],
                 ['Operating expenses', $pnl['opex']], ['Net income', $pnl['net_income']], ['Net margin %', $pnl['net_margin_pct']]];
    } elseif ($stmt === 'balance') {
        $rows[] = ['Balance Sheet', date('Y-m-d')]; $rows[] = ['ASSETS', ''];
        foreach ($bs['assets_list'] as $a) $rows[] = [$a['account_code'].' '.$a['account_name'], $a['balance']];
        $rows[] = ['Total assets', $bs['total_assets']]; $rows[] = ['LIABILITIES', ''];
        foreach ($bs['liabilities_list'] as $a) $rows[] = [$a['account_code'].' '.$a['account_name'], $a['balance']];
        $rows[] = ['Total liabilities', $bs['total_liabilities']]; $rows[] = ['EQUITY', ''];
        foreach ($bs['equity_list'] as $a) $rows[] = [$a['account_code'].' '.$a['account_name'], $a['balance']];
        $rows[] = ['Retained earnings', $bs['retained_earnings']]; $rows[] = ['Total equity', $bs['total_equity']];
    } elseif ($stmt === 'cashflow') {
        $rows = [['Cash Flow', "$from to $to"], ['Beginning cash', $cf['beginning_cash']],
                 ['Operating inflows', $cf['operating_in']], ['Operating outflows', $cf['operating_out']],
                 ['Net operating', $cf['operating_net']], ['Financing', $cf['financing']],
                 ['Net change', $cf['net_change']], ['Ending cash', $cf['ending_cash']]];
    } elseif ($stmt === 'solvency') {
        $rows = [['Solvency & Liquidity', date('Y-m-d')], ['Current ratio', $sv['current_ratio']], ['Quick ratio', $sv['quick_ratio']],
                 ['Working capital', $sv['working_capital']], ['Debt to equity', $sv['debt_to_equity']], ['Equity ratio', $sv['equity_ratio']]];
    } elseif ($stmt === 'cogs') {
        $rows = [['COGS', "$from to $to"], ['Revenue', $pnl['revenue']], ['Cost of goods sold', $pnl['cogs']],
                 ['Gross profit', $pnl['gross_profit']], ['Gross margin %', $pnl['gross_margin_pct']]];
    }
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="'.$stmt.'-'.date('Ymd').'.csv"');
    $out = fopen('php://output', 'w');
    foreach ($rows as $r) fputcsv($out, $r);
    fclose($out); exit;
}
function m($n){ return '$'.number_format((float)$n,2); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Statements - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
    <style>@media print{.no-print{display:none}body{background:#fff}}</style>
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-4 no-print">
        <h1 class="text-2xl font-bold text-gray-900">Financial Statements</h1>
        <div class="flex gap-2 items-center">
            <form method="GET" class="flex gap-1 items-center text-sm">
                <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="px-2 py-1 border rounded">
                <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="px-2 py-1 border rounded">
                <button class="bg-gray-800 text-white px-3 py-1 rounded">Apply</button>
            </form>
            <button onclick="window.print()" class="bg-indigo-600 text-white px-3 py-1.5 rounded text-sm">🖨 Print / PDF</button>
            <a href="tax-report.php" class="text-blue-600 hover:underline text-sm">Tax report →</a>
        </div>
    </div>
    <p class="text-sm text-gray-500 mb-6">Automated from the general ledger · <?= htmlspecialchars($from) ?> to <?= htmlspecialchars($to) ?></p>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- P&L -->
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex justify-between items-center mb-3"><h2 class="font-semibold">Profit &amp; Loss</h2><a class="text-xs text-blue-600 no-print" href="?from=<?=$from?>&to=<?=$to?>&export=csv&stmt=pnl">CSV</a></div>
            <table class="w-full text-sm">
                <tr class="border-b"><td class="py-1">Revenue</td><td class="text-right"><?= m($pnl['revenue']) ?></td></tr>
                <tr class="border-b"><td class="py-1 text-gray-500">– COGS</td><td class="text-right text-gray-500"><?= m($pnl['cogs']) ?></td></tr>
                <tr class="border-b font-semibold"><td class="py-1">Gross profit</td><td class="text-right"><?= m($pnl['gross_profit']) ?> <span class="text-xs text-gray-400"><?= $pnl['gross_margin_pct'] ?>%</span></td></tr>
                <tr class="border-b"><td class="py-1 text-gray-500">– Operating expenses</td><td class="text-right text-gray-500"><?= m($pnl['opex']) ?></td></tr>
                <tr class="font-bold"><td class="py-1">Net income</td><td class="text-right <?= $pnl['net_income']>=0?'text-green-700':'text-red-700' ?>"><?= m($pnl['net_income']) ?></td></tr>
            </table>
        </div>

        <!-- COGS -->
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex justify-between items-center mb-3"><h2 class="font-semibold">COGS &amp; Margin</h2><a class="text-xs text-blue-600 no-print" href="?from=<?=$from?>&to=<?=$to?>&export=csv&stmt=cogs">CSV</a></div>
            <table class="w-full text-sm">
                <tr class="border-b"><td class="py-1">Revenue</td><td class="text-right"><?= m($pnl['revenue']) ?></td></tr>
                <tr class="border-b"><td class="py-1">Cost of goods sold</td><td class="text-right"><?= m($pnl['cogs']) ?></td></tr>
                <tr class="border-b"><td class="py-1">Gross profit</td><td class="text-right"><?= m($pnl['gross_profit']) ?></td></tr>
                <tr class="font-semibold"><td class="py-1">Gross margin</td><td class="text-right"><?= $pnl['gross_margin_pct'] ?>%</td></tr>
            </table>
        </div>

        <!-- Balance Sheet -->
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex justify-between items-center mb-3"><h2 class="font-semibold">Balance Sheet <span class="text-xs <?= $bs['balanced']?'text-green-600':'text-red-600' ?>"><?= $bs['balanced']?'✓ balanced':'⚠ off' ?></span></h2><a class="text-xs text-blue-600 no-print" href="?export=csv&stmt=balance">CSV</a></div>
            <div class="text-xs font-semibold text-gray-500 mt-2">ASSETS</div>
            <?php foreach ($bs['assets_list'] as $a): if($a['balance']==0) continue; ?><div class="flex justify-between text-sm"><span><?= htmlspecialchars($a['account_name']) ?></span><span><?= m($a['balance']) ?></span></div><?php endforeach; ?>
            <div class="flex justify-between text-sm font-semibold border-t mt-1"><span>Total assets</span><span><?= m($bs['total_assets']) ?></span></div>
            <div class="text-xs font-semibold text-gray-500 mt-3">LIABILITIES</div>
            <?php foreach ($bs['liabilities_list'] as $a): if($a['balance']==0) continue; ?><div class="flex justify-between text-sm"><span><?= htmlspecialchars($a['account_name']) ?></span><span><?= m($a['balance']) ?></span></div><?php endforeach; ?>
            <div class="flex justify-between text-sm border-t mt-1"><span>Total liabilities</span><span><?= m($bs['total_liabilities']) ?></span></div>
            <div class="text-xs font-semibold text-gray-500 mt-3">EQUITY</div>
            <?php foreach ($bs['equity_list'] as $a): if($a['balance']==0) continue; ?><div class="flex justify-between text-sm"><span><?= htmlspecialchars($a['account_name']) ?></span><span><?= m($a['balance']) ?></span></div><?php endforeach; ?>
            <div class="flex justify-between text-sm"><span>Retained earnings</span><span><?= m($bs['retained_earnings']) ?></span></div>
            <div class="flex justify-between text-sm font-semibold border-t mt-1"><span>Total equity</span><span><?= m($bs['total_equity']) ?></span></div>
        </div>

        <!-- Cash Flow -->
        <div class="bg-white rounded-lg shadow p-5">
            <div class="flex justify-between items-center mb-3"><h2 class="font-semibold">Cash Flow</h2><a class="text-xs text-blue-600 no-print" href="?from=<?=$from?>&to=<?=$to?>&export=csv&stmt=cashflow">CSV</a></div>
            <table class="w-full text-sm">
                <tr class="border-b"><td class="py-1">Beginning cash</td><td class="text-right"><?= m($cf['beginning_cash']) ?></td></tr>
                <tr class="border-b"><td class="py-1 text-green-700">+ Operating inflows</td><td class="text-right text-green-700"><?= m($cf['operating_in']) ?></td></tr>
                <tr class="border-b"><td class="py-1 text-red-600">– Operating outflows</td><td class="text-right text-red-600"><?= m($cf['operating_out']) ?></td></tr>
                <tr class="border-b"><td class="py-1">Financing</td><td class="text-right"><?= m($cf['financing']) ?></td></tr>
                <tr class="border-b font-semibold"><td class="py-1">Net change</td><td class="text-right"><?= m($cf['net_change']) ?></td></tr>
                <tr class="font-bold"><td class="py-1">Ending cash</td><td class="text-right"><?= m($cf['ending_cash']) ?></td></tr>
            </table>
        </div>

        <!-- Solvency -->
        <div class="bg-white rounded-lg shadow p-5 md:col-span-2">
            <div class="flex justify-between items-center mb-3"><h2 class="font-semibold">Solvency &amp; Liquidity</h2><a class="text-xs text-blue-600 no-print" href="?export=csv&stmt=solvency">CSV</a></div>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">
                <?php
                $rat = [['Current ratio',$sv['current_ratio'],($sv['current_ratio']??0)>=1],['Quick ratio',$sv['quick_ratio'],($sv['quick_ratio']??0)>=1],
                        ['Working capital',m($sv['working_capital']),$sv['working_capital']>=0],['Debt / equity',$sv['debt_to_equity'],($sv['debt_to_equity']??0)<=2],['Equity ratio',$sv['equity_ratio'],true]];
                foreach ($rat as $r): ?>
                    <div><div class="text-xs uppercase text-gray-400"><?= $r[0] ?></div><div class="text-xl font-bold <?= $r[2]?'text-green-700':'text-red-700' ?>"><?= $r[1] ?? '—' ?></div></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>
