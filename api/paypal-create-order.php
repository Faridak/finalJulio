<?php
/**
 * Create a PayPal order (Orders v2) for one of OUR pending orders.
 * The server sets the amount from the order row — the browser never chooses it.
 * Returns { id } for the PayPal JS SDK's createOrder callback.
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/PaymentGateway.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { http_response_code(401); echo json_encode(['error' => 'Login required']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['error' => 'CSRF token mismatch']); exit;
}

$orderId = (int) ($input['order_id'] ?? 0);

// The order must belong to the logged-in customer and still be unpaid.
$stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND payment_status <> 'paid' LIMIT 1");
$stmt->execute([$orderId, $_SESSION['user_id']]);
if (!$stmt->fetchColumn()) { http_response_code(404); echo json_encode(['error' => 'Order not found or already paid']); exit; }

$gateway = new PaymentGateway($pdo);
$res = $gateway->createPayPalOrderFor($orderId);

if (!empty($res['id'])) { echo json_encode(['id' => $res['id']]); }
else { http_response_code(502); echo json_encode(['error' => $res['error'] ?? 'PayPal unavailable']); }
