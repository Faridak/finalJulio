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
        if ($action === 'add_employee') {
            $id = $hr->addEmployee([
                'full_name' => trim($_POST['full_name']), 'email' => trim($_POST['email']) ?: null,
                'phone' => trim($_POST['phone']) ?: null, 'position' => trim($_POST['position']),
                'department' => $_POST['department'] ?? 'general', 'employment_type' => $_POST['employment_type'] ?? 'full_time',
                'pay_type' => $_POST['pay_type'] ?? 'salary',
                'monthly_salary' => (float) ($_POST['monthly_salary'] ?? 0), 'hourly_rate' => (float) ($_POST['hourly_rate'] ?? 0),
                'tax_rate_pct' => (float) ($_POST['tax_rate_pct'] ?? 10), 'hire_date' => $_POST['hire_date'] ?: date('Y-m-d'),
            ]);
            $notice = "Employee #$id added.";
            AuditLog::record($pdo, 'hr.employee_add', 'employee', $id, "Hired {$_POST['full_name']} as {$_POST['position']}");
        } elseif ($action === 'terminate') {
            $hr->terminate((int) $_POST['employee_id']);
            // disable their login too
            $pdo->prepare("UPDATE users u JOIN employees e ON e.user_id = u.id SET u.role='user' WHERE e.id = ?")
                ->execute([(int) $_POST['employee_id']]);
            $notice = 'Employee terminated (login revoked).';
            AuditLog::record($pdo, 'hr.employee_terminate', 'employee', (int) $_POST['employee_id'], "Terminated employee #{$_POST['employee_id']}");
        } elseif ($action === 'create_login') {
            $eid = (int) $_POST['employee_id'];
            $e = $pdo->query("SELECT * FROM employees WHERE id = $eid")->fetch(PDO::FETCH_ASSOC);
            if (!$e) $error = 'Employee not found.';
            elseif (empty($e['email'])) $error = 'Employee has no email — add one first.';
            elseif ($e['user_id']) $error = 'This employee already has a login.';
            else {
                // department -> admin-area scope
                $scopeMap = ['warehouse' => 'warehouse', 'finance' => 'finance', 'sales' => 'orders',
                             'marketing' => 'marketing', 'engineering' => 'engineering', 'admin' => 'all'];
                $scope = $scopeMap[strtolower($e['department'])] ?? 'orders';
                $tempPass = 'VD-' . strtoupper(bin2hex(random_bytes(3)));
                $pdo->prepare("INSERT INTO users (email, password, role, staff_scope, chat_nickname, created_at) VALUES (?,?,?,?,?,NOW())")
                    ->execute([$e['email'], password_hash($tempPass, PASSWORD_DEFAULT), 'staff', $scope, $e['full_name']]);
                $uid2 = (int) $pdo->lastInsertId();
                $pdo->prepare("UPDATE employees SET user_id = ? WHERE id = ?")->execute([$uid2, $eid]);
                $notice = "Login created for {$e['full_name']} — scope \"$scope\". Temporary password: $tempPass (share it once; they should change it via Forgot Password).";
                AuditLog::record($pdo, 'hr.login_create', 'employee', $eid, "Staff login created for {$e['full_name']} (scope $scope)");
            }
        } elseif ($action === 'add_opening') {
            $pdo->prepare("INSERT INTO job_openings (title, department, description) VALUES (?,?,?)")
                ->execute([trim($_POST['title']), $_POST['department'] ?? 'general', trim($_POST['description']) ?: null]);
            $notice = 'Job opening posted.';
        } elseif ($action === 'add_applicant') {
            $pdo->prepare("INSERT INTO job_applications (opening_id, full_name, email, phone, notes) VALUES (?,?,?,?,?)")
                ->execute([(int) $_POST['opening_id'], trim($_POST['full_name']), trim($_POST['email']) ?: null, trim($_POST['phone']) ?: null, trim($_POST['notes']) ?: null]);
            $notice = 'Applicant added.';
        } elseif ($action === 'app_status') {
            $st = $_POST['status'];
            $r = $hr->setApplicationStatus((int) $_POST['app_id'], $st, [
                'pay_type' => $_POST['pay_type'] ?? 'salary',
                'monthly_salary' => (float) ($_POST['monthly_salary'] ?? 0),
                'hourly_rate' => (float) ($_POST['hourly_rate'] ?? 0),
            ]);
            if ($r['success']) {
                $notice = $st === 'hired' ? "Applicant hired — employee #{$r['employee_id']} created." : "Application moved to $st.";
                if ($st === 'hired') AuditLog::record($pdo, 'hr.hire', 'employee', $r['employee_id'], "Hired applicant #{$_POST['app_id']}");
            } else $error = $r['error'];
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$employees = $pdo->query("SELECT * FROM employees ORDER BY status='active' DESC, full_name")->fetchAll(PDO::FETCH_ASSOC);
$openings = $pdo->query("SELECT o.*, (SELECT COUNT(*) FROM job_applications a WHERE a.opening_id=o.id AND a.status NOT IN ('hired','rejected')) AS active_apps FROM job_openings o WHERE o.status='open' ORDER BY o.id DESC")->fetchAll(PDO::FETCH_ASSOC);
$applicants = $pdo->query("SELECT a.*, o.title FROM job_applications a JOIN job_openings o ON a.opening_id=o.id WHERE a.status NOT IN ('hired','rejected') ORDER BY a.updated_at DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
$activeCount = count(array_filter($employees, fn($e) => $e['status'] === 'active'));
$monthlyCost = array_sum(array_map(fn($e) => $e['status'] === 'active' ? ($e['pay_type'] === 'salary' ? (float)$e['monthly_salary'] : (float)$e['hourly_rate'] * (float)$e['std_hours_per_period']) : 0, $employees));
$appBadge = ['applied'=>'bg-gray-100 text-gray-700','interview'=>'bg-blue-100 text-blue-800','offer'=>'bg-amber-100 text-amber-800'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">Human Resources</h1>
        <div class="text-sm"><a href="hr-payroll.php" class="text-blue-600 hover:underline mr-3">Payroll →</a><a href="hr-leave.php" class="text-blue-600 hover:underline">Leave &amp; Overtime →</a></div>
    </div>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Active employees</div><div class="text-2xl font-bold"><?= $activeCount ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Monthly payroll cost</div><div class="text-2xl font-bold text-indigo-700">$<?= number_format($monthlyCost, 2) ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Open positions</div><div class="text-2xl font-bold"><?= count($openings) ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Active applicants</div><div class="text-2xl font-bold text-blue-700"><?= count($applicants) ?></div></div>
    </div>

    <!-- Employees -->
    <h2 class="font-semibold text-gray-800 mb-2">Employees</h2>
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b"><th class="px-4 py-2">Name</th><th>Position</th><th>Dept</th><th>Type</th><th class="text-right">Pay</th><th>Hired</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($employees)): ?><tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No employees yet — add one below or hire from the pipeline.</td></tr><?php endif; ?>
                <?php foreach ($employees as $e): ?>
                <tr class="border-b <?= $e['status'] === 'terminated' ? 'opacity-50' : '' ?>">
                    <td class="px-4 py-2 font-medium"><?= htmlspecialchars($e['full_name']) ?><div class="text-xs text-gray-400"><?= htmlspecialchars($e['email'] ?? '') ?></div></td>
                    <td><?= htmlspecialchars($e['position']) ?></td>
                    <td><?= htmlspecialchars($e['department']) ?></td>
                    <td class="text-xs"><?= str_replace('_', ' ', $e['employment_type']) ?></td>
                    <td class="text-right"><?= $e['pay_type'] === 'salary' ? '$'.number_format($e['monthly_salary'], 2).'/mo' : '$'.number_format($e['hourly_rate'], 2).'/hr' ?></td>
                    <td class="text-gray-500"><?= htmlspecialchars($e['hire_date']) ?></td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs <?= $e['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>"><?= $e['status'] ?></span></td>
                    <td class="text-right px-3 whitespace-nowrap">
                        <?php if ($e['status'] === 'active'): ?>
                            <?php if (empty($e['user_id'])): ?>
                            <form method="POST" class="inline"><?= Security::getCSRFInput() ?><input type="hidden" name="action" value="create_login"><input type="hidden" name="employee_id" value="<?= (int)$e['id'] ?>">
                                <button class="bg-blue-600 text-white text-xs px-2 py-0.5 rounded">🔑 create login</button></form>
                            <?php else: ?><span class="text-xs text-green-700">login ✓</span><?php endif; ?>
                        <form method="POST" class="inline ml-1"><?= Security::getCSRFInput() ?><input type="hidden" name="action" value="terminate"><input type="hidden" name="employee_id" value="<?= (int)$e['id'] ?>">
                            <button class="text-red-600 text-xs" onclick="return confirm('Terminate <?= htmlspecialchars($e['full_name']) ?>?')">terminate</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Add employee -->
    <details class="bg-white rounded-lg shadow mb-8">
        <summary class="px-5 py-3 cursor-pointer font-semibold text-gray-800">＋ Add employee directly</summary>
        <form method="POST" class="p-5 pt-2 grid md:grid-cols-4 gap-3 text-sm">
            <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="add_employee">
            <input name="full_name" required placeholder="Full name *" class="px-3 py-2 border rounded">
            <input name="email" type="email" placeholder="Email" class="px-3 py-2 border rounded">
            <input name="phone" placeholder="Phone" class="px-3 py-2 border rounded">
            <input name="position" required placeholder="Position *" class="px-3 py-2 border rounded">
            <select name="department" class="px-3 py-2 border rounded"><option>general</option><option>warehouse</option><option>sales</option><option>finance</option><option>engineering</option><option>admin</option></select>
            <select name="employment_type" class="px-3 py-2 border rounded"><option value="full_time">Full time</option><option value="part_time">Part time</option><option value="contract">Contract</option></select>
            <select name="pay_type" class="px-3 py-2 border rounded"><option value="salary">Salary (monthly)</option><option value="hourly">Hourly</option></select>
            <input name="monthly_salary" type="number" step="0.01" placeholder="Monthly salary $" class="px-3 py-2 border rounded">
            <input name="hourly_rate" type="number" step="0.01" placeholder="Hourly rate $" class="px-3 py-2 border rounded">
            <input name="tax_rate_pct" type="number" step="0.1" value="10" title="Withholding %" class="px-3 py-2 border rounded">
            <input name="hire_date" type="date" value="<?= date('Y-m-d') ?>" class="px-3 py-2 border rounded">
            <button class="bg-blue-600 text-white rounded px-4 py-2">Add employee</button>
        </form>
    </details>

    <!-- Hiring pipeline -->
    <h2 class="font-semibold text-gray-800 mb-2">Hiring pipeline</h2>
    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-medium text-gray-700 mb-3">Open positions</h3>
            <?php foreach ($openings as $o): ?>
                <div class="flex justify-between border-b py-2 text-sm"><span><?= htmlspecialchars($o['title']) ?> <span class="text-xs text-gray-400">(<?= htmlspecialchars($o['department']) ?>)</span></span><span class="text-gray-500"><?= (int)$o['active_apps'] ?> applicant(s)</span></div>
            <?php endforeach; if (empty($openings)) echo '<p class="text-gray-400 text-sm">No open positions.</p>'; ?>
            <form method="POST" class="mt-4 flex flex-wrap gap-2 text-sm">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="add_opening">
                <input name="title" required placeholder="Position title" class="px-3 py-2 border rounded flex-1">
                <select name="department" class="px-3 py-2 border rounded"><option>general</option><option>warehouse</option><option>sales</option><option>finance</option><option>engineering</option></select>
                <input name="description" placeholder="Short description" class="px-3 py-2 border rounded w-full">
                <button class="bg-gray-800 text-white rounded px-4 py-2">Post opening</button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow p-5">
            <h3 class="font-medium text-gray-700 mb-3">Applicants</h3>
            <?php foreach ($applicants as $a): ?>
                <div class="border-b py-2 text-sm">
                    <div class="flex justify-between items-center">
                        <span><b><?= htmlspecialchars($a['full_name']) ?></b> → <?= htmlspecialchars($a['title']) ?></span>
                        <span class="px-2 py-0.5 rounded-full text-xs <?= $appBadge[$a['status']] ?? 'bg-gray-100' ?>"><?= $a['status'] ?></span>
                    </div>
                    <form method="POST" class="mt-1 flex flex-wrap gap-1 items-center text-xs">
                        <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="app_status"><input type="hidden" name="app_id" value="<?= (int)$a['id'] ?>">
                        <?php if ($a['status'] === 'applied'): ?><button name="status" value="interview" class="bg-blue-600 text-white px-2 py-1 rounded">Interview</button><?php endif; ?>
                        <?php if ($a['status'] === 'interview'): ?><button name="status" value="offer" class="bg-amber-600 text-white px-2 py-1 rounded">Make offer</button><?php endif; ?>
                        <?php if ($a['status'] === 'offer'): ?>
                            <select name="pay_type" class="border rounded px-1 py-1"><option value="salary">salary</option><option value="hourly">hourly</option></select>
                            <input name="monthly_salary" type="number" step="0.01" placeholder="$/mo" class="border rounded px-1 py-1 w-20">
                            <input name="hourly_rate" type="number" step="0.01" placeholder="$/hr" class="border rounded px-1 py-1 w-16">
                            <button name="status" value="hired" class="bg-green-600 text-white px-2 py-1 rounded">✓ Hire</button>
                        <?php endif; ?>
                        <button name="status" value="rejected" class="text-red-600 px-2">reject</button>
                    </form>
                </div>
            <?php endforeach; if (empty($applicants)) echo '<p class="text-gray-400 text-sm">No active applicants.</p>'; ?>
            <?php if ($openings): ?>
            <form method="POST" class="mt-4 flex flex-wrap gap-2 text-sm">
                <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="add_applicant">
                <select name="opening_id" class="px-3 py-2 border rounded"><?php foreach ($openings as $o): ?><option value="<?= (int)$o['id'] ?>"><?= htmlspecialchars($o['title']) ?></option><?php endforeach; ?></select>
                <input name="full_name" required placeholder="Applicant name" class="px-3 py-2 border rounded flex-1">
                <input name="email" type="email" placeholder="Email" class="px-3 py-2 border rounded">
                <button class="bg-gray-800 text-white rounded px-4 py-2">Add applicant</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
