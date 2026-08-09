<?php
/**
 * OXXO payment confirmation webhook (PSP-agnostic).
 * When Conekta/Openpay is connected, point its webhook here.
 *
 * POST JSON: { "reference": "93...", "secret": "<OXXO_WEBHOOK_SECRET>" }
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/OxxoPaymentService.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"error":"POST only"}'; exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$secret = $_ENV['OXXO_WEBHOOK_SECRET'] ?? '';
if ($secret === '' || strpos($secret, 'your_') === 0) { http_response_code(503); echo '{"error":"Webhook not configured (OXXO_WEBHOOK_SECRET)"}'; exit; }
if (!hash_equals($secret, (string) ($in['secret'] ?? ''))) { http_response_code(403); echo '{"error":"Bad secret"}'; exit; }

$r = (new OxxoPaymentService($pdo))->confirmPayment($in['reference'] ?? '', 'webhook');
echo json_encode($r);
