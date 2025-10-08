<?php
/**
 * Real-time Notification System
 * Handles notifications for orders, messages, reviews, and other marketplace events
 */

class NotificationSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new notification
     */
    public function createNotification($userId, $type, $title, $message, $actionUrl = null, $actionText = null, $data = null, $isImportant = false) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, action_url, action_text, data, is_important, channel) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'web')
            ");
            
            $result = $stmt->execute([
                $userId, $type, $title, $message, $actionUrl, $actionText, 
                $data ? json_encode($data) : null, $isImportant
            ]);
            
            if ($result) {
                return $this->pdo->lastInsertId();
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Notification creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $page = 1, $limit = 20, $unreadOnly = false) {
        $offset = ($page - 1) * $limit;
        
        $whereClause = "WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $whereClause .= " AND is_read = FALSE";
        }
        
        // Exclude expired notifications
        $whereClause .= " AND (expires_at IS NULL OR expires_at > NOW())";
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM notifications 
            $whereClause
            ORDER BY is_important DESC, created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        
        $notifications = $stmt->fetchAll();
        
        // Parse JSON data
        foreach ($notifications as &$notification) {
            if ($notification['data']) {
                $notification['data'] = json_decode($notification['data'], true);
            }
        }
        
        return $notifications;
    }
    
    /**
     * Get unread notification count
     */
    public function getUnreadCount($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM notifications 
            WHERE user_id = ? AND is_read = FALSE 
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetchColumn();
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$notificationId, $userId]);
    }
    
    /**
     * Mark all notifications as read
     */
    public function markAllAsRead($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE notifications 
            SET is_read = TRUE, read_at = CURRENT_TIMESTAMP 
            WHERE user_id = ? AND is_read = FALSE
        ");
        
        return $stmt->execute([$userId]);
    }
    
    /**
     * Delete notification
     */
    public function deleteNotification($notificationId, $userId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM notifications 
            WHERE id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$notificationId, $userId]);
    }
    
    /**
     * Order-related notifications
     */
    public function notifyOrderPlaced($sellerId, $orderId, $orderTotal, $customerName) {
        return $this->createNotification(
            $sellerId,
            'order',
            'New Order Received!',
            "You have received a new order from {$customerName} for $" . number_format($orderTotal, 2),
            "merchant/orders.php?id={$orderId}",
            'View Order',
            ['order_id' => $orderId, 'amount' => $orderTotal],
            true
        );
    }
    
    public function notifyOrderStatusChange($buyerId, $orderId, $newStatus, $merchantName) {
        $statusMessages = [
            'confirmed' => 'Your order has been confirmed',
            'processing' => 'Your order is being processed',
            'shipped' => 'Your order has been shipped',
            'delivered' => 'Your order has been delivered',
            'cancelled' => 'Your order has been cancelled'
        ];
        
        $message = $statusMessages[$newStatus] ?? "Your order status has been updated to {$newStatus}";
        
        return $this->createNotification(
            $buyerId,
            'order',
            'Order Update',
            "{$message} by {$merchantName}",
            "orders.php?id={$orderId}",
            'View Order',
            ['order_id' => $orderId, 'status' => $newStatus]
        );
    }
    
    /**
     * Payment notifications
     */
    public function notifyPaymentReceived($sellerId, $amount, $orderId) {
        return $this->createNotification(
            $sellerId,
            'payment',
            'Payment Received',
            "You have received a payment of $" . number_format($amount, 2),
            "merchant/earnings.php",
            'View Earnings',
            ['amount' => $amount, 'order_id' => $orderId]
        );
    }
    
    public function notifyPaymentFailed($buyerId, $orderId, $reason = '') {
        $message = "Payment failed for your order" . ($reason ? ": {$reason}" : '');
        
        return $this->createNotification(
            $buyerId,
            'payment',
            'Payment Failed',
            $message,
            "checkout.php?retry_order={$orderId}",
            'Retry Payment',
            ['order_id' => $orderId],
            true
        );
    }
    
    /**
     * Message notifications
     */
    public function notifyNewMessage($recipientId, $senderName, $conversationId, $messagePreview) {
        return $this->createNotification(
            $recipientId,
            'message',
            'New Message',
            "{$senderName}: " . substr($messagePreview, 0, 50) . (strlen($messagePreview) > 50 ? '...' : ''),
            "messages.php?conversation={$conversationId}",
            'Reply',
            ['conversation_id' => $conversationId, 'sender' => $senderName]
        );
    }
    
    /**
     * Review notifications
     */
    public function notifyNewReview($sellerId, $productName, $rating, $reviewerId) {
        $stars = str_repeat('⭐', $rating);
        
        return $this->createNotification(
            $sellerId,
            'review',
            'New Product Review',
            "Your product \"{$productName}\" received a {$rating}-star review {$stars}",
            "merchant/reviews.php",
            'View Reviews',
            ['product_name' => $productName, 'rating' => $rating]
        );
    }
    
    public function notifyReviewResponse($reviewerId, $productName, $merchantName) {
        return $this->createNotification(
            $reviewerId,
            'review',
            'Merchant Response',
            "{$merchantName} responded to your review of \"{$productName}\"",
            "product-reviews.php",
            'View Response',
            ['product_name' => $productName, 'merchant' => $merchantName]
        );
    }
    
    /**
     * System notifications
     */
    public function notifyMaintenance($userId, $startTime, $duration) {
        return $this->createNotification(
            $userId,
            'system',
            'Scheduled Maintenance',
            "System maintenance scheduled for {$startTime} (approximately {$duration})",
            null,
            null,
            ['start_time' => $startTime, 'duration' => $duration],
            true
        );
    }
    
    public function notifySecurityAlert($userId, $alertType, $details) {
        return $this->createNotification(
            $userId,
            'system',
            'Security Alert',
            "Security alert: {$alertType}. Please review your account.",
            "profile.php?tab=security",
            'Review Security',
            ['alert_type' => $alertType, 'details' => $details],
            true
        );
    }
    
    /**
     * Promotion notifications
     */
    public function notifyPromotion($userId, $title, $description, $promoCode = null, $expiresAt = null) {
        $actionText = $promoCode ? 'Use Code: ' . $promoCode : 'Shop Now';
        
        return $this->createNotification(
            $userId,
            'promotion',
            $title,
            $description,
            "index.php",
            $actionText,
            ['promo_code' => $promoCode],
            false,
            $expiresAt
        );
    }
    
    /**
     * Shipping notifications
     */
    public function notifyShippingUpdate($buyerId, $orderId, $trackingNumber, $carrier) {
        return $this->createNotification(
            $buyerId,
            'shipping',
            'Shipment Tracking Available',
            "Your order has been shipped via {$carrier}. Tracking: {$trackingNumber}",
            "orders.php?id={$orderId}",
            'Track Package',
            ['order_id' => $orderId, 'tracking' => $trackingNumber, 'carrier' => $carrier]
        );
    }
    
    /**
     * Clean up old notifications
     */
    public function cleanupOldNotifications($daysOld = 30) {
        $stmt = $this->pdo->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            AND is_read = TRUE
        ");
        
        return $stmt->execute([$daysOld]);
    }
    
    /**
     * Get notification statistics
     */
    public function getNotificationStats($userId = null) {
        $whereClause = $userId ? "WHERE user_id = ?" : "";
        $params = $userId ? [$userId] : [];
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_notifications,
                COUNT(CASE WHEN is_read = FALSE THEN 1 END) as unread_count,
                COUNT(CASE WHEN type = 'order' THEN 1 END) as order_notifications,
                COUNT(CASE WHEN type = 'message' THEN 1 END) as message_notifications,
                COUNT(CASE WHEN type = 'payment' THEN 1 END) as payment_notifications,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_notifications
            FROM notifications 
            $whereClause
        ");
        
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Bulk create notifications (for broadcasts)
     */
    public function createBulkNotifications($userIds, $type, $title, $message, $actionUrl = null, $actionText = null, $data = null) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, action_url, action_text, data, channel) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'web')
            ");
            
            $successCount = 0;
            foreach ($userIds as $userId) {
                if ($stmt->execute([$userId, $type, $title, $message, $actionUrl, $actionText, $data ? json_encode($data) : null])) {
                    $successCount++;
                }
            }
            
            $this->pdo->commit();
            return $successCount;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Bulk notification creation failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get notification preferences (if user preferences table exists)
     */
    public function getUserPreferences($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT email_notifications, sms_notifications, push_notifications 
                FROM user_preferences 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            $prefs = $stmt->fetch();
            return $prefs ?: [
                'email_notifications' => true,
                'sms_notifications' => false,
                'push_notifications' => true
            ];
            
        } catch (Exception $e) {
            // Return defaults if table doesn't exist
            return [
                'email_notifications' => true,
                'sms_notifications' => false,
                'push_notifications' => true
            ];
        }
    }
    
    /**
     * Real-time notification polling endpoint data
     */
    public function getPollingData($userId, $lastCheck = null) {
        $whereClause = "WHERE user_id = ? AND is_read = FALSE";
        $params = [$userId];
        
        if ($lastCheck) {
            $whereClause .= " AND created_at > ?";
            $params[] = $lastCheck;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT id, type, title, message, action_url, action_text, is_important, created_at
            FROM notifications 
            $whereClause
            AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY created_at DESC
            LIMIT 10
        ");
        
        $stmt->execute($params);
        $newNotifications = $stmt->fetchAll();
        
        return [
            'notifications' => $newNotifications,
            'unread_count' => $this->getUnreadCount($userId),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
?>