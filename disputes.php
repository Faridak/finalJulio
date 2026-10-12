<?php
/**
 * Buyer dispute center: list, open a dispute on an order, view / reply /
 * escalate / withdraw. Seller side: merchant/disputes.php. Admin decisions:
 * admin/dispute-management.php.
 */
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'services/DisputeService.php';

requireLogin();
$userId = (int) $_SESSION['user_id'];
$svc = new DisputeService($pdo);
$flash = $_SESSION['dispute_flash'] ?? null;
unset($_SESSION['dispute_flash']);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    $disputeId = (int) ($_POST['dispute_id'] ?? 0);
    $res = ['success' => false, 'error' => 'Unknown action.'];

    if ($action === 'open') {
        if (!Security::checkRateLimit('open_dispute', 5, 86400)) {
            $res = ['success' => false, 'error' => 'Too many disputes opened today. Please contact support.'];
        } else {
            $res = $svc->open($userId, (int) $_POST['order_id'], (int) $_POST['merchant_id'], $_POST['reason'] ?? '',
                $_POST['description'] ?? '', $_POST['requested_outcome'] ?? 'full_refund', $_POST['amount_requested'] ?? null);
            if ($res['success']) $disputeId = $res['dispute_id'];
        }
    } elseif ($action === 'reply') {
        $res = Security::checkRateLimit('dispute_reply', 30, 3600)
            ? $svc->reply($disputeId, $userId, 'buyer', $_POST['body'] ?? '')
            : ['success' => false, 'error' => 'Too many messages. Please slow down.'];
    } elseif ($action === 'escalate') {
        $res = $svc->escalate($disputeId, $userId);
    } elseif ($action === 'withdraw') {
        $res = $svc->withdraw($disputeId, $userId);
    }

    if ($res['success']) {
        $_SESSION['dispute_flash'] = ['open' => 'Dispute opened. The seller has ' . DisputeService::SELLER_DAYS . ' days to respond.',
            'reply' => 'Message sent.', 'escalate' => 'Escalated — VentDepot will review and decide.',
            'withdraw' => 'Dispute withdrawn.'][$action] ?? 'Done.';
        header('Location: disputes.php?id=' . $disputeId);
        exit;
    }
    $error = $res['error'];
    if (!empty($res['dispute_id'])) { header('Location: disputes.php?id=' . (int) $res['dispute_id']); exit; }
}

$view = 'list';
$d = null;
if (!empty($_GET['id'])) {
    $d = $svc->get((int) $_GET['id'], $userId, 'buyer');
    $view = $d ? 'detail' : 'list';
    if (!$d) $error = 'Dispute not found.';
} elseif (!empty($_GET['order'])) {
    $orderId = (int) $_GET['order'];
    $o = $pdo->prepare("SELECT id, status, payment_status, created_at FROM orders WHERE id = ? AND user_id = ?");
    $o->execute([$orderId, $userId]);
    $order = $o->fetch();
    if ($order) {
        $view = 'open';
        $ineligible = $svc->ineligibleReason($order);
        $sellers = $svc->disputableSellers($orderId, $userId);
    } else {
        $error = 'Order not found.';
    }
}
$list = $view === 'list' ? $svc->listFor($userId, 'buyer') : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Disputes - VentDepot</title>
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
    <link rel="stylesheet" href="public/vendor/css/fontawesome.min.css">
</head>
<body class="bg-gray-50">
<?php include 'includes/navigation.php'; ?>
<main class="container mx-auto px-4 py-8 max-w-4xl">
    <?php if ($flash): ?><div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($view === 'detail'): $as = 'buyer'; ?>
    <a href="disputes.php" class="text-blue-600 hover:underline text-sm mb-4 inline-block"><i class="fas fa-arrow-left mr-1"></i> All disputes</a>
    <?php include 'includes/dispute-thread.php'; ?>

    <?php if (in_array($d['status'], DisputeService::OPEN_STATUSES, true)): ?>
        <form method="POST" class="bg-white rounded-lg shadow p-6 mb-6">
            <?= Security::getCSRFInput() ?>
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="dispute_id" value="<?= (int) $d['id'] ?>">
            <label class="block text-sm font-medium text-gray-700 mb-2">Add a message</label>
            <textarea name="body" rows="3" required maxlength="5000" class="w-full border border-gray-300 rounded-lg px-3 py-2"
                      placeholder="Add details, tracking info, or a photo link…"></textarea>
            <button class="mt-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">Send</button>
        </form>
        <div class="flex flex-wrap gap-3">
            <?php if ($d['status'] === 'seller_responded' || $d['seller_overdue']): ?>
                <form method="POST" onsubmit="return confirm('Ask VentDepot to review and decide this dispute?')">
                    <?= Security::getCSRFInput() ?>
                    <input type="hidden" name="action" value="escalate"><input type="hidden" name="dispute_id" value="<?= (int) $d['id'] ?>">
                    <button class="bg-purple-600 hover:bg-purple-700 text-white font-semibold py-2 px-4 rounded">Ask VentDepot to decide</button>
                </form>
            <?php endif; ?>
            <form method="POST" onsubmit="return confirm('Withdraw this dispute? This cannot be undone.')">
                <?= Security::getCSRFInput() ?>
                <input type="hidden" name="action" value="withdraw"><input type="hidden" name="dispute_id" value="<?= (int) $d['id'] ?>">
                <button class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold py-2 px-4 rounded">Withdraw dispute</button>
            </form>
        </div>
    <?php endif; ?>

