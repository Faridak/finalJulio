<?php
/**
 * Product Reviews System
 * Handles product reviews, ratings, and review management
 */

class ProductReviews {
    private $pdo;
    private $tablesExist = false;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->checkTablesExist();
    }
    
    /**
     * Check if required tables exist
     */
    private function checkTablesExist() {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE 'product_reviews'");
            $stmt->execute();
            $this->tablesExist = (bool)$stmt->fetch();
        } catch (Exception $e) {
            $this->tablesExist = false;
        }
    }
    
    /**
     * Add a new review
     */
    public function addReview($productId, $userId, $rating, $title, $reviewText, $orderId = null) {
        try {
            // Check if user already reviewed this product
            $stmt = $this->pdo->prepare("SELECT id FROM product_reviews WHERE product_id = ? AND user_id = ?");
            $stmt->execute([$productId, $userId]);
            
            if ($stmt->fetch()) {
                throw new Exception("You have already reviewed this product.");
            }
            
            // Verify purchase if order ID provided
            $isVerifiedPurchase = false;
            if ($orderId) {
                $stmt = $this->pdo->prepare("
                    SELECT 1 FROM order_items oi 
                    JOIN orders o ON oi.order_id = o.id 
                    WHERE oi.product_id = ? AND o.user_id = ? AND o.id = ? AND o.status = 'delivered'
                ");
                $stmt->execute([$productId, $userId, $orderId]);
                $isVerifiedPurchase = (bool)$stmt->fetch();
            }
            
            // Insert review
            $stmt = $this->pdo->prepare("
                INSERT INTO product_reviews (product_id, user_id, order_id, rating, title, review_text, is_verified_purchase) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $result = $stmt->execute([$productId, $userId, $orderId, $rating, $title, $reviewText, $isVerifiedPurchase]);
            
            if ($result) {
                $reviewId = $this->pdo->lastInsertId();
                $this->updateProductRating($productId);
                return $reviewId;
            }
            
            return false;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Get reviews for a product
     */
    public function getProductReviews($productId, $page = 1, $limit = 10, $sortBy = 'newest') {
        // Return empty result if tables don't exist
        if (!$this->tablesExist) {
            return [
                'reviews' => [],
                'total' => 0,
                'page' => $page,
                'total_pages' => 0
            ];
        }
        
        $page = (int)$page;
        $limit = (int)$limit;
        $offset = ($page - 1) * $limit;
        
        $orderClause = match($sortBy) {
            'oldest' => 'ORDER BY pr.created_at ASC',
            'highest_rated' => 'ORDER BY pr.rating DESC, pr.created_at DESC',
            'lowest_rated' => 'ORDER BY pr.rating ASC, pr.created_at DESC',
            'most_helpful' => 'ORDER BY pr.helpful_count DESC, pr.created_at DESC',
            default => 'ORDER BY pr.created_at DESC' // newest
        };
        
        $stmt = $this->pdo->prepare("
            SELECT 
                pr.*,
                up.first_name,
                up.last_name,
                u.email,
                (SELECT COUNT(*) FROM review_votes rv WHERE rv.review_id = pr.id AND rv.vote_type = 'helpful') as helpful_votes,
                (SELECT COUNT(*) FROM review_votes rv WHERE rv.review_id = pr.id AND rv.vote_type = 'unhelpful') as unhelpful_votes
            FROM product_reviews pr
            LEFT JOIN users u ON pr.user_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE pr.product_id = ? AND pr.is_approved = TRUE
            $orderClause
            LIMIT $limit OFFSET $offset
        ");
        
        $stmt->execute([$productId]);
        $reviews = $stmt->fetchAll();
        
        // Get total count
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM product_reviews 
            WHERE product_id = ? AND is_approved = TRUE
        ");
        $countStmt->execute([$productId]);
        $totalCount = $countStmt->fetchColumn();
        
        return [
            'reviews' => $reviews,
            'total' => $totalCount,
            'page' => $page,
            'total_pages' => ceil($totalCount / $limit)
        ];
    }
    
    /**
     * Get product rating summary
     */
    public function getProductRatingSummary($productId) {
        // Return empty result if tables don't exist
        if (!$this->tablesExist) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'five_star' => 0,
                'four_star' => 0,
                'three_star' => 0,
                'two_star' => 0,
                'one_star' => 0,
                'verified_purchases' => 0
            ];
        }
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
                SUM(CASE WHEN is_verified_purchase = TRUE THEN 1 ELSE 0 END) as verified_purchases
            FROM product_reviews 
            WHERE product_id = ? AND is_approved = TRUE
        ");
        
        $stmt->execute([$productId]);
        return $stmt->fetch();
    }
    
    /**
     * Vote on review helpfulness
     */
    public function voteOnReview($reviewId, $userId, $voteType) {
        try {
            // Check if user already voted
            $stmt = $this->pdo->prepare("SELECT id FROM review_votes WHERE review_id = ? AND user_id = ?");
            $stmt->execute([$reviewId, $userId]);
            
            if ($stmt->fetch()) {
                throw new Exception("You have already voted on this review.");
            }
            
            // Add vote
            $stmt = $this->pdo->prepare("
                INSERT INTO review_votes (review_id, user_id, vote_type) VALUES (?, ?, ?)
            ");
            
            $result = $stmt->execute([$reviewId, $userId, $voteType]);
            
            if ($result) {
                // Update review helpfulness counts
                $this->updateReviewHelpfulness($reviewId);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Update review helpfulness counts
     */
    private function updateReviewHelpfulness($reviewId) {
        $stmt = $this->pdo->prepare("
            UPDATE product_reviews SET 
                helpful_count = (SELECT COUNT(*) FROM review_votes WHERE review_id = ? AND vote_type = 'helpful'),
                unhelpful_count = (SELECT COUNT(*) FROM review_votes WHERE review_id = ? AND vote_type = 'unhelpful')
            WHERE id = ?
        ");
        
        return $stmt->execute([$reviewId, $reviewId, $reviewId]);
    }
    
    /**
     * Update product average rating
     */
    private function updateProductRating($productId) {
        $stmt = $this->pdo->prepare("
            UPDATE products SET 
                average_rating = (
                    SELECT AVG(rating) FROM product_reviews 
                    WHERE product_id = ? AND is_approved = TRUE
                ),
                review_count = (
                    SELECT COUNT(*) FROM product_reviews 
                    WHERE product_id = ? AND is_approved = TRUE
                )
            WHERE id = ?
        ");
        
        return $stmt->execute([$productId, $productId, $productId]);
    }
    
    /**
     * Get reviews by user
     */
    public function getUserReviews($userId, $page = 1, $limit = 10) {
        $page = (int)$page;
        $limit = (int)$limit;
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->pdo->prepare("
            SELECT 
                pr.*,
                p.name as product_name,
                p.image_url as product_image
            FROM product_reviews pr
            JOIN products p ON pr.product_id = p.id
            WHERE pr.user_id = ?
            ORDER BY pr.created_at DESC
            LIMIT $limit OFFSET $offset
        ");
        
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Delete review
     */
    public function deleteReview($reviewId, $userId = null) {
        $whereClause = "id = ?";
        $params = [$reviewId];
        
        // If user ID provided, ensure user owns the review
        if ($userId) {
            $whereClause .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM product_reviews WHERE $whereClause");
        $result = $stmt->execute($params);
        
        if ($result && $stmt->rowCount() > 0) {
            // Get product ID to update rating
            $stmt = $this->pdo->prepare("SELECT product_id FROM product_reviews WHERE id = ?");
            $stmt->execute([$reviewId]);
            $productId = $stmt->fetchColumn();
            
            if ($productId) {
                $this->updateProductRating($productId);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Moderate review (admin function)
     */
    public function moderateReview($reviewId, $isApproved, $adminId, $adminResponse = null) {
        $stmt = $this->pdo->prepare("
            UPDATE product_reviews 
            SET is_approved = ?, admin_response = ?, admin_id = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $result = $stmt->execute([$isApproved, $adminResponse, $adminId, $reviewId]);
        
        if ($result) {
            // Get product ID to update rating
            $stmt = $this->pdo->prepare("SELECT product_id FROM product_reviews WHERE id = ?");
            $stmt->execute([$reviewId]);
            $productId = $stmt->fetchColumn();
            
            if ($productId) {
                $this->updateProductRating($productId);
            }
        }
        
        return $result;
    }
    
    /**
     * Get reviews pending moderation
     */
    public function getPendingReviews($page = 1, $limit = 20) {
        $page = (int)$page;
        $limit = (int)$limit;
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->pdo->prepare("
            SELECT 
                pr.*,
                p.name as product_name,
                u.email as user_email,
                up.first_name,
                up.last_name
            FROM product_reviews pr
            JOIN products p ON pr.product_id = p.id
            JOIN users u ON pr.user_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE pr.is_approved = FALSE
            ORDER BY pr.created_at ASC
            LIMIT $limit OFFSET $offset
        ");
        
        $stmt->execute([]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get merchant reviews summary
     */
    public function getMerchantReviewsSummary($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(pr.id) as total_reviews,
                AVG(pr.rating) as average_rating,
                SUM(CASE WHEN pr.rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN pr.rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN pr.rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN pr.rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN pr.rating = 1 THEN 1 ELSE 0 END) as one_star
            FROM product_reviews pr
            JOIN products p ON pr.product_id = p.id
            WHERE p.merchant_id = ? AND pr.is_approved = TRUE
        ");
        
        $stmt->execute([$merchantId]);
        return $stmt->fetch();
    }
    
    /**
     * Check if user can review product
     */
    public function canUserReviewProduct($userId, $productId) {
        // Return false if tables don't exist
        if (!$this->tablesExist) {
            return ['can_review' => false, 'reason' => 'reviews_not_available'];
        }
        
        // Check if user already reviewed
        $stmt = $this->pdo->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        
        if ($stmt->fetch()) {
            return ['can_review' => false, 'reason' => 'already_reviewed'];
        }
        
        // Check if user purchased the product
        $stmt = $this->pdo->prepare("
            SELECT o.id, o.status FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE o.user_id = ? AND oi.product_id = ? AND o.status = 'delivered'
            ORDER BY o.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$userId, $productId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            return ['can_review' => false, 'reason' => 'no_purchase'];
        }
        
        return ['can_review' => true, 'order_id' => $order['id']];
    }
    
    /**
     * Get product reviews for sitemap/SEO
     */
    public function getReviewsForSEO($productId, $limit = 5) {
        $stmt = $this->pdo->prepare("
            SELECT 
                pr.rating,
                pr.title,
                pr.review_text,
                pr.created_at,
                CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(SUBSTRING(up.last_name, 1, 1), '')) as reviewer_name
            FROM product_reviews pr
            LEFT JOIN users u ON pr.user_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE pr.product_id = ? AND pr.is_approved = TRUE
            ORDER BY pr.helpful_count DESC, pr.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$productId, $limit]);
        return $stmt->fetchAll();
    }
}
?>