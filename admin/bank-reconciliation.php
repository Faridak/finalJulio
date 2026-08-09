<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/BankReconciliationService.php';

requireRole('admin');
requireScope('finance');
$svc = new BankReconciliationService($pdo);
$notice = ''; $error = '';

$accounts = $pdo->query("SELECT id, name, account_kind, account_mask, gl_account_code FROM bank_accounts WHERE is_active=1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$bankId = (int) ($_GET['account'] ?? ($_POST['bank_account_id'] ?? ($accounts[0]['id'] ?? 0)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    $action = $_POST['action'] ?? '';
    if ($action === 'upload' && !empty($_FILES['statement']['tmp_name'])) {
        $csv = file_get_contents($_FILES['statement']['tmp_name']);
        $r = $svc->importCsv($bankId, $csv);
        $m = $svc->autoMatch($bankId, $_SESSION['user_id']);
        $notice = "Imported {$r['imported']} line(s); auto-matched {$m['matched']}.";
    } elseif ($action === 'rematch') {
        $m = $svc->autoMatch($bankId, $_SESSION['user_id']);
        $notice = "Auto-matched {$m['matched']} additional line(s).";
    } elseif ($action === 'ignore') {
        $svc->ignore((int) $_POST['line_id']); $notice = 'Line ignored.';
    }
    header('Location: bank-reconciliation.php?account=' . $bankId . '&ok=' . urlencode($notice)); exit;
}
if (isset($_GET['ok'])) $notice = $_GET['ok'];

$summary = $bankId ? $svc->summary($bankId) : null;
$recent = [];
if ($bankId) {
    $r = $pdo->prepare("SELECT txn_date, description, reference, amount, status, match_confidence FROM bank_statement_lines WHERE bank_account_id=? ORDER BY txn_date DESC, id DESC LIMIT 40");
    $r->execute([$bankId]); $recent = $r->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Reconciliation - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Bank Reconciliation</h1>
        <a href="financial-kpis.php" class="text-blue-600 hover:underline text-sm">Financial KPIs →</a>
    </div>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="flex items-center gap-3 mb-4">
        <form method="GET">
            <select name="account" onchange="this.form.submit()" class="px-3 py-2 border rounded text-sm">
                <?php foreach ($accounts as $a): ?>
                    <option value="<?= (int)$a['id'] ?>" <?= $a['id']==$bankId?'selected':'' ?>>
                        <?= htmlspecialchars($a['name'].' ('.$a['account_kind'].' ••'.$a['account_mask'].') → GL '.$a['gl_account_code']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2">
            <?= Security::getCSRFInput() ?>
            <input type="hidden" name="bank_account_id" value="<?= $bankId ?>">
            <input type="hidden" name="action" value="upload">
            <input type="file" name="statement" accept=".csv" class="text-sm" required>
            <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Upload statement (CSV)</button>
        </form>
        <form method="POST"><?= Security::getCSRFInput() ?><input type="hidden" name="bank_account_id" value="<?= $bankId ?>"><input type="hidden" name="action" value="rematch"><button class="bg-gray-700 text-white px-4 py-2 rounded text-sm">Re-run auto-match</button></form>
    </div>
    <p class="text-xs text-gray-400 mb-6">CSV columns: date, description, amount (signed), reference. Deposits +, withdrawals −.</p>

    <?php if ($summary): ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Matched</div><div class="text-2xl font-bold text-green-700"><?= $summary['matched'] ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Unmatched</div><div class="text-2xl font-bold <?= $summary['unmatched']>0?'text-red-700':'text-gray-700' ?>"><?= $summary['unmatched'] ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Statement balance</div><div class="text-2xl font-bold">$<?= number_format($summary['statement_balance'],2) ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Unmatched value</div><div class="text-2xl font-bold text-red-700">$<?= number_format($summary['unmatched_value'],2) ?></div></div>
    </div>

    <?php if ($summary['unmatched_lines']): ?>
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <h2 class="font-semibold text-red-700 mb-3">⚠ Unmatched — resolve these</h2>
        <table class="w-full text-sm">
            <?php foreach ($summary['unmatched_lines'] as $u): ?>
            <tr class="border-b">
                <td class="py-2"><?= htmlspecialchars($u['txn_date']) ?></td>
                <td><?= htmlspecialchars($u['description']) ?> <span class="text-gray-400 font-mono text-xs"><?= htmlspecialchars($u['reference']) ?></span></td>
                <td class="text-right font-semibold <?= $u['amount']<0?'text-red-600':'text-green-700' ?>">$<?= number_format($u['amount'],2) ?></td>
                <td class="text-right">
                    <form method="POST" class="inline"><?= Security::getCSRFInput() ?><input type="hidden" name="bank_account_id" value="<?= $bankId ?>"><input type="hidden" name="action" value="ignore"><input type="hidden" name="line_id" value="<?= $u['id'] ?>"><button class="text-gray-500 text-xs">ignore (bank fee/interest)</button></form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <p class="text-xs text-gray-400 mt-2">These have no matching ledger entry — a bank fee, interest, or an unrecorded transaction. Post them to the ledger, or ignore.</p>
    </div>
    <?php else: ?>
        <div class="bg-green-50 border border-green-300 text-green-800 px-4 py-3 rounded mb-6">✅ Fully reconciled — every statement line matches the ledger.</div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b"><th class="px-4 py-2">Date</th><th>Description</th><th class="text-right">Amount</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                <tr class="border-b">
                    <td class="px-4 py-2"><?= htmlspecialchars($r['txn_date']) ?></td>
                    <td><?= htmlspecialchars($r['description']) ?></td>
                    <td class="text-right <?= $r['amount']<0?'text-red-600':'' ?>">$<?= number_format($r['amount'],2) ?></td>
                    <td>
                        <?php if ($r['status']==='matched'): ?><span class="text-green-600 text-xs">✓ matched<?= $r['match_confidence']!==null?' ('.round($r['match_confidence']*100).'%)':'' ?></span>
                        <?php elseif ($r['status']==='ignored'): ?><span class="text-gray-400 text-xs">ignored</span>
                        <?php else: ?><span class="text-red-600 text-xs">unmatched</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-gray-400">No bank accounts configured.</p>
    <?php endif; ?>
</div>
</body>
</html>
