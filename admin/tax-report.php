<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/FinancialMetricsService.php';

requireRole('admin');
requireScope('finance');
$fin = new FinancialMetricsService($pdo);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-t');
$fedRate = (float) ($_GET['fed_rate'] ?? 21);
$tax = $fin->taxReport($from, $to, $fedRate);

// Export the filing as CSV (the file to upload to the gov portal for now).
if (($_GET['export'] ?? '') === 'csv') {
    $rows = [
        ['Tax Filing', $tax['period']],
        ['Generated', date('Y-m-d H:i')],
        [],
        ['SALES TAX'],
        ['Taxable sales', $tax['taxable_sales']],
        ['Sales tax collected', $tax['sales_tax_collected']],
        ['Sales tax already remitted', $tax['sales_tax_remitted']],
        ['Sales tax owed this period', $tax['sales_tax_owed']],
        [],
        ['FEDERAL / INCOME TAX'],
        ['Net income (taxable profit)', $tax['net_income']],
        ['Federal rate %', $tax['federal_rate']],
        ['Estimated federal tax', $tax['estimated_federal_tax']],
    ];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="tax-filing-'.$from.'_'.$to.'.csv"');
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
    <title>Tax Report - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Tax Report &amp; Filing</h1>
        <a href="financial-statements.php" class="text-blue-600 hover:underline text-sm">Financial statements →</a>
    </div>

    <form method="GET" class="flex flex-wrap gap-2 items-end mb-6 text-sm">
        <div><label class="block text-xs text-gray-500">Period from</label><input type="date" name="from" value="<?= htmlspecialchars($from) ?>" class="px-2 py-1 border rounded"></div>
        <div><label class="block text-xs text-gray-500">to</label><input type="date" name="to" value="<?= htmlspecialchars($to) ?>" class="px-2 py-1 border rounded"></div>
        <div><label class="block text-xs text-gray-500">Federal rate %</label><input type="number" step="0.1" name="fed_rate" value="<?= $fedRate ?>" class="px-2 py-1 border rounded w-24"></div>
        <button class="bg-gray-800 text-white px-4 py-1.5 rounded">Generate</button>
        <a href="?from=<?=$from?>&to=<?=$to?>&fed_rate=<?=$fedRate?>&export=csv" class="bg-green-600 text-white px-4 py-1.5 rounded">⬇ Export filing (CSV)</a>
    </form>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-3">Sales Tax — <?= htmlspecialchars($tax['period']) ?></h2>
        <table class="w-full text-sm">
            <tr class="border-b"><td class="py-2">Taxable sales</td><td class="text-right"><?= m($tax['taxable_sales']) ?></td></tr>
            <tr class="border-b"><td class="py-2">Sales tax collected</td><td class="text-right"><?= m($tax['sales_tax_collected']) ?></td></tr>
            <tr class="border-b"><td class="py-2 text-gray-500">Already remitted</td><td class="text-right text-gray-500"><?= m($tax['sales_tax_remitted']) ?></td></tr>
            <tr class="font-bold text-lg"><td class="py-2">Sales tax owed</td><td class="text-right text-red-700"><?= m($tax['sales_tax_owed']) ?></td></tr>
        </table>
    </div>

    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="font-semibold text-gray-800 mb-3">Federal / Income Tax</h2>
        <table class="w-full text-sm">
            <tr class="border-b"><td class="py-2">Net income (taxable profit)</td><td class="text-right"><?= m($tax['net_income']) ?></td></tr>
            <tr class="border-b"><td class="py-2">Federal rate</td><td class="text-right"><?= $tax['federal_rate'] ?>%</td></tr>
            <tr class="font-bold text-lg"><td class="py-2">Estimated federal tax</td><td class="text-right text-red-700"><?= m($tax['estimated_federal_tax']) ?></td></tr>
        </table>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
        <b>Filing:</b> Export the CSV above and upload it to the government portal. When the portal API is connected,
        this same report will submit automatically for the selected period — the numbers are computed live from the ledger,
        so they always tie out to Profit &amp; Loss and the balance sheet.
    </div>
</div>
</body>
</html>
