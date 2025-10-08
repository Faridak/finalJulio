<?php
/**
 * Buyer-Seller Messaging System
 * Handles conversations, messages, and communication between buyers and sellers
 */

class MessagingSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Start a new conversation between buyer and seller
     */
    public function startConversation($buyerId, $sellerId, $subject, $productId = null) {
        try {
            // Check if conversation already exists for this product
            if ($productId) {
                $stmt = $this->pdo->prepare("
                    SELECT c.id FROM conversations c
                    JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
                    JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
                    WHERE cp1.user_id = ? AND cp1.role = 'buyer'
                    AND cp2.user_id = ? AND cp2.role = 'seller'
                    AND c.type = 'buyer_seller'
                    AND JSON_EXTRACT(c.metadata, '$.product_id') = ?
                    AND c.status = 'active'
                ");
                $stmt->execute([$buyerId, $sellerId, $productId]);
                
                if ($existingConversation = $stmt->fetch()) {
                    return $existingConversation['id'];
                }
            }
            
            $this->pdo->beginTransaction();
            
            // Create conversation
            $stmt = $this->pdo->prepare("
                INSERT INTO conversations (type, subject, created_by, metadata) 
                VALUES ('buyer_seller', ?, ?, ?)
            ");
            
            $metadata = $productId ? json_encode(['product_id' => $productId]) : null;
            $stmt->execute([$subject, $buyerId, $metadata]);
            $conversationId = $this->pdo->lastInsertId();
            
            // Add buyer as participant
            $stmt = $this->pdo->prepare("
                INSERT INTO conversation_participants (conversation_id, user_id, role) 
                VALUES (?, ?, 'buyer')
            ");
            $stmt->execute([$conversationId, $buyerId]);
            
            // Add seller as participant
            $stmt = $this->pdo->prepare("
                INSERT INTO conversation_participants (conversation_id, user_id, role) 
                VALUES (?, ?, 'seller')
            ");
            $stmt->execute([$conversationId, $sellerId]);
            
            $this->pdo->commit();
            return $conversationId;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Send a message in a conversation
     */
    public function sendMessage($conversationId, $senderId, $messageText, $messageType = 'text', $attachmentUrl = null) {
        try {
            // Verify user is participant in conversation
            if (!$this->isParticipant($conversationId, $senderId)) {
                throw new Exception("You are not a participant in this conversation");
            }
            
            // Insert message
            $stmt = $this->pdo->prepare("
                INSERT INTO messages (conversation_id, sender_id, message_text, message_type, attachment_url) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([$conversationId, $senderId, $messageText, $messageType, $attachmentUrl]);
            
            if ($result) {
                $messageId = $this->pdo->lastInsertId();
                
                // Update conversation timestamp
                $stmt = $this->pdo->prepare("
                    UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?
                ");
                $stmt->execute([$conversationId]);
                
                // Mark conversation as unread for other participants
                $stmt = $this->pdo->prepare("
                    UPDATE conversation_participants 
                    SET last_read_at = NULL 
                    WHERE conversation_id = ? AND user_id != ?
                ");
                $stmt->execute([$conversationId, $senderId]);
                
                return $messageId;
            }
            
            return false;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get conversations for a user
     */
    public function getUserConversations($userId, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                cp.role as user_role,
                cp.last_read_at,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.created_at > COALESCE(cp.last_read_at, '1970-01-01')) as unread_count,
                (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message,
                (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) as last_message_at,
                other_participant.user_id as other_user_id,
                other_user.email as other_user_email,
                other_profile.first_name as other_first_name,
                other_profile.last_name as other_last_name
            FROM conversations c
            JOIN conversation_participants cp ON c.id = cp.conversation_id
            JOIN conversation_participants other_participant ON c.id = other_participant.conversation_id AND other_participant.user_id != ?
            JOIN users other_user ON other_participant.user_id = other_user.id
            LEFT JOIN user_profiles other_profile ON other_user.id = other_profile.user_id
            WHERE cp.user_id = ? AND cp.is_active = TRUE
            ORDER BY COALESCE(last_message_at, c.created_at) DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$userId, $userId, $userId, $limit, $offset]);
        $conversations = $stmt->fetchAll();
        
        // Add product information if available
        foreach ($conversations as &$conversation) {
            if ($conversation['metadata']) {
                $metadata = json_decode($conversation['metadata'], true);
                if (isset($metadata['product_id'])) {
                    $stmt = $this->pdo->prepare("
                        SELECT id, name, price, image_url FROM products WHERE id = ?
                    ");
                    $stmt->execute([$metadata['product_id']]);
                    $conversation['product'] = $stmt->fetch();
                }
            }
        }
        
        return $conversations;
    }
    
    /**
     * Get messages in a conversation
     */
    public function getConversationMessages($conversationId, $userId, $page = 1, $limit = 50) {
        // Verify user is participant
        if (!$this->isParticipant($conversationId, $userId)) {
            throw new Exception("Access denied");
        }
        
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->pdo->prepare("
            SELECT 
                m.*,
                u.email as sender_email,
                up.first_name as sender_first_name,
                up.last_name as sender_last_name
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$conversationId, $limit, $offset]);
        $messages = $stmt->fetchAll();
        
        // Mark as read
        $this->markAsRead($conversationId, $userId);
        
        return array_reverse($messages); // Show oldest first
    }
    
    /**
     * Get conversation details
     */
    public function getConversation($conversationId, $userId) {
        // Verify user is participant
        if (!$this->isParticipant($conversationId, $userId)) {
            throw new Exception("Access denied");
        }
        
        $stmt = $this->pdo->prepare("
            SELECT 
                c.*,
                cp.role as user_role,
                other_participant.user_id as other_user_id,
                other_user.email as other_user_email,
                other_profile.first_name as other_first_name,
                other_profile.last_name as other_last_name,
                other_participant.role as other_user_role
            FROM conversations c
            JOIN conversation_participants cp ON c.id = cp.conversation_id
            JOIN conversation_participants other_participant ON c.id = other_participant.conversation_id AND other_participant.user_id != ?
            JOIN users other_user ON other_participant.user_id = other_user.id
            LEFT JOIN user_profiles other_profile ON other_user.id = other_profile.user_id
            WHERE c.id = ? AND cp.user_id = ?
        ");
        
        $stmt->execute([$userId, $conversationId, $userId]);
        $conversation = $stmt->fetch();
        
        if (!$conversation) {
            throw new Exception("Conversation not found");
        }
        
        // Add product information if available
        if ($conversation['metadata']) {
            $metadata = json_decode($conversation['metadata'], true);
            if (isset($metadata['product_id'])) {
                $stmt = $this->pdo->prepare("
                    SELECT id, name, price, image_url FROM products WHERE id = ?
                ");
                $stmt->execute([$metadata['product_id']]);
                $conversation['product'] = $stmt->fetch();
            }
        }
        
        return $conversation;
    }
    
    /**
     * Mark conversation as read
     */
    public function markAsRead($conversationId, $userId) {
        $stmt = $this->pdo->prepare("
            UPDATE conversation_participants 
            SET last_read_at = CURRENT_TIMESTAMP 
            WHERE conversation_id = ? AND user_id = ?
        ");
        
        return $stmt->execute([$conversationId, $userId]);
    }
    
    /**
     * Close/Archive conversation
     */
    public function closeConversation($conversationId, $userId) {
        // Only allow if user is participant
        if (!$this->isParticipant($conversationId, $userId)) {
            throw new Exception("Access denied");
        }
        
        $stmt = $this->pdo->prepare("
            UPDATE conversations 
            SET status = 'closed' 
            WHERE id = ?
        ");
        
        return $stmt->execute([$conversationId]);
    }
    
    /**
     * Check if user is participant in conversation
     */
    private function isParticipant($conversationId, $userId) {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ? AND is_active = TRUE
        ");
        $stmt->execute([$conversationId, $userId]);
        
        return (bool)$stmt->fetch();
    }
    
    /**
     * Get unread message count for user
     */
    public function getUnreadCount($userId) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM messages m
            JOIN conversations c ON m.conversation_id = c.id
            JOIN conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ? AND m.sender_id != ? 
            AND m.created_at > COALESCE(cp.last_read_at, '1970-01-01')
            AND c.status = 'active'
        ");
        
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Search messages
     */
    public function searchMessages($userId, $query, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->pdo->prepare("
            SELECT 
                m.*,
                c.subject,
                u.email as sender_email,
                up.first_name as sender_first_name,
                up.last_name as sender_last_name
            FROM messages m
            JOIN conversations c ON m.conversation_id = c.id
            JOIN conversation_participants cp ON c.id = cp.conversation_id
            JOIN users u ON m.sender_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE cp.user_id = ? AND cp.is_active = TRUE
            AND (m.message_text LIKE ? OR c.subject LIKE ?)
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $searchTerm = '%' . $query . '%';
        $stmt->execute([$userId, $searchTerm, $searchTerm, $limit, $offset]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Block/Unblock user from messaging
     */
    public function blockUser($blockerId, $blockedUserId) {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO user_blocks (blocker_id, blocked_id) VALUES (?, ?)
        ");
        
        return $stmt->execute([$blockerId, $blockedUserId]);
    }
    
    public function unblockUser($blockerId, $blockedUserId) {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_blocks WHERE blocker_id = ? AND blocked_id = ?
        ");
        
        return $stmt->execute([$blockerId, $blockedUserId]);
    }
    
    /**
     * Check if user is blocked
     */
    public function isUserBlocked($userId, $otherUserId) {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM user_blocks 
            WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)
        ");
        $stmt->execute([$userId, $otherUserId, $otherUserId, $userId]);
        
        return (bool)$stmt->fetch();
    }
    
    /**
     * Report inappropriate message
     */
    public function reportMessage($messageId, $reporterId, $reason) {
        $stmt = $this->pdo->prepare("
            INSERT INTO message_reports (message_id, reporter_id, reason, created_at) 
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        return $stmt->execute([$messageId, $reporterId, $reason]);
    }
    
    /**
     * Get conversation statistics for admin
     */
    public function getConversationStats() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_conversations,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_conversations,
                COUNT(CASE WHEN status = 'closed' THEN 1 END) as closed_conversations,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_this_month
            FROM conversations
        ");
        
        $stmt->execute();
        return $stmt->fetch();
    }
}

/**
 * Create user_blocks table if it doesn't exist
 */
function createUserBlocksTable($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS user_blocks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            blocker_id INT NOT NULL,
            blocked_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_block (blocker_id, blocked_id)
        )
    ";
    $pdo->exec($sql);
}

/**
 * Create message_reports table if it doesn't exist
 */
function createMessageReportsTable($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS message_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NOT NULL,
            reporter_id INT NOT NULL,
            reason VARCHAR(255) NOT NULL,
            status ENUM('pending', 'reviewed', 'dismissed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
            FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    $pdo->exec($sql);
}
?>