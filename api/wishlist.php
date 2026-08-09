<?php
/**
 * Wishlist API: toggle / remove / list. Logged-in users only.
 * POST JSON: { action: 'toggle'|'remove', product_id, csrf_token }
 */
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

header('Content-Type: application/json');
if (!isLoggedIn()) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Login to save items']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?: [];
if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'CSRF token mismatch']); exit;
}

$uid = (int) $_SESSION['user_id'];
$pid = (int) ($input['product_id'] ?? 0);
$action = $input['action'] ?? '';

if ($action === 'toggle' && $pid > 0) {
    $has = $pdo->prepare("SELECT id FROM wishlist_items WHERE user_id=? AND product_id=?");
    $has->execute([$uid, $pid]);
    if ($row = $has->fetchColumn()) {
        $pdo->prepare("DELETE FROM wishlist_items WHERE id=?")->execute([$row]);
        echo json_encode(['success' => true, 'in_wishlist' => false]);
    } else {
        $pdo->prepare("INSERT INTO wishlist_items (user_id, product_id) VALUES (?,?)")->execute([$uid, $pid]);
        echo json_encode(['success' => true, 'in_wishlist' => true]);
    }
} elseif ($action === 'remove' && $pid > 0) {
    $pdo->prepare("DELETE FROM wishlist_items WHERE user_id=? AND product_id=?")->execute([$uid, $pid]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
