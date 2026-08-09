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
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'log_overtime') {
            $hr->logOvertime((int) $_POST['employee_id'], $_POST['work_date'], (float) $_POST['hours'], trim($_POST['reason']) ?: null, (float) ($_POST['multiplier'] ?? 1.5));
            $notice = 'Overtime logged (pending approval).';
        } elseif ($action === 'decide_overtime') {
            $ok = $_POST['decision'] === 'approve';
            $hr->decideOvertime((int) $_POST['entry_id'], $ok, $_SESSION['user_id']);
            $notice = 'Overtime ' . ($ok ? 'approved — will be added to the next payroll run.' : 'rejected.');
            if ($ok) AuditLog::record($pdo, 'hr.overtime_approve', 'overtime', (int) $_POST['entry_id'], "Approved overtime entry #{$_POST['entry_id']}");
        } elseif ($action === 'request_leave') {
            $hr->requestLeave((int) $_POST['employee_id'], $_POST['leave_type'], $_POST['start_date'], $_POST['end_date'], trim($_POST['reason']) ?: null);
            $notice = 'Leave request submitted (pending approval).';
        } elseif ($action === 'decide_leave') {
            $ok = $_POST['decision'] === 'approve';
            $r = $hr->decideLeave((int) $_POST['request_id'], $ok, $_SESSION['user_id']);
            if ($r['success']) {
                $notice = 'Leave ' . ($ok ? 'approved.' : 'rejected.');
                if ($ok) AuditLog::record($pdo, 'hr.leave_approve', 'leave', (int) $_POST['request_id'], "Approved leave request #{$_POST['request_id']}");
            } else $error = $r['error'];
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$emps = $pdo->query("SELECT id, full_name FROM employees WHERE status='active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
$otPending = $pdo->query("SELECT ot.*, e.full_name FROM overtime_entries ot JOIN employees e ON ot.employee_id=e.id WHERE ot.status='pending' ORDER BY ot.work_date")->fetchAll(PDO::FETCH_ASSOC);
$lvPending = $pdo->query("SELECT lr.*, e.full_name FROM leave_requests lr JOIN employees e ON lr.employee_id=e.id WHERE lr.status='pending' ORDER BY lr.start_date")->fetchAll(PDO::FETCH_ASSOC);
$recent = $pdo->query("
    (SELECT 'overtime' kind, ot.id, e.full_name, CONCAT(ot.hours,'h × ',ot.multiplier,' on ',ot.work_date) detail, ot.status, ot.created_at FROM overtime_entries ot JOIN employees e ON ot.employee_id=e.id WHERE ot.status<>'pending')
    UNION ALL
    (SELECT 'leave' kind, lr.id, e.full_name, CONCAT(lr.leave_type,' ',lr.start_date,' → ',lr.end_date,' (',lr.days,'d)') detail, lr.status, lr.created_at FROM leave_requests lr JOIN employees e ON lr.employee_id=e.id WHERE lr.status<>'pending')
    ORDER BY created_at DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

// leave balances per active employee
$balances = [];
foreach ($emps as $e) $balances[$e['id']] = $hr->leaveBalance($e['id']);
$typeBadge = ['vacation'=>'bg-blue-100 text-blue-800','sick'=>'bg-purple-100 text-purple-800','personal'=>'bg-gray-100 text-gray-700','unpaid'=>'bg-amber-100 text-amber-800'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave & Overtime - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">Leave &amp; Overtime</h1>
        <div class="text-sm"><a href="hr-dashboard.php" class="text-blue-600 hover:underline mr-3">← HR</a><a href="hr-payroll.php" class="text-blue-600 hover:underline">Payroll →</a></div>
    </div>
    <p class="text-sm text-gray-500 mb-5">Approved overtime is paid automatically in the next payroll run; approved unpaid leave is deducted. Vacation/sick approvals check the yearly balance.</p>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="grid md:grid-cols-2 gap-6 mb-6">
        <!-- Overtime -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Overtime — pending approval (<?= count($otPending) ?>)</h2>
            <?php foreach ($otPending as $o): ?>
                <div class="border-b py-2 text-sm flex justify-between items-center">
                    <div><b><?= htmlspecialchars($o['full_name']) ?></b> — <?= $o['hours'] ?>h × <?= $o['multiplier'] ?> on <?= htmlspecialchars($o['work_date']) ?>
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($o['reason'] ?? '') ?></div></div>
                    <form method="POST" class="flex gap-1">
                        <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="decide_overtime"><input type="hidden" name="entry_id" value="<?= (int)$o['id'] ?>">
                        <button name="decision" value="approve" class="bg-green-600 text-white text-xs px-2 py-1 rounded">Approve</button>
                        <button name="decision" value="reject" class="text-red-600 text-xs px-2">reject</button>
                    </form>
                </div>
            <?php endforeach; if (empty($otPending)) echo '<p class="text-gray-400 text-sm">Nothing pending. ✅</p>'; ?>

            <form method="POST" class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="log_overtime">
                <select name="employee_id" required class="px-2 py-1.5 border rounded col-span-2"><option value="">— employee —</option><?php foreach ($emps as $e): ?><option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?></option><?php endforeach; ?></select>
                <input type="date" name="work_date" required value="<?= date('Y-m-d') ?>" class="px-2 py-1.5 border rounded">
                <input type="number" name="hours" step="0.25" min="0.25" required placeholder="Hours" class="px-2 py-1.5 border rounded">
                <select name="multiplier" class="px-2 py-1.5 border rounded"><option value="1.5">×1.5 (standard)</option><option value="2">×2 (holiday)</option><option value="1">×1</option></select>
                <input name="reason" placeholder="Reason" class="px-2 py-1.5 border rounded">
                <button class="bg-blue-600 text-white rounded px-4 py-2 col-span-2">Log overtime</button>
            </form>
        </div>

        <!-- Leave -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Leave — pending approval (<?= count($lvPending) ?>)</h2>
            <?php foreach ($lvPending as $l): ?>
                <div class="border-b py-2 text-sm flex justify-between items-center">
                    <div><b><?= htmlspecialchars($l['full_name']) ?></b>
                        <span class="px-2 py-0.5 rounded-full text-xs <?= $typeBadge[$l['leave_type']] ?>"><?= $l['leave_type'] ?></span>
                        <?= htmlspecialchars($l['start_date']) ?> → <?= htmlspecialchars($l['end_date']) ?> (<?= $l['days'] ?>d)
                        <div class="text-xs text-gray-400"><?= htmlspecialchars($l['reason'] ?? '') ?></div></div>
                    <form method="POST" class="flex gap-1">
                        <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="decide_leave"><input type="hidden" name="request_id" value="<?= (int)$l['id'] ?>">
                        <button name="decision" value="approve" class="bg-green-600 text-white text-xs px-2 py-1 rounded">Approve</button>
                        <button name="decision" value="reject" class="text-red-600 text-xs px-2">reject</button>
                    </form>
                </div>
            <?php endforeach; if (empty($lvPending)) echo '<p class="text-gray-400 text-sm">Nothing pending. ✅</p>'; ?>

            <form method="POST" class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="request_leave">
                <select name="employee_id" required class="px-2 py-1.5 border rounded"><option value="">— employee —</option><?php foreach ($emps as $e): ?><option value="<?= (int)$e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?></option><?php endforeach; ?></select>
                <select name="leave_type" class="px-2 py-1.5 border rounded"><option value="vacation">Vacation</option><option value="sick">Sick</option><option value="personal">Personal</option><option value="unpaid">Unpaid</option></select>
                <input type="date" name="start_date" required class="px-2 py-1.5 border rounded">
                <input type="date" name="end_date" required class="px-2 py-1.5 border rounded">
                <input name="reason" placeholder="Reason" class="px-2 py-1.5 border rounded col-span-2">
                <button class="bg-blue-600 text-white rounded px-4 py-2 col-span-2">Request leave</button>
            </form>
        </div>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <!-- Balances -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Leave balances (<?= date('Y') ?>)</h2>
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 border-b"><th class="py-1">Employee</th><th class="text-right">Vacation left</th><th class="text-right">Sick left</th></tr></thead>
                <tbody>
                    <?php foreach ($emps as $e): $b = $balances[$e['id']]; ?>
                    <tr class="border-b"><td class="py-1.5"><?= htmlspecialchars($e['full_name']) ?></td>
                        <td class="text-right <?= $b['vacation_left'] <= 0 ? 'text-red-600' : '' ?>"><?= $b['vacation_left'] ?>d</td>
                        <td class="text-right <?= $b['sick_left'] <= 0 ? 'text-red-600' : '' ?>"><?= $b['sick_left'] ?>d</td></tr>
                    <?php endforeach; if (empty($emps)) echo '<tr><td colspan="3" class="py-4 text-center text-gray-400">No active employees.</td></tr>'; ?>
                </tbody>
            </table>
        </div>

        <!-- History -->
        <div class="bg-white rounded-lg shadow p-5">
            <h2 class="font-semibold text-gray-800 mb-3">Recent decisions</h2>
            <?php foreach ($recent as $r): ?>
                <div class="border-b py-1.5 text-sm flex justify-between">
                    <span><b><?= htmlspecialchars($r['full_name']) ?></b> · <?= $r['kind'] ?> · <?= htmlspecialchars($r['detail']) ?></span>
                    <span class="text-xs <?= $r['status'] === 'approved' ? 'text-green-700' : 'text-red-600' ?>"><?= $r['status'] ?></span>
                </div>
            <?php endforeach; if (empty($recent)) echo '<p class="text-gray-400 text-sm">No history yet.</p>'; ?>
        </div>
    </div>
</div>
</body>
</html>
