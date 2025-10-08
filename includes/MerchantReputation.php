<?php
/**
 * Merchant Reputation System
 * Handles merchant ratings, reputation scores, and trust metrics
 */

class MerchantReputation {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Add a merchant rating from a customer
     */
    public function addMerchantRating($merchantId, $customerId, $orderId, $rating, $communicationRating, $shippingSpeedRating, $itemDescriptionRating, $feedbackText = '') {
        try {
            // Verify the order exists and belongs to the customer
            $stmt = $this->pdo->prepare("
                SELECT id FROM orders 
                WHERE id = ? AND user_id = ? AND status = 'delivered'
            ");
            $stmt->execute([$orderId, $customerId]);
            
            if (!$stmt->fetch()) {
                throw new Exception("Invalid order or order not eligible for rating");
            }
            
            // Check if rating already exists
            $stmt = $this->pdo->prepare("
                SELECT id FROM merchant_ratings 
                WHERE merchant_id = ? AND customer_id = ? AND order_id = ?
            ");
            $stmt->execute([$merchantId, $customerId, $orderId]);
            
            if ($stmt->fetch()) {
                throw new Exception("You have already rated this merchant for this order");
            }
            
            // Insert rating
            $stmt = $this->pdo->prepare("
                INSERT INTO merchant_ratings 
                (merchant_id, customer_id, order_id, rating, communication_rating, shipping_speed_rating, item_description_rating, feedback_text) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([
                $merchantId, $customerId, $orderId, $rating, 
                $communicationRating, $shippingSpeedRating, $itemDescriptionRating, $feedbackText
            ]);
            
            if ($result) {
                $this->updateMerchantReputationScore($merchantId);
                return $this->pdo->lastInsertId();
            }
            
            return false;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get merchant reputation summary
     */
    public function getMerchantReputation($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_ratings,
                AVG(rating) as average_rating,
                AVG(communication_rating) as avg_communication,
                AVG(shipping_speed_rating) as avg_shipping_speed,
                AVG(item_description_rating) as avg_item_description,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            FROM merchant_ratings 
            WHERE merchant_id = ?
        ");
        
        $stmt->execute([$merchantId]);
        $reputation = $stmt->fetch();
        
        // Get recent feedback
        $stmt = $this->pdo->prepare("
            SELECT 
                mr.*,
                up.first_name,
                up.last_name
            FROM merchant_ratings mr
            LEFT JOIN users u ON mr.customer_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE mr.merchant_id = ? AND mr.feedback_text IS NOT NULL AND mr.feedback_text != ''
            ORDER BY mr.created_at DESC
            LIMIT 5
        ");
        
        $stmt->execute([$merchantId]);
        $reputation['recent_feedback'] = $stmt->fetchAll();
        
        // Calculate reputation badge
        $reputation['badge'] = $this->calculateReputationBadge($reputation['average_rating'], $reputation['total_ratings']);
        
        return $reputation;
    }
    
    /**
     * Update merchant overall reputation score
     */
    private function updateMerchantReputationScore($merchantId) {
        // Get all metrics
        $stmt = $this->pdo->prepare("
            SELECT 
                AVG(rating) as avg_rating,
                COUNT(*) as total_ratings,
                AVG(communication_rating) as avg_communication,
                AVG(shipping_speed_rating) as avg_shipping,
                AVG(item_description_rating) as avg_description
            FROM merchant_ratings 
            WHERE merchant_id = ?
        ");
        
        $stmt->execute([$merchantId]);
        $metrics = $stmt->fetch();
        
        // Calculate weighted reputation score
        $reputationScore = $this->calculateReputationScore($metrics);
        
        // Update merchant profile
        $stmt = $this->pdo->prepare("
            UPDATE user_profiles 
            SET reputation_score = ?, total_ratings = ?, average_rating = ?
            WHERE user_id = ?
        ");
        
        return $stmt->execute([
            $reputationScore,
            $metrics['total_ratings'],
            $metrics['avg_rating'],
            $merchantId
        ]);
    }
    
    /**
     * Calculate overall reputation score (0-100)
     */
    private function calculateReputationScore($metrics) {
        if ($metrics['total_ratings'] == 0) {
            return 0;
        }
        
        // Base score from average rating (0-100)
        $baseScore = ($metrics['avg_rating'] / 5) * 100;
        
        // Bonus for volume (up to 10 points)
        $volumeBonus = min(10, $metrics['total_ratings'] / 10);
        
        // Sub-ratings influence (weighted)
        $subRatingScore = (
            $metrics['avg_communication'] * 0.3 +
            $metrics['avg_shipping'] * 0.3 +
            $metrics['avg_description'] * 0.4
        ) / 5 * 20; // 0-20 points
        
        $totalScore = $baseScore + $volumeBonus + $subRatingScore;
        
        return min(100, $totalScore);
    }
    
    /**
     * Calculate reputation badge
     */
    private function calculateReputationBadge($avgRating, $totalRatings) {
        if ($totalRatings < 5) {
            return ['name' => 'New Seller', 'color' => 'gray', 'icon' => 'fas fa-seedling'];
        }
        
        if ($avgRating >= 4.8 && $totalRatings >= 100) {
            return ['name' => 'Top Rated Plus', 'color' => 'purple', 'icon' => 'fas fa-crown'];
        }
        
        if ($avgRating >= 4.5 && $totalRatings >= 50) {
            return ['name' => 'Top Rated', 'color' => 'gold', 'icon' => 'fas fa-star'];
        }
        
        if ($avgRating >= 4.0 && $totalRatings >= 20) {
            return ['name' => 'Trusted Seller', 'color' => 'green', 'icon' => 'fas fa-shield-alt'];
        }
        
        if ($avgRating >= 3.5) {
            return ['name' => 'Good Seller', 'color' => 'blue', 'icon' => 'fas fa-thumbs-up'];
        }
        
        return ['name' => 'Basic Seller', 'color' => 'gray', 'icon' => 'fas fa-store'];
    }
    
    /**
     * Get merchant ratings with pagination
     */
    public function getMerchantRatings($merchantId, $page = 1, $limit = 10, $sortBy = 'newest') {
        $offset = ($page - 1) * $limit;
        
        $orderClause = match($sortBy) {
            'oldest' => 'ORDER BY mr.created_at ASC',
            'highest_rated' => 'ORDER BY mr.rating DESC, mr.created_at DESC',
            'lowest_rated' => 'ORDER BY mr.rating ASC, mr.created_at DESC',
            default => 'ORDER BY mr.created_at DESC' // newest
        };
        
        $stmt = $this->pdo->prepare("
            SELECT 
                mr.*,
                up.first_name,
                up.last_name,
                u.email
            FROM merchant_ratings mr
            LEFT JOIN users u ON mr.customer_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE mr.merchant_id = ?
            $orderClause
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$merchantId, $limit, $offset]);
        $ratings = $stmt->fetchAll();
        
        // Get total count
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM merchant_ratings WHERE merchant_id = ?
        ");
        $countStmt->execute([$merchantId]);
        $totalCount = $countStmt->fetchColumn();
        
        return [
            'ratings' => $ratings,
            'total' => $totalCount,
            'page' => $page,
            'total_pages' => ceil($totalCount / $limit)
        ];
    }
    
    /**
     * Get top rated merchants
     */
    public function getTopRatedMerchants($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id,
                u.email,
                up.first_name,
                up.last_name,
                up.reputation_score,
                up.average_rating,
                up.total_ratings,
                COUNT(DISTINCT p.id) as total_products,
                COUNT(DISTINCT o.id) as total_orders
            FROM users u
            LEFT JOIN user_profiles up ON u.id = up.user_id
            LEFT JOIN products p ON u.id = p.merchant_id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'delivered'
            WHERE u.role = 'merchant' AND up.total_ratings >= 5
            GROUP BY u.id
            ORDER BY up.reputation_score DESC, up.average_rating DESC
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get merchant performance metrics
     */
    public function getMerchantMetrics($merchantId) {
        // Sales metrics
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(CASE WHEN o.status = 'delivered' THEN 1 END) as completed_orders,
                COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders,
                SUM(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE 0 END) as total_revenue,
                AVG(CASE WHEN o.status = 'delivered' THEN o.total_amount ELSE NULL END) as avg_order_value
            FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE p.merchant_id = ?
        ");
        
        $stmt->execute([$merchantId]);
        $metrics = $stmt->fetch();
        
        // Response time metrics (if messaging system exists)
        $stmt = $this->pdo->prepare("
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, c.created_at, 
                    (SELECT MIN(m.created_at) FROM messages m 
                     JOIN conversation_participants cp ON m.sender_id = cp.user_id 
                     WHERE m.conversation_id = c.id AND cp.user_id = ? AND m.created_at > c.created_at)
                )) as avg_response_time_hours
            FROM conversations c
            JOIN conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ? AND c.type = 'buyer_seller'
            AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        $stmt->execute([$merchantId, $merchantId]);
        $responseMetrics = $stmt->fetch();
        
        if ($responseMetrics && $responseMetrics['avg_response_time_hours']) {
            $metrics['avg_response_time_hours'] = $responseMetrics['avg_response_time_hours'];
        }
        
        // Calculate completion rate
        $metrics['completion_rate'] = $metrics['total_orders'] > 0 
            ? ($metrics['completed_orders'] / $metrics['total_orders']) * 100 
            : 0;
            
        return $metrics;
    }
    
    /**
     * Check if customer can rate merchant
     */
    public function canRateMerchant($customerId, $merchantId, $orderId) {
        // Check if order exists and belongs to customer
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE o.id = ? AND o.user_id = ? AND p.merchant_id = ? AND o.status = 'delivered'
        ");
        $stmt->execute([$orderId, $customerId, $merchantId]);
        
        if (!$stmt->fetch()) {
            return ['can_rate' => false, 'reason' => 'invalid_order'];
        }
        
        // Check if already rated
        $stmt = $this->pdo->prepare("
            SELECT id FROM merchant_ratings 
            WHERE customer_id = ? AND merchant_id = ? AND order_id = ?
        ");
        $stmt->execute([$customerId, $merchantId, $orderId]);
        
        if ($stmt->fetch()) {
            return ['can_rate' => false, 'reason' => 'already_rated'];
        }
        
        return ['can_rate' => true];
    }
    
    /**
     * Get merchant trust indicators
     */
    public function getMerchantTrustIndicators($merchantId) {
        $indicators = [];
        
        // Years in business
        $stmt = $this->pdo->prepare("
            SELECT TIMESTAMPDIFF(YEAR, created_at, NOW()) as years_active 
            FROM users WHERE id = ?
        ");
        $stmt->execute([$merchantId]);
        $yearsActive = $stmt->fetchColumn();
        
        if ($yearsActive >= 1) {
            $indicators[] = [
                'icon' => 'fas fa-calendar-check',
                'text' => $yearsActive . ' year' . ($yearsActive > 1 ? 's' : '') . ' on VentDepot',
                'type' => 'experience'
            ];
        }
        
        // Verified information
        $stmt = $this->pdo->prepare("
            SELECT email_verified_at FROM users WHERE id = ?
        ");
        $stmt->execute([$merchantId]);
        $emailVerified = $stmt->fetchColumn();
        
        if ($emailVerified) {
            $indicators[] = [
                'icon' => 'fas fa-shield-check',
                'text' => 'Verified email address',
                'type' => 'verification'
            ];
        }
        
        // High volume seller
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM orders o
            JOIN order_items oi ON o.id = oi.order_id
            JOIN products p ON oi.product_id = p.id
            WHERE p.merchant_id = ? AND o.status = 'delivered'
        ");
        $stmt->execute([$merchantId]);
        $totalSales = $stmt->fetchColumn();
        
        if ($totalSales >= 100) {
            $indicators[] = [
                'icon' => 'fas fa-shipping-fast',
                'text' => $totalSales . '+ successful orders',
                'type' => 'volume'
            ];
        }
        
        return $indicators;
    }
}
?>