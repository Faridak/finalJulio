<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'services/ReturnService.php';

if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$userId = $_SESSION['user_id'];
$orderId = intval($_GET['order_id'] ?? 0);
$error = '';
$success = '';

// Load the order (must belong to the current user).
$stmt = $pdo->prepare("SELECT id, status, total, created_at FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $error = 'Order not found.';
} else {
    $itemStmt = $pdo->prepare("
        SELECT oi.id, oi.product_id, oi.quantity, oi.price_at_purchase, p.name
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $itemStmt->execute([$orderId]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order) {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(419);
        die('CSRF token mismatch. Please refresh and try again.');
    }

    $reason = Security::sanitizeInput($_POST['reason'] ?? '', 'string');
    $selected = [];
    foreach ($_POST['return_qty'] ?? [] as $orderItemId => $qty) {
        $qty = (int) $qty;
        if ($qty > 0) {
            $selected[] = ['order_item_id' => (int) $orderItemId, 'quantity' => $qty];
        }
    }

    if (empty($selected)) {
        $error = 'Select at least one item and quantity to return.';
    } else {
        $service = new ReturnService($pdo);
        $result = $service->createReturn($orderId, $userId, $selected, $reason);
        if ($result['success']) {
            $success = 'Return request submitted. Refund on approval: $' . number_format($result['refund_amount'], 2);
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request a Return - VentDepot</title>
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-3xl mx-auto px-4 py-10">
        <a href="orders.php" class="text-blue-600 hover:underline text-sm">&larr; Back to orders</a>
        <h1 class="text-3xl font-bold text-gray-900 mt-4 mb-6">Request a Return</h1>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($order && !$success): ?>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-gray-600 mb-4">Order #<?= (int) $order['id'] ?> &middot; placed <?= htmlspecialchars(date('M j, Y', strtotime($order['created_at']))) ?></p>
                <form method="POST">
                    <?= Security::getCSRFInput() ?>
                    <table class="w-full text-sm mb-6">
                        <thead>
                            <tr class="text-left text-gray-500 border-b">
                                <th class="py-2">Item</th>
                                <th class="py-2">Purchased</th>
                                <th class="py-2">Unit price</th>
                                <th class="py-2">Return qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $it): ?>
                            <tr class="border-b">
                                <td class="py-3"><?= htmlspecialchars($it['name'] ?? ('Product #' . $it['product_id'])) ?></td>
                                <td class="py-3"><?= (int) $it['quantity'] ?></td>
                                <td class="py-3">$<?= number_format((float) $it['price_at_purchase'], 2) ?></td>
                                <td class="py-3">
                                    <input type="number" name="return_qty[<?= (int) $it['id'] ?>]" min="0"
                                           max="<?= (int) $it['quantity'] ?>" value="0"
                                           class="w-20 px-2 py-1 border rounded">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <label class="block text-sm font-medium text-gray-700 mb-2">Reason for return</label>
                    <textarea name="reason" rows="3" class="w-full px-3 py-2 border rounded mb-6"
                              placeholder="Tell us what went wrong (optional)"></textarea>

                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                        Submit Return Request
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
