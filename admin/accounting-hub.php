<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/FinancialMetricsService.php';
require_once '../services/BankReconciliationService.php';

requireRole('admin');
requireScope('finance');

$k = (new FinancialMetricsService($pdo))->kpis();
$pl = $k['pnl'];

// reconciliation status across bank accounts
$bankAccts = $pdo->query("SELECT id, name FROM bank_accounts WHERE is_active=1")->fetchAll(PDO::FETCH_ASSOC);
$rec = new BankReconciliationService($pdo);
$unreconciled = 0;
foreach ($bankAccts as $b) { $s = $rec->summary($b['id']); $unreconciled += $s['unmatched']; }

function m($n){ return '$'.number_format((float)$n,2); }

$links = [
    ['Financial Statements', 'financial-statements.php', 'P&L, Balance Sheet, Cash Flow, Solvency, COGS', '📊'],
    ['Financial KPIs', 'financial-kpis.php', 'Margins, DSO/DPO, aging, ratios', '📈'],
    ['Tax Report & Filing', 'tax-report.php', 'Sales tax + federal, export for gov portal', '🧾'],
    ['Bank Reconciliation', 'bank-reconciliation.php', 'Match bank / card statements to the ledger', '🏦'],
    ['Accounts Payable', 'accounts-payable.php', 'What we owe suppliers', '📤'],
    ['Accounts Receivable', 'accounts-receivable.php', 'What customers owe us', '📥'],
    ['Chart of Accounts', 'view-chart-of-accounts.php', 'The ledger account structure', '📚'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Accounting &amp; Finance</h1>
    <p class="text-sm text-gray-500 mb-6">One source of truth — everything below is computed live from the general ledger (<?= htmlspecialchars($pl['from']) ?> to <?= htmlspecialchars($pl['to']) ?>).</p>

    <!-- Live headline -->
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
        <?php
        $cards = [
            ['Revenue', m($pl['revenue']), 'text-gray-900'],
            ['Net income', m($pl['net_income']), $pl['net_income']>=0?'text-green-700':'text-red-700'],
            ['Cash', m($k['cash']), 'text-blue-700'],
            ['A/R', m($k['ar']['total']), 'text-gray-900'],
            ['A/P', m($k['ap']['total']), 'text-gray-900'],
            ['Unreconciled', $unreconciled, $unreconciled>0?'text-red-700':'text-green-700'],
        ];
        foreach ($cards as $c): ?>
            <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400"><?= $c[0] ?></div><div class="text-xl font-bold <?= $c[2] ?>"><?= $c[1] ?></div></div>
        <?php endforeach; ?>
    </div>

    <!-- Navigation -->
    <div class="grid md:grid-cols-3 gap-4">
        <?php foreach ($links as $l): ?>
            <a href="<?= $l[1] ?>" class="bg-white rounded-lg shadow p-5 hover:shadow-md transition block">
                <div class="text-2xl mb-1"><?= $l[3] ?></div>
                <div class="font-semibold text-gray-800"><?= htmlspecialchars($l[0]) ?></div>
                <div class="text-sm text-gray-500"><?= htmlspecialchars($l[2]) ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($unreconciled > 0): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-lg p-4 mt-6 text-sm">
        ⚠ <?= $unreconciled ?> bank statement line(s) still unreconciled — <a href="bank-reconciliation.php" class="underline font-medium">resolve them</a> so the KPIs are trustworthy.
    </div>
    <?php endif; ?>
</div>
</body>
</html>
