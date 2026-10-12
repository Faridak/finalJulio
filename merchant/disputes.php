<?php
/**
 * Seller dispute inbox: see disputes on your items and answer them before the
 * deadline. Buyer contact details are never shown (PII policy).
 */
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/DisputeService.php';

requireRole('merchant');
$mid = (int) $_SESSION['user_id'];
$svc = new DisputeService($pdo);
$flash = $_SESSION['dispute_flash'] ?? null;
unset($_SESSION['dispute_flash']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $disputeId = (int) ($_POST['dispute_id'] ?? 0);
    $res = Security::checkRateLimit('dispute_reply', 60, 3600)
        ? $svc->reply($disputeId, $mid, 'seller', $_POST['body'] ?? '')
        : ['success' => false, 'error' => 'Too many messages. Please slow down.'];
    if ($res['success']) {
        $_SESSION['dispute_flash'] = 'Response sent to the buyer.';
        header('Location: disputes.php?id=' . $disputeId);
        exit;
    }
    $error = $res['error'];
}

$d = !empty($_GET['id']) ? $svc->get((int) $_GET['id'], $mid, 'seller') : null;
if (!empty($_GET['id']) && !$d) $error = 'Dispute not found.';
$filter = in_array($_GET['f'] ?? '', ['active', 'resolved'], true) ? $_GET['f'] : 'active';
$list = $d ? [] : $svc->listFor($mid, 'seller', $filter);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disputes - VentDepot Seller</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<nav class="bg-gray-900 text-white">
    <div class="max-w-6xl mx-auto px-4 h-12 flex items-center gap-4 text-sm">
        <a href="dashboard.php" class="font-bold">VentDepot <span class="text-blue-400">Seller</span></a>
        <a href="dashboard.php" class="hover:text-blue-300">Dashboard</a>
        <a href="products.php" class="hover:text-blue-300">Products</a>
        <a href="orders.php" class="hover:text-blue-300">Orders</a>
        <a href="earnings.php" class="hover:text-blue-300">Earnings</a>
        <a href="disputes.php" class="text-blue-300 font-medium">Disputes</a>
        <a href="../messages.php" class="hover:text-blue-300">Messages</a>
        <a href="../logout.php" class="ml-auto hover:text-blue-300">Logout</a>
    </div>
</nav>
<div class="max-w-4xl mx-auto px-4 py-8">
    <?php if ($flash): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($d): $as = 'seller'; ?>
    <a href="disputes.php" class="text-blue-600 hover:underline text-sm mb-4 inline-block">&larr; All disputes</a>
    <?php include __DIR__ . '/../includes/dispute-thread.php'; ?>
    <?php if (in_array($d['status'], DisputeService::OPEN_STATUSES, true)): ?>
        <form method="POST" class="bg-white rounded-lg shadow p-6">
            <?= Security::getCSRFInput() ?>
            <input type="hidden" name="dispute_id" value="<?= (int) $d['id'] ?>">
            <label class="block text-sm font-medium text-gray-700 mb-2">Your response</label>
            <textarea name="body" rows="4" required maxlength="5000" class="w-full border border-gray-300 rounded-lg px-3 py-2"
                      placeholder="Explain what happened and what you offer (refund, replacement, tracking proof…)"></textarea>
            <p class="text-xs text-gray-500 mt-1">Keep the conversation on VentDepot — decisions are based on what's written here.</p>
            <button class="mt-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">Send response</button>
        </form>
    <?php endif; ?>
<?php else: ?>
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Disputes</h1>
    <p class="text-sm text-gray-500 mb-6">Respond within <?= DisputeService::SELLER_DAYS ?> days. Unanswered disputes can be decided against you, and refunds affect your credibility tier.</p>
    <div class="flex gap-4 text-sm mb-4">
        <a href="?f=active" class="<?= $filter === 'active' ? 'font-semibold text-blue-600' : 'text-gray-600' ?>">Active</a>
        <a href="?f=resolved" class="<?= $filter === 'resolved' ? 'font-semibold text-blue-600' : 'text-gray-600' ?>">Resolved</a>
    </div>
    <?php if (empty($list)): ?>
        <div class="bg-white rounded-lg shadow p-12 text-center text-gray-400">No <?= $filter ?> disputes.</div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow divide-y">
            <?php foreach ($list as $row): $overdue = $row['status'] === 'open' && strtotime($row['seller_due_at']) < time(); ?>
                <a href="disputes.php?id=<?= (int) $row['id'] ?>" class="block p-4 hover:bg-gray-50">
                    <div class="flex justify-between gap-4">
                        <div>
                            <div class="font-medium text-gray-900">#<?= (int) $row['id'] ?> · <?= htmlspecialchars(DisputeService::REASONS[$row['reason']] ?? $row['reason']) ?></div>
                            <div class="text-sm text-gray-500">Order #<?= str_pad((int) $row['order_id'], 6, '0', STR_PAD_LEFT) ?> · $<?= number_format((float) $row['disputed_amount'], 2) ?></div>
                        </div>
                        <div class="text-sm whitespace-nowrap <?= $overdue ? 'text-red-600 font-semibold' : 'text-gray-600' ?>">
                            <?= $row['status'] === 'open' ? 'Reply by ' . date('M j', strtotime($row['seller_due_at'])) : htmlspecialchars(str_replace('_', ' ', $row['status'])) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</div>
<script src="../public/vendor/js/motion.min.js" defer></script>
</body>
</html>
