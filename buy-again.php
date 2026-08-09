<?php
/**
 * Buy again: re-add a past order's items (or one product) to the cart.
 * POST: order_id [, product_id] — must be the customer's own order.
 */
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'services/CartService.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: orders.php'); exit;
}

$orderId = (int) ($_POST['order_id'] ?? 0);
$onlyProduct = (int) ($_POST['product_id'] ?? 0);

// Items from the customer's own order, joined to live stock.
$stmt = $pdo->prepare("
    SELECT oi.product_id, oi.quantity, p.inventory
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    JOIN products p ON oi.product_id = p.id
    WHERE o.id = ? AND o.user_id = ?" . ($onlyProduct ? " AND oi.product_id = ?" : ""));
$args = [$orderId, $_SESSION['user_id']];
if ($onlyProduct) $args[] = $onlyProduct;
$stmt->execute($args);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$added = 0; $skipped = 0;
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
foreach ($items as $it) {
    $pid = (int) $it['product_id'];
    $want = ($_SESSION['cart'][$pid] ?? 0) + (int) $it['quantity'];
    $capped = min($want, (int) $it['inventory']);
    if ($capped > 0) { $_SESSION['cart'][$pid] = $capped; $added++; }
    else $skipped++;
}
(new CartService($pdo))->persist($_SESSION['user_id']);

$msg = $added ? "$added item(s) added to your cart" . ($skipped ? " ($skipped out of stock)" : '') : 'Those items are currently out of stock';
header('Location: cart.php?notice=' . urlencode($msg));
exit;
