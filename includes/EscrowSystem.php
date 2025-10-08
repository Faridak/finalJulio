<?php
/**
 * Escrow and Buyer Protection System
 * Provides secure transaction handling with fund holding until delivery confirmation
 */

require_once 'security.php';
require_once 'NotificationSystem.php';

class EscrowSystem {
    private $pdo;
    private $notificationSystem;
    
    // Escrow periods in days
    const DEFAULT_ESCROW_PERIOD = 14;
    const DIGITAL_GOODS_ESCROW_PERIOD = 3;
    const PHYSICAL_GOODS_ESCROW_PERIOD = 21;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->notificationSystem = new NotificationSystem($pdo);
    }
    
    /**
     * Create escrow for an order after payment
     */
    public function createEscrow($orderId, $paymentTransactionId) {
        try {
            $this->pdo->beginTransaction();
            
            // Get order details
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            if ($order['status'] !== 'paid') {
                throw new Exception("Order must be paid before creating escrow");
            }
            
            // Determine escrow period based on order type
            $escrowPeriod = $this->determineEscrowPeriod($orderId);
            $releaseDate = date('Y-m-d H:i:s', strtotime("+{$escrowPeriod} days"));
            
            // Create escrow record
            $stmt = $this->pdo->prepare("
                INSERT INTO escrow_transactions (
                    order_id, payment_transaction_id, buyer_id, seller_id, 
                    amount, escrow_fee, status, release_date, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ");
            
            $escrowFee = $this->calculateEscrowFee($order['total_amount']);
            
            $stmt->execute([
                $orderId,
                $paymentTransactionId,
                $order['customer_id'],
                $order['merchant_id'],
                $order['total_amount'],
                $escrowFee,
                $releaseDate
            ]);
            
            $escrowId = $this->pdo->lastInsertId();
            
            // Update order status
            $stmt = $this->pdo->prepare("
                UPDATE orders SET status = 'in_escrow', escrow_id = ? WHERE id = ?
            ");
            $stmt->execute([$escrowId, $orderId]);
            
            // Log escrow creation
            $this->logEscrowEvent($escrowId, 'created', $order['customer_id'], [
                'escrow_period_days' => $escrowPeriod,
                'release_date' => $releaseDate
            ]);
            
            // Send notifications
            $this->notificationSystem->notifyEscrowCreated(
                $order['customer_id'], 
                $order['merchant_id'], 
                $orderId, 
                $escrowPeriod
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'escrow_id' => $escrowId,
                'release_date' => $releaseDate,
                'protection_period_days' => $escrowPeriod
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            Security::logSecurityEvent('escrow_creation_failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ], 'error');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Buyer confirms receipt and releases escrow
     */
    public function confirmReceipt($escrowId, $buyerId, $rating = null, $feedback = '') {
        try {
            $this->pdo->beginTransaction();
            
            $escrow = $this->getEscrowDetails($escrowId);
            if (!$escrow) {
                throw new Exception("Escrow not found");
            }
            
            if ($escrow['buyer_id'] != $buyerId) {
                throw new Exception("Only the buyer can confirm receipt");
            }
            
            if ($escrow['status'] !== 'active') {
                throw new Exception("Escrow is not active");
            }
            
            // Release escrow
            $this->releaseEscrow($escrowId, 'buyer_confirmed', $buyerId);
            
            // Add buyer rating if provided
            if ($rating && $rating >= 1 && $rating <= 5) {
                $this->addSellerRating($escrow['order_id'], $escrow['seller_id'], $buyerId, $rating, $feedback);
            }
            
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'Receipt confirmed and payment released'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Buyer initiates a dispute
     */
    public function initiateDispute($escrowId, $buyerId, $reason, $description, $evidence = []) {
        try {
            $this->pdo->beginTransaction();
            
            $escrow = $this->getEscrowDetails($escrowId);
            if (!$escrow) {
                throw new Exception("Escrow not found");
            }
            
            if ($escrow['buyer_id'] != $buyerId) {
                throw new Exception("Only the buyer can initiate disputes");
            }
            
            if (!in_array($escrow['status'], ['active', 'shipped'])) {
                throw new Exception("Cannot dispute this escrow");
            }
            
            // Create dispute
            $stmt = $this->pdo->prepare("
                INSERT INTO escrow_disputes (
                    escrow_id, initiated_by, dispute_reason, description, 
                    evidence, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'open', NOW())
            ");
            
            $stmt->execute([
                $escrowId,
                $buyerId,
                $reason,
                $description,
                json_encode($evidence)
            ]);
            
            $disputeId = $this->pdo->lastInsertId();
            
            // Update escrow status
            $stmt = $this->pdo->prepare("
                UPDATE escrow_transactions SET status = 'disputed' WHERE id = ?
            ");
            $stmt->execute([$escrowId]);
            
            // Log dispute
            $this->logEscrowEvent($escrowId, 'dispute_initiated', $buyerId, [
                'dispute_id' => $disputeId,
                'reason' => $reason
            ]);
            
            // Notify seller and admin
            $this->notificationSystem->notifyDisputeInitiated(
                $escrow['seller_id'],
                $escrow['order_id'],
                $disputeId,
                $reason
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'dispute_id' => $disputeId,
                'message' => 'Dispute initiated. Our team will review within 24 hours.'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Seller marks order as shipped
     */
    public function markAsShipped($escrowId, $sellerId, $trackingNumber = '', $carrier = '') {
        try {
            $escrow = $this->getEscrowDetails($escrowId);
            if (!$escrow) {
                throw new Exception("Escrow not found");
            }
            
            if ($escrow['seller_id'] != $sellerId) {
                throw new Exception("Only the seller can mark as shipped");
            }
            
            if ($escrow['status'] !== 'active') {
                throw new Exception("Escrow is not in active status");
            }
            
            // Update escrow status
            $stmt = $this->pdo->prepare("
                UPDATE escrow_transactions 
                SET status = 'shipped', tracking_number = ?, carrier = ?, shipped_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$trackingNumber, $carrier, $escrowId]);
            
            // Update order status
            $stmt = $this->pdo->prepare("
                UPDATE orders SET status = 'shipped' WHERE id = ?
            ");
            $stmt->execute([$escrow['order_id']]);
            
            // Log shipping
            $this->logEscrowEvent($escrowId, 'marked_shipped', $sellerId, [
                'tracking_number' => $trackingNumber,
                'carrier' => $carrier
            ]);
            
            // Notify buyer
            $this->notificationSystem->notifyOrderShipped(
                $escrow['buyer_id'],
                $escrow['order_id'],
                $trackingNumber,
                $carrier
            );
            
            return [
                'success' => true,
                'message' => 'Order marked as shipped. Buyer has been notified.'
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Auto-release escrow after protection period
     */
    public function processAutoReleases() {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM escrow_transactions 
                WHERE status IN ('active', 'shipped') 
                AND release_date <= NOW()
                AND auto_release_processed = FALSE
            ");
            $stmt->execute();
            $expiredEscrows = $stmt->fetchAll();
            
            $releasedCount = 0;
            
            foreach ($expiredEscrows as $escrow) {
                try {
                    $this->releaseEscrow($escrow['id'], 'auto_released', null);
                    $releasedCount++;
                    
                    // Mark as processed
                    $stmt = $this->pdo->prepare("
                        UPDATE escrow_transactions SET auto_release_processed = TRUE WHERE id = ?
                    ");
                    $stmt->execute([$escrow['id']]);
                    
                } catch (Exception $e) {
                    // Log individual failures but continue processing
                    Security::logSecurityEvent('auto_release_failed', [
                        'escrow_id' => $escrow['id'],
                        'error' => $e->getMessage()
                    ], 'error');
                }
            }
            
            return [
                'success' => true,
                'processed' => count($expiredEscrows),
                'released' => $releasedCount
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Release escrow funds to seller
     */
    private function releaseEscrow($escrowId, $releaseReason, $releasedBy) {
        $escrow = $this->getEscrowDetails($escrowId);
        
        // Update escrow status
        $stmt = $this->pdo->prepare("
            UPDATE escrow_transactions 
            SET status = 'released', released_at = NOW(), release_reason = ?, released_by = ?
            WHERE id = ?
        ");
        $stmt->execute([$releaseReason, $releasedBy, $escrowId]);
        
        // Update order status
        $stmt = $this->pdo->prepare("
            UPDATE orders SET status = 'completed' WHERE id = ?
        ");
        $stmt->execute([$escrow['order_id']]);
        
        // Process merchant commission payout
        $this->processMerchantPayout($escrow['order_id'], $escrow['amount']);
        
        // Log release
        $this->logEscrowEvent($escrowId, 'released', $releasedBy, [
            'release_reason' => $releaseReason,
            'amount' => $escrow['amount']
        ]);
        
        // Send notifications
        $this->notificationSystem->notifyEscrowReleased(
            $escrow['seller_id'],
            $escrow['buyer_id'],
            $escrow['order_id'],
            $escrow['amount']
        );
    }
    
    /**
     * Get escrow details
     */
    public function getEscrowDetails($escrowId) {
        $stmt = $this->pdo->prepare("
            SELECT et.*, o.total_amount, o.status as order_status
            FROM escrow_transactions et
            JOIN orders o ON et.order_id = o.id
            WHERE et.id = ?
        ");
        $stmt->execute([$escrowId]);
        return $stmt->fetch();
    }
    
    /**
     * Get buyer's escrow transactions
     */
    public function getBuyerEscrows($buyerId, $status = null) {
        $whereClause = "et.buyer_id = ?";
        $params = [$buyerId];
        
        if ($status) {
            $whereClause .= " AND et.status = ?";
            $params[] = $status;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT et.*, o.id as order_number, o.created_at as order_date,
                   u.email as seller_email,
                   CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as seller_name
            FROM escrow_transactions et
            JOIN orders o ON et.order_id = o.id
            JOIN users u ON et.seller_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE {$whereClause}
            ORDER BY et.created_at DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get seller's escrow transactions
     */
    public function getSellerEscrows($sellerId, $status = null) {
        $whereClause = "et.seller_id = ?";
        $params = [$sellerId];
        
        if ($status) {
            $whereClause .= " AND et.status = ?";
            $params[] = $status;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT et.*, o.id as order_number, o.created_at as order_date,
                   u.email as buyer_email,
                   CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as buyer_name
            FROM escrow_transactions et
            JOIN orders o ON et.order_id = o.id
            JOIN users u ON et.buyer_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE {$whereClause}
            ORDER BY et.created_at DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Calculate escrow fee
     */
    private function calculateEscrowFee($amount) {
        // 1% escrow fee with minimum of $0.50
        return max(0.50, $amount * 0.01);
    }
    
    /**
     * Determine escrow period based on order
     */
    private function determineEscrowPeriod($orderId) {
        $stmt = $this->pdo->prepare("
            SELECT p.category, p.is_digital
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll();
        
        $hasDigital = false;
        $hasPhysical = false;
        
        foreach ($items as $item) {
            if ($item['is_digital']) {
                $hasDigital = true;
            } else {
                $hasPhysical = true;
            }
        }
        
        // Mixed orders use physical goods period
        if ($hasPhysical) {
            return self::PHYSICAL_GOODS_ESCROW_PERIOD;
        } elseif ($hasDigital) {
            return self::DIGITAL_GOODS_ESCROW_PERIOD;
        }
        
        return self::DEFAULT_ESCROW_PERIOD;
    }
    
    /**
     * Get order details
     */
    private function getOrderDetails($orderId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM orders WHERE id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }
    
    /**
     * Process merchant payout after escrow release
     */
    private function processMerchantPayout($orderId, $amount) {
        // Update merchant commission status
        $stmt = $this->pdo->prepare("
            UPDATE merchant_commissions 
            SET status = 'pending_payout' 
            WHERE order_id = ? AND status = 'on_hold'
        ");
        $stmt->execute([$orderId]);
    }
    
    /**
     * Add seller rating
     */
    private function addSellerRating($orderId, $sellerId, $buyerId, $rating, $feedback) {
        $stmt = $this->pdo->prepare("
            INSERT INTO merchant_ratings (
                order_id, merchant_id, buyer_id, rating, 
                feedback_text, communication_rating, shipping_speed_rating, 
                item_description_rating, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        // Use overall rating for specific categories as default
        $stmt->execute([
            $orderId, $sellerId, $buyerId, $rating, $feedback,
            $rating, $rating, $rating
        ]);
    }
    
    /**
     * Log escrow events
     */
    private function logEscrowEvent($escrowId, $event, $userId, $metadata = []) {
        $stmt = $this->pdo->prepare("
            INSERT INTO escrow_logs (
                escrow_id, event_type, user_id, metadata, created_at
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $escrowId,
            $event,
            $userId,
            json_encode($metadata)
        ]);
    }
    
    /**
     * Get escrow statistics for admin
     */
    public function getEscrowStatistics($period = '30_days') {
        $dateCondition = match($period) {
            '7_days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
            '30_days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
            '90_days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
            '1_year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
            default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
        };
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_escrows,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_escrows,
                COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped_escrows,
                COUNT(CASE WHEN status = 'released' THEN 1 END) as released_escrows,
                COUNT(CASE WHEN status = 'disputed' THEN 1 END) as disputed_escrows,
                COALESCE(SUM(amount), 0) as total_amount,
                COALESCE(SUM(CASE WHEN status IN ('active', 'shipped', 'disputed') THEN amount ELSE 0 END), 0) as held_amount,
                COALESCE(SUM(escrow_fee), 0) as total_fees_collected,
                AVG(DATEDIFF(COALESCE(released_at, NOW()), created_at)) as avg_holding_days
            FROM escrow_transactions
            WHERE created_at >= {$dateCondition}
        ");
        
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Process dispute resolution (admin function)
     */
    public function resolveDispute($disputeId, $adminId, $resolution, $award_to_buyer_percentage = 0) {
        try {
            $this->pdo->beginTransaction();
            
            // Get dispute details
            $stmt = $this->pdo->prepare("
                SELECT ed.*, et.amount, et.escrow_id
                FROM escrow_disputes ed
                JOIN escrow_transactions et ON ed.escrow_id = et.id
                WHERE ed.id = ?
            ");
            $stmt->execute([$disputeId]);
            $dispute = $stmt->fetch();
            
            if (!$dispute) {
                throw new Exception("Dispute not found");
            }
            
            // Update dispute status
            $stmt = $this->pdo->prepare("
                UPDATE escrow_disputes 
                SET status = 'resolved', resolved_by = ?, resolution = ?, 
                    award_to_buyer_percentage = ?, resolved_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$adminId, $resolution, $award_to_buyer_percentage, $disputeId]);
            
            // Process refund/release based on resolution
            if ($award_to_buyer_percentage > 0) {
                $this->processDisputeRefund($dispute['escrow_id'], $award_to_buyer_percentage);
            } else {
                $this->releaseEscrow($dispute['escrow_id'], 'dispute_resolved_seller', $adminId);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Dispute resolved successfully'];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Process partial or full refund for dispute resolution
     */
    private function processDisputeRefund($escrowId, $refundPercentage) {
        $escrow = $this->getEscrowDetails($escrowId);
        $refundAmount = $escrow['amount'] * ($refundPercentage / 100);
        $sellerAmount = $escrow['amount'] - $refundAmount;
        
        // Update escrow status
        $stmt = $this->pdo->prepare("
            UPDATE escrow_transactions 
            SET status = 'partially_refunded', released_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$escrowId]);
        
        // Process refund to buyer (this would integrate with payment gateway)
        // Process partial payment to seller if applicable
        
        // Log the resolution
        $this->logEscrowEvent($escrowId, 'dispute_resolved', null, [
            'refund_percentage' => $refundPercentage,
            'refund_amount' => $refundAmount,
            'seller_amount' => $sellerAmount
        ]);
    }
}
?>