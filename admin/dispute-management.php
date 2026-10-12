<?php
/**
 * Dispute center (admin): queue of buyer <-> seller disputes and the decision
 * form. A buyer-favour decision creates an approved return; the refund is then
 * completed in Returns (payment record + optional restock + ledger reversal).
 * Rewritten on DisputeService — the old EscrowSystem version targeted tables
 * and order statuses that never existed.
 */
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/DisputeService.php';

requireRole('admin');
requireScope('orders');

$svc = new DisputeService($pdo);
$adminId = (int) $_SESSION['user_id'];
$flash = $_SESSION['dispute_flash'] ?? null;
unset($_SESSION['dispute_flash']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $disputeId = (int) ($_POST['dispute_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if ($action === 'note') {
        $res = $svc->reply($disputeId, $adminId, 'admin', $_POST['body'] ?? '');
        $msg = 'Message posted to both parties.';
    } elseif ($action === 'resolve') {
        $amount = trim($_POST['refund_amount'] ?? '');
        $res = $svc->resolve($disputeId, $adminId, $_POST['winner'] ?? '', $_POST['notes'] ?? '',
            $amount === '' ? null : (float) $amount, !empty($_POST['restock']));
        $msg = !empty($res['return_id'])
            ? 'Resolved for the buyer. Return #' . $res['return_id'] . ' approved — complete the refund in Returns.'
            : 'Resolved for the seller.';
    } else {
        $res = ['success' => false, 'error' => 'Unknown action.'];
    }
    if ($res['success']) {
        $_SESSION['dispute_flash'] = $msg;
        header('Location: dispute-management.php?id=' . $disputeId);
        exit;
    }
    $error = $res['error'];
}

$d = !empty($_GET['id']) ? $svc->get((int) $_GET['id'], $adminId, 'admin') : null;
$filter = in_array($_GET['f'] ?? '', ['active', 'resolved', 'all'], true) ? $_GET['f'] : 'active';
$list = $d ? [] : $svc->listFor($adminId, 'admin', $filter === 'all' ? null : $filter);
$stats = $svc->stats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispute Center - Admin</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
    <link rel="stylesheet" href="../public/vendor/css/fontawesome.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <?php if ($flash): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($d): $as = 'admin'; ?>
    <a href="dispute-management.php" class="text-blue-600 hover:underline text-sm mb-4 inline-block">&larr; Dispute queue</a>
    <div class="bg-white rounded-lg shadow p-4 mb-4 text-sm text-gray-600">
        Buyer: <?= htmlspecialchars($d['buyer_email'] ?? ('#' . $d['buyer_id'])) ?> ·
        Seller: <?= htmlspecialchars($d['seller_email'] ?? ('#' . $d['merchant_id'])) ?> ·
        <a href="order-details.php?id=<?= (int) $d['order_id'] ?>" class="text-blue-600 hover:underline">Order #<?= (int) $d['order_id'] ?></a> ·
        Priority: <?= htmlspecialchars($d['priority']) ?>
    </div>
    <?php include __DIR__ . '/../includes/dispute-thread.php'; ?>

    <?php if (in_array($d['status'], DisputeService::OPEN_STATUSES, true)): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <form method="POST" class="bg-white rounded-lg shadow p-6">
                <?= Security::getCSRFInput() ?>
                <input type="hidden" name="action" value="note"><input type="hidden" name="dispute_id" value="<?= (int) $d['id'] ?>">
                <h2 class="font-semibold text-gray-900 mb-2">Message both parties</h2>
                <textarea name="body" rows="4" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Ask for evidence, tracking, photos…"></textarea>
                <button class="mt-3 bg-gray-800 hover:bg-gray-900 text-white font-semibold py-2 px-4 rounded">Post message</button>
            </form>

            <form method="POST" class="bg-white rounded-lg shadow p-6" onsubmit="return confirm('Record this decision? Both parties are notified and it cannot be reopened.')">
                <?= Security::getCSRFInput() ?>
                <input type="hidden" name="action" value="resolve"><input type="hidden" name="dispute_id" value="<?= (int) $d['id'] ?>">
                <h2 class="font-semibold text-gray-900 mb-2">Decision</h2>
                <div class="flex gap-6 text-sm mb-3">
                    <label><input type="radio" name="winner" value="buyer" required> Buyer (refund)</label>
                    <label><input type="radio" name="winner" value="seller"> Seller (no refund)</label>
                </div>
                <label class="block text-sm text-gray-700 mb-1">Refund amount — blank = full refund of this seller's items + tax share</label>
                <input type="number" name="refund_amount" step="0.01" min="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-3"
                       placeholder="<?= $d['amount_requested'] !== null ? 'Buyer asked $' . number_format($d['amount_requested'], 2) : 'Full refund' ?>">
                <label class="text-sm flex items-center gap-2 mb-3"><input type="checkbox" name="restock" value="1"> Goods were returned — restock (full refunds only)</label>
                <textarea name="notes" rows="3" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Decision and reasoning (visible to buyer and seller)"></textarea>
                <button class="mt-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">Record decision</button>
            </form>
        </div>
    <?php elseif ($d['return_request_id'] && $d['return_status'] !== 'refunded'): ?>
        <a href="returns.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">Complete refund for return #<?= (int) $d['return_request_id'] ?> in Returns &rarr;</a>
    <?php endif; ?>

<?php else: ?>
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Dispute Center</h1>
    <p class="text-sm text-gray-500 mb-6">Buyer &harr; seller disputes. Sellers get <?= DisputeService::SELLER_DAYS ?> days to answer; buyers can escalate after that.</p>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Active</div><div class="text-xl font-bold"><?= (int) $stats['active'] ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Needs decision</div><div class="text-xl font-bold text-purple-700"><?= (int) $stats['under_review'] ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Seller overdue</div><div class="text-xl font-bold text-red-600"><?= (int) $stats['seller_overdue'] ?></div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Resolved (30d)</div><div class="text-xl font-bold"><?= (int) $stats['resolved_30d'] ?></div><div class="text-xs text-gray-400"><?= (int) $stats['buyer_wins_30d'] ?> for buyer</div></div>
        <div class="bg-white rounded-lg shadow p-4"><div class="text-xs uppercase text-gray-400">Avg time (30d)</div><div class="text-xl font-bold"><?= $stats['avg_hours_30d'] !== null ? round($stats['avg_hours_30d']) . 'h' : '—' ?></div></div>
    </div>

    <div class="flex gap-4 text-sm mb-4">
        <?php foreach (['active' => 'Active', 'resolved' => 'Resolved', 'all' => 'All'] as $k => $label): ?>
            <a href="?f=<?= $k ?>" class="<?= $filter === $k ? 'font-semibold text-blue-600' : 'text-gray-600' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($list)): ?>
        <div class="bg-white rounded-lg shadow p-12 text-center text-gray-400">No disputes here.</div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-gray-500">
                    <tr><th class="p-3">#</th><th class="p-3">Order</th><th class="p-3">Seller</th><th class="p-3">Reason</th><th class="p-3 text-right">Amount</th><th class="p-3">Priority</th><th class="p-3">Status</th><th class="p-3">Opened</th></tr>
                </thead>
                <tbody class="divide-y">
                <?php foreach ($list as $row): $overdue = $row['status'] === 'open' && strtotime($row['seller_due_at']) < time(); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3"><a href="?id=<?= (int) $row['id'] ?>" class="text-blue-600 hover:underline"><?= (int) $row['id'] ?></a></td>
                        <td class="p-3">#<?= (int) $row['order_id'] ?></td>
                        <td class="p-3"><?= htmlspecialchars($row['seller_name']) ?></td>
                        <td class="p-3"><?= htmlspecialchars(DisputeService::REASONS[$row['reason']] ?? $row['reason']) ?></td>
                        <td class="p-3 text-right">$<?= number_format((float) $row['disputed_amount'], 2) ?></td>
                        <td class="p-3"><?= htmlspecialchars($row['priority']) ?></td>
                        <td class="p-3 <?= $row['status'] === 'under_review' ? 'text-purple-700 font-semibold' : ($overdue ? 'text-red-600' : '') ?>">
                            <?= htmlspecialchars(str_replace('_', ' ', $row['status'])) ?><?= $overdue ? ' (seller overdue)' : '' ?>
                        </td>
                        <td class="p-3 whitespace-nowrap"><?= date('M j', strtotime($row['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>
</body>
</html>