<?php elseif ($view === 'open'): ?>
    <a href="order-details.php?id=<?= (int) $orderId ?>" class="text-blue-600 hover:underline text-sm mb-4 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to order</a>
    <h1 class="text-3xl font-bold text-gray-800 mb-2">Report a problem</h1>
    <p class="text-sm text-gray-500 mb-6">Order #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?>. The seller gets <?= DisputeService::SELLER_DAYS ?> days to respond; if you can't agree, VentDepot decides.</p>

    <?php if ($ineligible): ?>
        <div class="bg-white rounded-lg shadow p-6 text-gray-700"><?= htmlspecialchars($ineligible) ?></div>
    <?php else: ?>
        <form method="POST" class="bg-white rounded-lg shadow p-6 space-y-4">
            <?= Security::getCSRFInput() ?>
            <input type="hidden" name="action" value="open">
            <input type="hidden" name="order_id" value="<?= (int) $orderId ?>">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Seller</label>
                <select name="merchant_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <?php foreach ($sellers as $s): ?>
                        <option value="<?= (int) $s['merchant_id'] ?>" <?= $s['open_dispute_id'] ? 'disabled' : '' ?>>
                            <?= htmlspecialchars($s['seller_name']) ?> — <?= (int) $s['items'] ?> item(s), $<?= number_format($s['subtotal'], 2) ?><?= $s['open_dispute_id'] ? ' (dispute already open)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">What went wrong?</label>
                <select name="reason" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <?php foreach (DisputeService::REASONS as $k => $label): ?><option value="<?= $k ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Describe the problem</label>
                <textarea name="description" rows="5" required minlength="20" maxlength="5000" class="w-full border border-gray-300 rounded-lg px-3 py-2"
                          placeholder="What happened, when it arrived, what you expected… (min. 20 characters)"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">What would resolve it?</label>
                    <select name="requested_outcome" id="outcome" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <?php foreach (DisputeService::OUTCOMES as $k => $label): ?><option value="<?= $k ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div id="amountWrap" class="hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount requested ($)</label>
                    <input type="number" name="amount_requested" step="0.01" min="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
            </div>
            <button class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded">Open dispute</button>
        </form>
        <script>
            document.getElementById('outcome').addEventListener('change', function () {
                document.getElementById('amountWrap').classList.toggle('hidden', this.value !== 'partial_refund');
            });
        </script>
    <?php endif; ?>

<?php else: ?>
    <h1 class="text-3xl font-bold text-gray-800 mb-2">My Disputes</h1>
    <p class="text-sm text-gray-500 mb-6">Problem with an order? Open a dispute from the order's page — <a href="orders.php" class="text-blue-600 hover:underline">My Orders</a>.</p>
    <?php if (empty($list)): ?>
        <div class="bg-white rounded-lg shadow p-12 text-center text-gray-400">No disputes. 🎉</div>
    <?php else: ?>
        <div class="bg-white rounded-lg shadow divide-y">
            <?php foreach ($list as $row): ?>
                <a href="disputes.php?id=<?= (int) $row['id'] ?>" class="block p-4 hover:bg-gray-50">
                    <div class="flex justify-between gap-4">
                        <div>
                            <div class="font-medium text-gray-900">#<?= (int) $row['id'] ?> · <?= htmlspecialchars(DisputeService::REASONS[$row['reason']] ?? $row['reason']) ?></div>
                            <div class="text-sm text-gray-500">Order #<?= str_pad((int) $row['order_id'], 6, '0', STR_PAD_LEFT) ?> · <?= htmlspecialchars($row['seller_name']) ?> · $<?= number_format((float) $row['disputed_amount'], 2) ?></div>
                        </div>
                        <div class="text-sm text-gray-600 whitespace-nowrap"><?= htmlspecialchars(str_replace('_', ' ', $row['status'])) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
</main>
</body>
</html>
