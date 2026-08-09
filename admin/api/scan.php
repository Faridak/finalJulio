<?php
/**
 * Scan API — JSON endpoint for the barcode console.
 * Actions: in (put-away), out (pick/ship), count (bin reconcile).
 */
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../services/ScanService.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserRole() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

if (!Security::validateCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'CSRF token mismatch']);
    exit;
}

$scan = new ScanService($pdo);
$userId = $_SESSION['user_id'];
$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'in':
            echo json_encode($scan->scanIn($input['item'] ?? '', $input['bin'] ?? '', $input['qty'] ?? 1, $userId));
            break;
        case 'out':
            echo json_encode($scan->scanOut($input['item'] ?? '', $input['qty'] ?? 1, $userId, $input['bin'] ?? null, 'Scan out'));
            break;
        case 'count':
            echo json_encode($scan->countBin($input['bin'] ?? '', $input['items'] ?? [], $userId, !empty($input['apply'])));
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
