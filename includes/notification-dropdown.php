<?php
// Notification Dropdown Component
// Include this in navigation.php

if (!isLoggedIn()) {
    return;
}

require_once __DIR__ . '/NotificationSystem.php';
$notificationSystem = new NotificationSystem($pdo);
$unreadCount = $notificationSystem->getUnreadCount($_SESSION['user_id']);
$recentNotifications = $notificationSystem->getUserNotifications($_SESSION['user_id'], 1, 5, false);
?>

<!-- Notifications Dropdown -->
<div class="relative" x-data="{ open: false, unreadCount: <?= $unreadCount ?>, notifications: [] }" x-init="pollNotifications()">
    <!-- Notification Bell -->
    <button @click="open = !open" 
            class="relative p-2 text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600">
        <i class="fas fa-bell text-xl"></i>
        <!-- Unread Badge -->
        <span x-show="unreadCount > 0" 
              x-text="unreadCount > 99 ? '99+' : unreadCount"
              class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">
        </span>
    </button>

    <!-- Dropdown Panel -->
    <div x-show="open" 
         @click.away="open = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 z-50"
         style="display: none;">
         
        <!-- Header -->
        <div class="p-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900">Notifications</h3>
                <div class="flex items-center space-x-2">
                    <button @click="markAllAsRead()" 
                            x-show="unreadCount > 0"
                            class="text-sm text-blue-600 hover:text-blue-800">
                        Mark all read
                    </button>
                    <a href="notifications.php" class="text-sm text-gray-600 hover:text-gray-800">
                        View all
                    </a>
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="max-h-96 overflow-y-auto">
            <?php if (empty($recentNotifications)): ?>
                <div class="p-4 text-center text-gray-500">
                    <i class="fas fa-bell-slash text-2xl mb-2"></i>
                    <p>No notifications</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentNotifications as $notification): ?>
                    <div class="p-4 border-b border-gray-100 hover:bg-gray-50 cursor-pointer notification-item <?= !$notification['is_read'] ? 'bg-blue-50' : '' ?>"
                         data-notification-id="<?= $notification['id'] ?>"
                         onclick="handleNotificationClick(<?= $notification['id'] ?>, '<?= addslashes($notification['action_url'] ?? '') ?>')">
                        <div class="flex items-start space-x-3">
                            <!-- Icon -->
                            <div class="flex-shrink-0">
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
                                <i class="<?= $iconClass ?>"></i>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($notification['title']) ?>
                                </p>
                                <p class="text-sm text-gray-600 mt-1">
                                    <?= htmlspecialchars($notification['message']) ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?= timeAgo($notification['created_at']) ?>
                                </p>
                            </div>
                            
                            <!-- Unread Indicator -->
                            <?php if (!$notification['is_read']): ?>
                                <div class="flex-shrink-0">
                                    <div class="w-2 h-2 bg-blue-600 rounded-full"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-gray-200">
            <a href="notifications.php" 
               class="block w-full text-center text-sm text-blue-600 hover:text-blue-800 font-medium">
                View All Notifications
            </a>
        </div>
    </div>
</div>

<script>
// Notification functionality
function pollNotifications() {
    // Initial load
    updateNotificationCount();
    
    // Poll every 30 seconds
    setInterval(updateNotificationCount, 30000);
}

function updateNotificationCount() {
    fetch('/api/notifications.php?action=count')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update Alpine.js data
                this.unreadCount = data.unread_count;
            }
        })
        .catch(error => console.error('Failed to update notification count:', error));
}

function handleNotificationClick(notificationId, actionUrl) {
    // Mark as read
    const formData = new FormData();
    formData.append('action', 'mark_read');
    formData.append('notification_id', notificationId);
    formData.append('csrf_token', '<?= Security::generateCSRFToken() ?>');
    
    fetch('/api/notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const item = document.querySelector(`.notification-item[data-notification-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('bg-blue-50');
                const indicator = item.querySelector('.w-2.h-2.bg-blue-600');
                if (indicator) {
                    indicator.remove();
                }
            }
            
            // Update count
            updateNotificationCount();
        }
    });
    
    // Navigate if action URL exists
    if (actionUrl) {
        window.location.href = actionUrl;
    }
}

function markAllAsRead() {
    const formData = new FormData();
    formData.append('action', 'mark_all_read');
    formData.append('csrf_token', '<?= Security::generateCSRFToken() ?>');
    
    fetch('/api/notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('bg-blue-50');
                const indicator = item.querySelector('.w-2.h-2.bg-blue-600');
                if (indicator) {
                    indicator.remove();
                }
            });
            
            // Update count
            this.unreadCount = 0;
        }
    })
    .catch(error => console.error('Failed to mark all as read:', error));
}

function timeAgo(datetime) {
    const time = new Date().getTime() - new Date(datetime).getTime();
    const seconds = Math.floor(time / 1000);
    
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
    if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
    if (seconds < 2592000) return Math.floor(seconds / 86400) + 'd ago';
    
    return new Date(datetime).toLocaleDateString();
}
</script>