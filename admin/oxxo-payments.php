<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/OxxoPaymentService.php';
require_once '../includes/AuditLog.php';

requireRole('admin');
requireScope('finance');

$svc = new OxxoPaymentService($pdo);
$svc->expireOverdue();
$notice = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    $r = $svc->confirmPayment($_POST['reference'] ?? '', 'manual');
    if ($r['success']) {
        $notice = "Reference confirmed — order #{$r['order_id']} paid \$" . number_format($r['amount'], 2) . ' (cash). Fulfilment started.';
        AuditLog::record($pdo, 'oxxo.confirm', 'order', $r['order_id'],
            "OXXO cash payment confirmed manually — \$" . number_format($r['amount'], 2), ['reference' => $_POST['reference']]);
    } else $error = $r['error'];
}

$rows = $pdo->query("
    SELECT op.*, o.user_id, u.email
    FROM oxxo_payments op JOIN orders o ON op.order_id = o.id LEFT JOIN users u ON o.user_id = u.id
    ORDER BY op.status='pending' DESC, op.id DESC LIMIT 40
")->fetchAll(PDO::FETCH_ASSOC);
$badge = ['pending'=>'bg-amber-100 text-amber-800','paid'=>'bg-green-100 text-green-800','expired'=>'bg-gray-100 text-gray-500','cancelled'=>'bg-red-100 text-red-700'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OXXO Payments - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-5xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">OXXO Cash Payments</h1>
    <p class="text-sm text-gray-500 mb-5">Vouchers issued at checkout. Confirmation is automatic via the PSP webhook once connected — until then, confirm here when the cash report arrives.</p>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" class="bg-white rounded-lg shadow p-4 mb-6 flex gap-2 text-sm items-end">
        <?= Security::getCSRFInput() ?>
        <div class="flex-1"><label class="block text-xs text-gray-500">Confirm a reference (cash received)</label>
            <input name="reference" required placeholder="93XXXXXXXXXXXX" class="w-full px-3 py-2 border rounded font-mono"></div>
        <button class="bg-green-600 text-white px-5 py-2 rounded">✓ Confirm paid</button>
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b">
                <th class="px-4 py-2">Reference</th><th>Order</th><th>Customer</th>
                <th class="text-right">Amount</th><th>Expires</th><th>Status</th><th class="text-right px-3"></th>
            </tr></thead>
            <tbody>
                <?php if (empty($rows)): ?><tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No OXXO vouchers yet.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr class="border-b">
                    <td class="px-4 py-2 font-mono"><?= htmlspecialchars(trim(chunk_split($r['reference'], 4, ' '))) ?></td>
                    <td>#VD-<?= str_pad($r['order_id'], 6, '0', STR_PAD_LEFT) ?></td>
                    <td class="text-gray-500"><?= htmlspecialchars($r['email'] ?? '') ?></td>
                    <td class="text-right font-medium">$<?= number_format($r['amount'], 2) ?></td>
                    <td class="text-gray-500"><?= date('M j H:i', strtotime($r['expires_at'])) ?></td>
                    <td><span class="px-2 py-0.5 rounded-full text-xs <?= $badge[$r['status']] ?>"><?= $r['status'] ?></span>
                        <?php if ($r['confirmed_via']): ?><span class="text-[10px] text-gray-400"><?= $r['confirmed_via'] ?></span><?php endif; ?></td>
                    <td class="text-right px-3">
                        <?php if ($r['status'] === 'pending'): ?>
                        <form method="POST" class="inline"><?= Security::getCSRFInput() ?><input type="hidden" name="reference" value="<?= htmlspecialchars($r['reference']) ?>">
                            <button class="bg-green-600 text-white text-xs px-2 py-1 rounded" onclick="return confirm('Confirm cash received for $<?= number_format($r['amount'],2) ?>?')">✓ Paid</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
