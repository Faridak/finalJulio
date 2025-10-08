<?php
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/NotificationSystem.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];
$notificationSystem = new NotificationSystem($pdo);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('CSRF token mismatch');
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'mark_read':
            $notificationId = intval($_POST['notification_id']);
            $notificationSystem->markAsRead($notificationId, $userId);
            break;
            
        case 'mark_all_read':
            $notificationSystem->markAllAsRead($userId);
            break;
            
        case 'delete':
            $notificationId = intval($_POST['notification_id']);
            $notificationSystem->deleteNotification($notificationId, $userId);
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF'] . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit;
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;

// Get notifications
$unreadOnly = $filter === 'unread';
$notifications = $notificationSystem->getUserNotifications($userId, $page, $limit, $unreadOnly);
$unreadCount = $notificationSystem->getUnreadCount($userId);
$stats = $notificationSystem->getNotificationStats($userId);

// Calculate pagination
$totalNotifications = $unreadOnly ? $stats['unread_count'] : $stats['total_notifications'];
$totalPages = ceil($totalNotifications / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Notifications</h1>
            <p class="text-gray-600 mt-2">Stay updated with your marketplace activity</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-blue-100 rounded-lg">
                        <i class="fas fa-bell text-blue-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?= number_format($stats['total_notifications']) ?></h3>
                        <p class="text-gray-600 text-sm">Total</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-red-100 rounded-lg">
                        <i class="fas fa-exclamation-circle text-red-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?= number_format($stats['unread_count']) ?></h3>
                        <p class="text-gray-600 text-sm">Unread</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-green-100 rounded-lg">
                        <i class="fas fa-shopping-cart text-green-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?= number_format($stats['order_notifications']) ?></h3>
                        <p class="text-gray-600 text-sm">Orders</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="p-2 bg-purple-100 rounded-lg">
                        <i class="fas fa-envelope text-purple-600"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-gray-900"><?= number_format($stats['message_notifications']) ?></h3>
                        <p class="text-gray-600 text-sm">Messages</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="bg-white rounded-lg shadow-md mb-6">
            <div class="p-6 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                    <!-- Filters -->
                    <div class="flex space-x-2">
                        <a href="?filter=all" 
                           class="px-4 py-2 text-sm font-medium rounded-lg <?= $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                            All
                        </a>
                        <a href="?filter=unread" 
                           class="px-4 py-2 text-sm font-medium rounded-lg <?= $filter === 'unread' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                            Unread (<?= $unreadCount ?>)
                        </a>
                    </div>
                    
                    <!-- Actions -->
                    <?php if ($unreadCount > 0): ?>
                        <form method="POST" class="inline">
                            <?= Security::getCSRFInput() ?>
                            <input type="hidden" name="action" value="mark_all_read">
                            <button type="submit" 
                                    class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
                                <i class="fas fa-check mr-2"></i>
                                Mark All Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Notifications List -->
            <div class="divide-y divide-gray-200">
                <?php if (empty($notifications)): ?>
                    <div class="p-8 text-center">
                        <i class="fas fa-bell-slash text-gray-300 text-4xl mb-4"></i>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">
                            <?= $filter === 'unread' ? 'No unread notifications' : 'No notifications' ?>
                        </h3>
                        <p class="text-gray-600">
                            <?= $filter === 'unread' ? 'All caught up!' : 'You\'ll see your notifications here when you have them.' ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="p-6 <?= !$notification['is_read'] ? 'bg-blue-50 border-l-4 border-blue-500' : '' ?>">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4 flex-1">
                                    <!-- Icon -->
                                    <div class="flex-shrink-0 mt-1">
                                        <?php
                                        $iconClasses = [
                                            'order' => 'fas fa-shopping-cart text-green-600',
                                            'payment' => 'fas fa-credit-card text-blue-600',
                                            'message' => 'fas fa-envelope text-purple-600',
                                            'review' => 'fas fa-star text-yellow-600',
                                            'shipping' => 'fas fa-truck text-orange-600',
                                            'system' => 'fas fa-cog text-gray-600',
                                            'promotion' => 'fas fa-tag text-red-600'
                                        ];
                                        $iconClass = $iconClasses[$notification['type']] ?? 'fas fa-bell text-gray-600';
                                        ?>
                                        <div class="p-2 bg-white rounded-lg shadow-sm">
                                            <i class="<?= $iconClass ?>"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Content -->
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-2">
                                            <h3 class="text-lg font-medium text-gray-900">
                                                <?= htmlspecialchars($notification['title']) ?>
                                            </h3>
                                            <?php if ($notification['is_important']): ?>
                                                <span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full font-medium">
                                                    Important
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!$notification['is_read']): ?>
                                                <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="text-gray-700 mt-2"><?= nl2br(htmlspecialchars($notification['message'])) ?></p>
                                        
                                        <div class="flex items-center space-x-4 mt-3">
                                            <span class="text-sm text-gray-500">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?= timeAgo($notification['created_at']) ?>
                                            </span>
                                            
                                            <?php if ($notification['action_url']): ?>
                                                <a href="<?= htmlspecialchars($notification['action_url']) ?>" 
                                                   class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                                                    <?= htmlspecialchars($notification['action_text'] ?: 'View') ?>
                                                    <i class="fas fa-arrow-right ml-1"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex items-center space-x-2 ml-4">
                                    <?php if (!$notification['is_read']): ?>
                                        <form method="POST" class="inline">
                                            <?= Security::getCSRFInput() ?>
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                            <button type="submit" 
                                                    class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                Mark as read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this notification?')">
                                        <?= Security::getCSRFInput() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                        <button type="submit" 
                                                class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-center">
                    <nav class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?filter=<?= urlencode($filter) ?>&page=<?= $page - 1 ?>" 
                               class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?filter=<?= urlencode($filter) ?>&page=<?= $i ?>" 
                               class="px-3 py-2 text-sm <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?> rounded">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?filter=<?= urlencode($filter) ?>&page=<?= $page + 1 ?>" 
                               class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200">
                                Next
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
                
                <div class="text-center mt-4 text-sm text-gray-600">
                    Showing <?= min(($page - 1) * $limit + 1, $totalNotifications) ?>-<?= min($page * $limit, $totalNotifications) ?> of <?= number_format($totalNotifications) ?> notifications
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minute' . (floor($time/60) != 1 ? 's' : '') . ' ago';
    if ($time < 86400) return floor($time/3600) . ' hour' . (floor($time/3600) != 1 ? 's' : '') . ' ago';
    if ($time < 2592000) return floor($time/86400) . ' day' . (floor($time/86400) != 1 ? 's' : '') . ' ago';
    
    return date('M j, Y \a\t g:i A', strtotime($datetime));
}
?>