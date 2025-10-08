<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/NotificationSystem.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$notificationSystem = new NotificationSystem($pdo);

// Handle different request methods
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'poll':
                    // Real-time polling for new notifications
                    $lastCheck = $_GET['last_check'] ?? null;
                    $data = $notificationSystem->getPollingData($userId, $lastCheck);
                    echo json_encode(['success' => true, 'data' => $data]);
                    break;
                    
                case 'list':
                    // Get paginated notifications
                    $page = max(1, intval($_GET['page'] ?? 1));
                    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
                    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
                    
                    $notifications = $notificationSystem->getUserNotifications($userId, $page, $limit, $unreadOnly);
                    $unreadCount = $notificationSystem->getUnreadCount($userId);
                    
                    echo json_encode([
                        'success' => true,
                        'notifications' => $notifications,
                        'unread_count' => $unreadCount,
                        'page' => $page
                    ]);
                    break;
                    
                case 'count':
                    // Get unread count only
                    $count = $notificationSystem->getUnreadCount($userId);
                    echo json_encode(['success' => true, 'unread_count' => $count]);
                    break;
                    
                case 'stats':
                    // Get notification statistics
                    $stats = $notificationSystem->getNotificationStats($userId);
                    echo json_encode(['success' => true, 'stats' => $stats]);
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        case 'POST':
            // CSRF Protection for POST requests
            if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
                throw new Exception('CSRF token mismatch');
            }
            
            switch ($action) {
                case 'mark_read':
                    $notificationId = intval($_POST['notification_id'] ?? 0);
                    
                    if (!$notificationId) {
                        throw new Exception('Invalid notification ID');
                    }
                    
                    $result = $notificationSystem->markAsRead($notificationId, $userId);
                    echo json_encode(['success' => $result]);
                    break;
                    
                case 'mark_all_read':
                    $result = $notificationSystem->markAllAsRead($userId);
                    echo json_encode(['success' => $result]);
                    break;
                    
                case 'delete':
                    $notificationId = intval($_POST['notification_id'] ?? 0);
                    
                    if (!$notificationId) {
                        throw new Exception('Invalid notification ID');
                    }
                    
                    $result = $notificationSystem->deleteNotification($notificationId, $userId);
                    echo json_encode(['success' => $result]);
                    break;
                    
                case 'test':
                    // Create a test notification (development only)
                    if ($_SESSION['user_role'] === 'admin') {
                        $result = $notificationSystem->createNotification(
                            $userId,
                            'system',
                            'Test Notification',
                            'This is a test notification created at ' . date('Y-m-d H:i:s'),
                            null,
                            null,
                            ['test' => true]
                        );
                        echo json_encode(['success' => (bool)$result, 'notification_id' => $result]);
                    } else {
                        throw new Exception('Permission denied');
                    }
                    break;
                    
                default:
                    throw new Exception('Invalid action');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>