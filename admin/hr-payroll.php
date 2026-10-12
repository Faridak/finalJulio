<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/HRService.php';
require_once '../includes/AuditLog.php';

requireRole('admin');
requireScope('hr');

$hr = new HRService($pdo);
$notice = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('CSRF mismatch'); }
    $action = $_POST['action'] ?? '';
    $runId = (int) ($_POST['run_id'] ?? 0);

    if ($action === 'generate') {
        $r = $hr->generateRun($_POST['period_start'], $_POST['period_end'], $_SESSION['user_id']);
        if ($r['success']) {
            $notice = "Draft run #{$r['run_id']} created — {$r['employees']} employee(s), gross \$".number_format($r['gross'],2).", net \$".number_format($r['net'],2).".";
        } else $error = $r['error'];
    } elseif ($action === 'approve') {
        $hr->approveRun($runId);
        $notice = "Run #$runId approved — ready to pay.";
        AuditLog::record($pdo, 'hr.payroll_approve', 'payroll_run', $runId, "Approved payroll run #$runId");
    } elseif ($action === 'pay') {
        $r = $hr->payRun($runId);
        if ($r['success']) {
            $notice = "Run #$runId PAID — \$".number_format($r['net'],2)." net out of \$".number_format($r['gross'],2)." gross (\$".number_format($r['tax'],2)." tax withheld). Posted to the ledger.";
            AuditLog::record($pdo, 'hr.payroll_pay', 'payroll_run', $runId,
                "Paid payroll run #$runId — net \$".number_format($r['net'],2), ['gross'=>$r['gross'],'tax'=>$r['tax'],'net'=>$r['net']]);
        } else $error = $r['error'];
    }
}

$runs = $pdo->query("SELECT * FROM payroll_runs ORDER BY period_start DESC LIMIT 24")->fetchAll(PDO::FETCH_ASSOC);
$viewRun = (int) ($_GET['run'] ?? 0);
$items = [];
if ($viewRun) {
    $s = $pdo->prepare("SELECT pi.*, e.full_name, e.position FROM payroll_items pi JOIN employees e ON pi.employee_id=e.id WHERE pi.run_id=? ORDER BY e.full_name");
    $s->execute([$viewRun]);
    $items = $s->fetchAll(PDO::FETCH_ASSOC);
}
$statusBadge = ['draft'=>'bg-gray-100 text-gray-700','approved'=>'bg-amber-100 text-amber-800','paid'=>'bg-green-100 text-green-800'];
$m = fn($n) => '$'.number_format((float)$n,2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">Payroll</h1>
        <div class="text-sm"><a href="hr-dashboard.php" class="text-blue-600 hover:underline mr-3">← HR</a><a href="accounting-hub.php" class="text-blue-600 hover:underline">Accounting →</a></div>
    </div>
    <p class="text-sm text-gray-500 mb-5">Draft → Approve → Pay. Paying posts to the ledger: Dr Payroll Expense (gross) / Cr Payroll Taxes Payable / Cr Cash (net). Includes approved overtime and deducts unpaid leave.</p>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" class="bg-white rounded-lg shadow p-4 mb-6 flex flex-wrap gap-2 items-end text-sm">
        <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="generate">
        <div><label class="block text-xs text-gray-500">Period start</label><input type="date" name="period_start" required value="<?= date('Y-m-01') ?>" class="px-2 py-1.5 border rounded"></div>
        <div><label class="block text-xs text-gray-500">Period end</label><input type="date" name="period_end" required value="<?= date('Y-m-t') ?>" class="px-2 py-1.5 border rounded"></div>
        <button class="bg-blue-600 text-white px-4 py-2 rounded">Generate payroll run</button>
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b"><th class="px-4 py-2">Run</th><th>Period</th><th class="text-right">Gross</th><th class="text-right">Tax withheld</th><th class="text-right">Net</th><th>Status</th><th class="text-right px-3">Actions</th></tr></thead>
            <tbody>
                <?php if (empty($runs)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No payroll runs yet.</td></tr><?php endif; ?>
                <?php foreach ($runs as $r): ?>
                <tr class="border-b">
                    <td class="px-4 py-2 font-mono">#<?= (int)$r['id'] ?></td>
                    <td><?= htmlspecialchars($r['period_start']) ?> → <?= htmlspecialchars($r['period_end']) ?></td>
                    <td class="text-right"><?= $m($r['total_gross']) ?></td>
                    <td class="text-right text-gray-500"><?= $m($r['total_tax']) ?></td>
                    <td class="text-right font-bold"><?= $m($r['total_net']) ?></td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs <?= $statusBadge[$r['status']] ?>"><?= $r['status'] ?></span>
                        <?php if ($r['paid_at']): ?><div class="text-xs text-gray-400"><?= date('M j, Y', strtotime($r['paid_at'])) ?></div><?php endif; ?></td>
                    <td class="text-right px-3 whitespace-nowrap">
                        <a href="?run=<?= (int)$r['id'] ?>" class="text-blue-600 text-xs mr-2">detail</a>
                        <?php if ($r['status'] === 'draft'): ?>
                            <form method="POST" class="inline"><?= Security::getCSRFInput() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="run_id" value="<?= (int)$r['id'] ?>"><button class="bg-amber-600 text-white text-xs px-2 py-1 rounded">Approve</button></form>
                        <?php elseif ($r['status'] === 'approved'): ?>
                            <form method="POST" class="inline"><?= Security::getCSRFInput() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="run_id" value="<?= (int)$r['id'] ?>"><button class="bg-green-600 text-white text-xs px-2 py-1 rounded" onclick="return confirm('Pay <?= $m($r['total_net']) ?> net to employees?')">Pay</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($viewRun && $items): ?>
    <h2 class="font-semibold text-gray-800 mb-2">Run #<?= $viewRun ?> — payslips</h2>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b"><th class="px-4 py-2">Employee</th><th class="text-right">Base</th><th class="text-right">Overtime</th><th class="text-right">Unpaid leave</th><th class="text-right">Gross</th><th class="text-right">Tax</th><th class="text-right px-4">Net</th></tr></thead>
            <tbody>
                <?php foreach ($items as $i): ?>
                <tr class="border-b">
                    <td class="px-4 py-2"><?= htmlspecialchars($i['full_name']) ?> <span class="text-xs text-gray-400"><?= htmlspecialchars($i['position']) ?></span></td>
                    <td class="text-right"><?= $m($i['base_pay']) ?></td>
                    <td class="text-right <?= $i['overtime_pay']>0?'text-blue-700':'text-gray-400' ?>"><?= $m($i['overtime_pay']) ?></td>
                    <td class="text-right <?= $i['unpaid_leave_deduction']>0?'text-red-600':'text-gray-400' ?>">−<?= $m($i['unpaid_leave_deduction']) ?></td>
                    <td class="text-right font-medium"><?= $m($i['gross_pay']) ?></td>
                    <td class="text-right text-gray-500">−<?= $m($i['tax_withheld']) ?></td>
                    <td class="text-right px-4 font-bold text-green-700"><?= $m($i['net_pay']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
