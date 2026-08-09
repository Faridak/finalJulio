<?php
/**
 * PayPal webhook — safety net for payments approved but never posted back
 * (buyer closed the browser between approval and our capture confirmation).
 *
 * Point both PayPal apps' webhooks (event PAYMENT.CAPTURE.COMPLETED) here.
 *
 * SECURITY MODEL: we never trust the webhook payload. We take only the order
 * id hint from it, then ask PayPal's API (with our own credentials) what the
 * real state of that order's capture is, and verify the amount — the same
 * server-side verification the normal checkout path uses.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/PaymentGateway.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"error":"POST only"}'; exit; }

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$event = $payload['event_type'] ?? '';
if (!in_array($event, ['PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED'], true)) {
    echo '{"ok":true,"ignored":"' . htmlspecialchars($event) . '"}'; exit;   // ack unrelated events
}

// Order hints from the payload (custom_id = our order id, set at creation).
$res = $payload['resource'] ?? [];
$ourOrderId = (int) ($res['custom_id'] ?? ($res['purchase_units'][0]['custom_id'] ?? 0));
$paypalOrderId = $res['supplementary_data']['related_ids']['order_id']
    ?? ($event === 'CHECKOUT.ORDER.APPROVED' ? ($res['id'] ?? '') : '');

if (!$ourOrderId) { echo '{"ok":true,"ignored":"no custom_id"}'; exit; }

// Already paid? idempotent ack.
$o = $pdo->prepare("SELECT payment_status FROM orders WHERE id = ?");
$o->execute([$ourOrderId]);
$status = $o->fetchColumn();
if ($status === false) { echo '{"ok":true,"ignored":"unknown order"}'; exit; }
if ($status === 'paid') { echo '{"ok":true,"already":"paid"}'; exit; }

if ($paypalOrderId === '') { echo '{"ok":true,"ignored":"no paypal order id"}'; exit; }

// Server-side verification + completion through the SAME path as checkout:
// processPayment captures (or confirms an existing capture), verifies the
// amount/currency against OUR order, then runs the full orchestration.
$gateway = new PaymentGateway($pdo);
$result = $gateway->processPayment($ourOrderId, 'paypal', ['paypal_order_id' => $paypalOrderId]);

Security::logSecurityEvent('paypal_webhook', [
    'event' => $event, 'order_id' => $ourOrderId,
    'result' => !empty($result['success']) ? 'completed' : ($result['error'] ?? 'failed'),
]);

echo json_encode(['ok' => true, 'processed' => !empty($result['success'])]);
