<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/MarketplacePayoutService.php';
require_once '../includes/AuditLog.php';

requireRole('admin');
requireScope('finance');
$svc = new MarketplacePayoutService($pdo);
$notice = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    $mid = (int) ($_POST['merchant_id'] ?? 0);
    $r = $svc->runPayout($mid, $_SESSION['user_id']);
    if ($r['success']) {
        $notice = "Paid seller #$mid \$".number_format($r['net'],2)." (commission kept \$".number_format($r['commission'],2).") — ref {$r['reference']}.";
        AuditLog::record($pdo, 'seller.payout', 'merchant', $mid,
            "Paid seller #$mid \$".number_format($r['net'],2)." (ref {$r['reference']})",
            ['net'=>$r['net'], 'commission'=>$r['commission'], 'reference'=>$r['reference']]);
    } else $error = $r['error'];
}

$pending = $svc->pendingSellers();
$recent = $pdo->query("
    SELECT mp.*, u.email FROM merchant_payouts mp JOIN users u ON mp.merchant_id=u.id
    ORDER BY mp.id DESC LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
$m = fn($n) => '$'.number_format((float)$n,2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Payouts - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-5xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">Seller Payouts</h1>
        <a href="merchant-credibility.php" class="text-blue-600 hover:underline text-sm">Seller credibility →</a>
    </div>
    <p class="text-sm text-gray-500 mb-6">Marketplace sellers earn their sale total minus platform commission. Pay their accumulated balance here (Dr Merchant Payable / Cr Cash).</p>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-4 py-2 rounded mb-4"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <h2 class="font-semibold text-gray-800 mb-2">Pending payouts</h2>
    <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b"><th class="px-4 py-3">Seller</th><th class="text-right">Sales</th><th class="text-right">Gross</th><th class="text-right">Commission</th><th class="text-right">Net owed</th><th></th></tr></thead>
            <tbody>
                <?php if (empty($pending)): ?><tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No pending seller payouts.</td></tr><?php endif; ?>
                <?php foreach ($pending as $s): ?>
                <tr class="border-b">
                    <td class="px-4 py-3"><?= htmlspecialchars($s['email']) ?><div class="text-xs text-gray-400">#<?= (int)$s['merchant_id'] ?></div></td>
                    <td class="text-right"><?= (int)$s['lines'] ?></td>
                    <td class="text-right"><?= $m($s['gross']) ?></td>
                    <td class="text-right text-gray-500"><?= $m($s['commission']) ?></td>
                    <td class="text-right font-bold text-green-700"><?= $m($s['net']) ?></td>
                    <td class="text-right px-4">
                        <form method="POST"><?= Security::getCSRFInput() ?><input type="hidden" name="merchant_id" value="<?= (int)$s['merchant_id'] ?>"><button class="bg-indigo-600 text-white text-xs px-3 py-1 rounded" onclick="return confirm('Pay <?= $m($s['net']) ?> to this seller?')">Pay now</button></form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 class="font-semibold text-gray-800 mb-2">Recent payouts</h2>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-gray-500 bg-gray-50 border-b"><th class="px-4 py-3">Ref</th><th>Seller</th><th class="text-right">Net paid</th><th class="text-right">Commission</th><th>When</th></tr></thead>
            <tbody>
                <?php if (empty($recent)): ?><tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No payouts yet.</td></tr><?php endif; ?>
                <?php foreach ($recent as $r): ?>
                <tr class="border-b">
                    <td class="px-4 py-2 font-mono text-xs"><?= htmlspecialchars($r['reference']) ?></td>
                    <td><?= htmlspecialchars($r['email']) ?></td>
                    <td class="text-right font-medium"><?= $m($r['net_amount']) ?></td>
                    <td class="text-right text-gray-500"><?= $m($r['commission_amount']) ?></td>
                    <td class="text-gray-500"><?= $r['paid_at'] ? htmlspecialchars(date('M j, Y', strtotime($r['paid_at']))) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
