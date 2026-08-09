<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../services/ReturnService.php';

// Admin only.
requireRole('admin');
requireScope('orders');

$service = new ReturnService($pdo);
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        die('CSRF token mismatch. Please refresh and try again.');
    }

    $returnId = (int) ($_POST['return_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'approve') {
        $service->setStatus($returnId, 'approved');
        $notice = "Return #$returnId approved.";
    } elseif ($action === 'reject') {
        $service->setStatus($returnId, 'rejected');
        $notice = "Return #$returnId rejected.";
    } elseif ($action === 'complete') {
        $result = $service->completeReturn($returnId);
        if ($result['success']) {
            $notice = "Return #$returnId completed — refunded $" . number_format($result['refund_amount'], 2)
                . ", inventory restocked and ledger reversed.";
        } else {
            $error = $result['error'];
        }
    }
}

$returns = $pdo->query("
    SELECT rr.*, u.email AS customer_email,
           (SELECT COUNT(*) FROM return_request_items WHERE return_request_id = rr.id) AS item_count
    FROM return_requests rr
    LEFT JOIN users u ON rr.user_id = u.id
    ORDER BY rr.created_at DESC
    LIMIT 200
")->fetchAll(PDO::FETCH_ASSOC);

$badge = [
    'requested' => 'bg-yellow-100 text-yellow-800',
    'approved'  => 'bg-blue-100 text-blue-800',
    'received'  => 'bg-indigo-100 text-indigo-800',
    'refunded'  => 'bg-green-100 text-green-800',
    'rejected'  => 'bg-red-100 text-red-800',
    'cancelled' => 'bg-gray-100 text-gray-800',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns Management - VentDepot Admin</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
    <div class="max-w-6xl mx-auto px-4 py-10">
        <h1 class="text-3xl font-bold text-gray-900 mb-6">Returns Management</h1>

        <?php if ($notice): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($notice) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 bg-gray-50 border-b">
                        <th class="px-4 py-3">Return</th>
                        <th class="px-4 py-3">Order</th>
                        <th class="px-4 py-3">Customer</th>
                        <th class="px-4 py-3">Items</th>
                        <th class="px-4 py-3">Refund</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No return requests yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($returns as $r): ?>
                    <tr class="border-b">
                        <td class="px-4 py-3">#<?= (int) $r['id'] ?></td>
                        <td class="px-4 py-3">#<?= (int) $r['order_id'] ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($r['customer_email'] ?? '—') ?></td>
                        <td class="px-4 py-3"><?= (int) $r['item_count'] ?></td>
                        <td class="px-4 py-3">$<?= number_format((float) $r['refund_amount'], 2) ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-full text-xs <?= $badge[$r['status']] ?? 'bg-gray-100 text-gray-800' ?>">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <?php if (in_array($r['status'], ['requested', 'approved', 'received'], true)): ?>
                            <form method="POST" class="inline-flex gap-2">
                                <?= Security::getCSRFInput() ?>
                                <input type="hidden" name="return_id" value="<?= (int) $r['id'] ?>">
                                <?php if ($r['status'] === 'requested'): ?>
                                    <button name="action" value="approve" class="text-blue-600 hover:underline">Approve</button>
                                    <button name="action" value="reject" class="text-red-600 hover:underline">Reject</button>
                                <?php endif; ?>
                                <?php if (in_array($r['status'], ['approved', 'received'], true)): ?>
                                    <button name="action" value="complete" class="text-green-700 font-medium hover:underline">Complete &amp; Refund</button>
                                <?php endif; ?>
                            </form>
                            <?php else: ?>
                                <span class="text-gray-400">—</span>
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
