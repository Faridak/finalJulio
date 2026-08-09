<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/SupplierCredibilityService.php';
require_once '../includes/AuditLog.php';

requireRole('admin');
requireScope('sellers');
$svc = new SupplierCredibilityService($pdo);
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    $svc->scoreAll();
    $notice = 'Credibility recomputed for all sellers.';
    AuditLog::record($pdo, 'seller.credibility_recompute', 'merchant', null, 'Recomputed credibility scores for all sellers');
}

$rows = $pdo->query("
    SELECT mc.*, u.email
    FROM merchant_credibility mc JOIN users u ON mc.merchant_id = u.id
    ORDER BY mc.score DESC
")->fetchAll(PDO::FETCH_ASSOC);

$tierColor = ['A'=>'bg-green-100 text-green-800','B'=>'bg-blue-100 text-blue-800','C'=>'bg-amber-100 text-amber-800','D'=>'bg-red-100 text-red-800'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Credibility - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold text-gray-900">Seller Credibility</h1>
        <form method="POST"><?= Security::getCSRFInput() ?><button class="bg-blue-600 text-white px-4 py-2 rounded text-sm">Recompute scores</button></form>
    </div>
    <p class="text-sm text-gray-500 mb-6">Score = volume trust + satisfaction − refund penalty. Tier sets the seller's subscription &amp; insurance fees (better sellers pay less).</p>

    <?php if ($notice): ?><div class="bg-green-50 border border-green-300 text-green-800 px-4 py-2 rounded mb-4"><?= htmlspecialchars($notice) ?></div><?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 bg-gray-50 border-b">
                    <th class="px-4 py-3">Seller</th><th>Score</th><th>Tier</th>
                    <th class="text-right">Orders</th><th class="text-right">Revenue</th>
                    <th class="text-right">Refund rate</th><th class="text-right">Rating</th>
                    <th class="text-right">Subscription</th><th class="text-right">Insurance</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?><tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">No sellers scored yet — click “Recompute scores”.</td></tr><?php endif; ?>
                <?php foreach ($rows as $r): ?>
                <tr class="border-b">
                    <td class="px-4 py-3"><?= htmlspecialchars($r['email']) ?><div class="text-xs text-gray-400">#<?= (int)$r['merchant_id'] ?></div></td>
                    <td class="py-3">
                        <div class="flex items-center gap-2">
                            <span class="font-bold"><?= (int)$r['score'] ?></span>
                            <div class="w-16 bg-gray-200 rounded h-1.5"><div class="h-1.5 rounded <?= $r['score']>=70?'bg-green-500':($r['score']>=50?'bg-amber-500':'bg-red-500') ?>" style="width:<?= (int)$r['score'] ?>%"></div></div>
                        </div>
                    </td>
                    <td class="py-3"><span class="px-2 py-0.5 rounded-full text-xs font-bold <?= $tierColor[$r['tier']]??'' ?>"><?= $r['tier'] ?></span></td>
                    <td class="text-right py-3"><?= (int)$r['orders_count'] ?></td>
                    <td class="text-right">$<?= number_format($r['revenue'],2) ?></td>
                    <td class="text-right <?= $r['refund_rate']>0.1?'text-red-600 font-medium':'' ?>"><?= round($r['refund_rate']*100,1) ?>%</td>
                    <td class="text-right"><?= $r['avg_rating']!==null?number_format($r['avg_rating'],1).'★':'—' ?></td>
                    <td class="text-right font-medium">$<?= number_format($r['subscription_fee'],2) ?>/mo</td>
                    <td class="text-right"><?= number_format($r['insurance_rate'],3) ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-xs text-gray-400 mt-3">Tier fee multipliers — A ×0.8 · B ×1.0 · C ×1.2 · D ×1.5 on a $99 base subscription and 1.0% base insurance rate.</p>
</div>
</body>
</html>
