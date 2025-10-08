<?php
/**
 * Advanced Commission Management System
 * Handles dynamic commission rates, tiered structures, and performance-based adjustments
 */

require_once 'security.php';

class CommissionManagementSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate commission for an order with dynamic rates
     */
    public function calculateCommission($orderId, $merchantId, $orderAmount, $productCategory = null) {
        try {
            // Get merchant's commission tier
            $merchantTier = $this->getMerchantCommissionTier($merchantId);
            
            // Get base commission rate for category
            $baseRate = $this->getCategoryCommissionRate($productCategory, $merchantTier);
            
            // Apply volume discounts
            $volumeDiscount = $this->calculateVolumeDiscount($merchantId, $orderAmount);
            
            // Apply performance bonuses/penalties
            $performanceAdjustment = $this->calculatePerformanceAdjustment($merchantId);
            
            // Calculate final commission rate
            $finalRate = $baseRate - $volumeDiscount + $performanceAdjustment;
            $finalRate = max(0.01, min(0.15, $finalRate)); // Cap between 1% and 15%
            
            // Calculate amounts
            $commissionAmount = $orderAmount * $finalRate;
            $platformFee = $this->calculatePlatformFee($orderAmount);
            $paymentProcessingFee = $this->calculatePaymentProcessingFee($orderAmount);
            
            $totalFees = $commissionAmount + $platformFee + $paymentProcessingFee;
            $merchantNet = $orderAmount - $totalFees;
            
            return [
                'order_id' => $orderId,
                'merchant_id' => $merchantId,
                'gross_amount' => $orderAmount,
                'base_commission_rate' => $baseRate,
                'final_commission_rate' => $finalRate,
                'commission_amount' => $commissionAmount,
                'platform_fee' => $platformFee,
                'payment_processing_fee' => $paymentProcessingFee,
                'total_fees' => $totalFees,
                'merchant_net_amount' => $merchantNet,
                'tier' => $merchantTier,
                'volume_discount' => $volumeDiscount,
                'performance_adjustment' => $performanceAdjustment,
                'breakdown' => [
                    'base_rate' => number_format($baseRate * 100, 2) . '%',
                    'volume_discount' => '-' . number_format($volumeDiscount * 100, 2) . '%',
                    'performance_adjustment' => ($performanceAdjustment >= 0 ? '+' : '') . number_format($performanceAdjustment * 100, 2) . '%',
                    'final_rate' => number_format($finalRate * 100, 2) . '%'
                ]
            ];
            
        } catch (Exception $e) {
            throw new Exception("Commission calculation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Record commission for completed order
     */
    public function recordCommission($commissionData) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO merchant_commissions (
                    order_id, merchant_id, gross_amount, commission_rate, 
                    commission_amount, platform_fee, payment_processing_fee,
                    net_amount, tier, volume_discount, performance_adjustment,
                    calculation_details, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_payout', NOW())
            ");
            
            $stmt->execute([
                $commissionData['order_id'],
                $commissionData['merchant_id'],
                $commissionData['gross_amount'],
                $commissionData['final_commission_rate'],
                $commissionData['commission_amount'],
                $commissionData['platform_fee'],
                $commissionData['payment_processing_fee'],
                $commissionData['merchant_net_amount'],
                $commissionData['tier'],
                $commissionData['volume_discount'],
                $commissionData['performance_adjustment'],
                json_encode($commissionData['breakdown'])
            ]);
            
            $commissionId = $this->pdo->lastInsertId();
            
            // Update merchant statistics
            $this->updateMerchantStats($commissionData['merchant_id'], $commissionData['gross_amount']);
            
            // Check for tier upgrades
            $this->checkTierUpgrade($commissionData['merchant_id']);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'commission_id' => $commissionId,
                'merchant_net' => $commissionData['merchant_net_amount'],
                'commission_rate' => $commissionData['final_commission_rate']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get merchant's current commission tier
     */
    public function getMerchantCommissionTier($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT ct.tier_name, ct.commission_rate, ct.min_volume, ct.min_orders
            FROM merchant_tier_history mth
            JOIN commission_tiers ct ON mth.tier_id = ct.id
            WHERE mth.merchant_id = ? AND mth.is_current = TRUE
        ");
        $stmt->execute([$merchantId]);
        $tier = $stmt->fetch();
        
        if (!$tier) {
            // Assign default tier
            return $this->assignDefaultTier($merchantId);
        }
        
        return $tier['tier_name'];
    }
    
    /**
     * Get commission rate for product category and tier
     */
    private function getCategoryCommissionRate($category, $tier) {
        // Get category-specific rate
        $stmt = $this->pdo->prepare("
            SELECT ccr.commission_rate 
            FROM category_commission_rates ccr
            JOIN commission_tiers ct ON ccr.tier_id = ct.id
            WHERE ccr.category = ? AND ct.tier_name = ?
        ");
        $stmt->execute([$category, $tier]);
        $categoryRate = $stmt->fetchColumn();
        
        if ($categoryRate !== false) {
            return $categoryRate;
        }
        
        // Get default tier rate
        $stmt = $this->pdo->prepare("
            SELECT commission_rate FROM commission_tiers WHERE tier_name = ?
        ");
        $stmt->execute([$tier]);
        $defaultRate = $stmt->fetchColumn();
        
        return $defaultRate ?: 0.05; // 5% fallback
    }
    
    /**
     * Calculate volume discount based on merchant's performance
     */
    private function calculateVolumeDiscount($merchantId, $currentOrderAmount) {
        // Get merchant's volume in last 30 days
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(gross_amount), 0) as total_volume,
                COUNT(*) as order_count
            FROM merchant_commissions 
            WHERE merchant_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$merchantId]);
        $stats = $stmt->fetch();
        
        $totalVolume = $stats['total_volume'] + $currentOrderAmount;
        
        // Volume discount tiers
        if ($totalVolume >= 100000) {
            return 0.015; // 1.5% discount
        } elseif ($totalVolume >= 50000) {
            return 0.01; // 1% discount
        } elseif ($totalVolume >= 25000) {
            return 0.005; // 0.5% discount
        }
        
        return 0;
    }
    
    /**
     * Calculate performance-based adjustment
     */
    private function calculatePerformanceAdjustment($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                AVG(rating) as avg_rating,
                COUNT(*) as rating_count,
                AVG(CASE WHEN r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN rating END) as recent_avg_rating
            FROM merchant_ratings r
            WHERE r.merchant_id = ?
        ");
        $stmt->execute([$merchantId]);
        $ratings = $stmt->fetch();
        
        if (!$ratings['rating_count']) {
            return 0; // No adjustment for new merchants
        }
        
        $avgRating = $ratings['recent_avg_rating'] ?: $ratings['avg_rating'];
        
        // Performance adjustments
        if ($avgRating >= 4.8) {
            return -0.005; // 0.5% commission reduction (bonus)
        } elseif ($avgRating >= 4.5) {
            return -0.0025; // 0.25% reduction
        } elseif ($avgRating < 3.5) {
            return 0.01; // 1% penalty
        } elseif ($avgRating < 4.0) {
            return 0.005; // 0.5% penalty
        }
        
        return 0;
    }
    
    /**
     * Calculate platform fee (separate from commission)
     */
    private function calculatePlatformFee($orderAmount) {
        // Fixed platform fee structure
        if ($orderAmount >= 1000) {
            return $orderAmount * 0.005; // 0.5% for high-value orders
        } elseif ($orderAmount >= 100) {
            return $orderAmount * 0.01; // 1% for medium orders
        } else {
            return max(0.30, $orderAmount * 0.015); // 1.5% with $0.30 minimum
        }
    }
    
    /**
     * Calculate payment processing fee
     */
    private function calculatePaymentProcessingFee($orderAmount) {
        // Standard payment processing: 2.9% + $0.30
        return ($orderAmount * 0.029) + 0.30;
    }
    
    /**
     * Update merchant statistics
     */
    private function updateMerchantStats($merchantId, $orderAmount) {
        $stmt = $this->pdo->prepare("
            INSERT INTO merchant_statistics (
                merchant_id, total_sales, order_count, last_sale_date
            ) VALUES (?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                total_sales = total_sales + VALUES(total_sales),
                order_count = order_count + 1,
                last_sale_date = NOW()
        ");
        
        $stmt->execute([$merchantId, $orderAmount]);
    }
    
    /**
     * Check and process tier upgrades
     */
    private function checkTierUpgrade($merchantId) {
        // Get current stats
        $stmt = $this->pdo->prepare("
            SELECT total_sales, order_count FROM merchant_statistics WHERE merchant_id = ?
        ");
        $stmt->execute([$merchantId]);
        $stats = $stmt->fetch();
        
        if (!$stats) return;
        
        // Get eligible tier
        $stmt = $this->pdo->prepare("
            SELECT id, tier_name, commission_rate
            FROM commission_tiers 
            WHERE min_volume <= ? AND min_orders <= ?
            ORDER BY tier_level DESC
            LIMIT 1
        ");
        $stmt->execute([$stats['total_sales'], $stats['order_count']]);
        $eligibleTier = $stmt->fetch();
        
        if (!$eligibleTier) return;
        
        // Check if upgrade needed
        $stmt = $this->pdo->prepare("
            SELECT tier_id FROM merchant_tier_history 
            WHERE merchant_id = ? AND is_current = TRUE
        ");
        $stmt->execute([$merchantId]);
        $currentTierId = $stmt->fetchColumn();
        
        if ($currentTierId != $eligibleTier['id']) {
            $this->upgradeMerchantTier($merchantId, $eligibleTier['id'], $currentTierId);
        }
    }
    
    /**
     * Upgrade merchant to new tier
     */
    private function upgradeMerchantTier($merchantId, $newTierId, $oldTierId) {
        try {
            $this->pdo->beginTransaction();
            
            // End current tier
            if ($oldTierId) {
                $stmt = $this->pdo->prepare("
                    UPDATE merchant_tier_history 
                    SET is_current = FALSE, ended_at = NOW()
                    WHERE merchant_id = ? AND tier_id = ? AND is_current = TRUE
                ");
                $stmt->execute([$merchantId, $oldTierId]);
            }
            
            // Start new tier
            $stmt = $this->pdo->prepare("
                INSERT INTO merchant_tier_history (
                    merchant_id, tier_id, started_at, is_current
                ) VALUES (?, ?, NOW(), TRUE)
            ");
            $stmt->execute([$merchantId, $newTierId]);
            
            // Log tier change
            Security::logSecurityEvent('tier_upgrade', [
                'merchant_id' => $merchantId,
                'old_tier_id' => $oldTierId,
                'new_tier_id' => $newTierId
            ], 'info');
            
            $this->pdo->commit();
            
            // Send notification to merchant
            $this->notifyTierUpgrade($merchantId, $newTierId);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get commission analytics for admin
     */
    public function getCommissionAnalytics($period = '30_days') {
        $dateCondition = match($period) {
            '7_days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
            '30_days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
            '90_days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
            '1_year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
            default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
        };
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COUNT(DISTINCT merchant_id) as active_merchants,
                COALESCE(SUM(gross_amount), 0) as total_gross_volume,
                COALESCE(SUM(commission_amount), 0) as total_commission,
                COALESCE(SUM(platform_fee), 0) as total_platform_fees,
                COALESCE(SUM(payment_processing_fee), 0) as total_processing_fees,
                COALESCE(SUM(net_amount), 0) as total_merchant_net,
                AVG(commission_rate) as avg_commission_rate,
                AVG(volume_discount) as avg_volume_discount,
                AVG(performance_adjustment) as avg_performance_adjustment
            FROM merchant_commissions 
            WHERE created_at >= {$dateCondition}
        ");
        
        $stmt->execute();
        $analytics = $stmt->fetch();
        
        // Get tier distribution
        $stmt = $this->pdo->prepare("
            SELECT 
                ct.tier_name,
                COUNT(DISTINCT mc.merchant_id) as merchant_count,
                COALESCE(SUM(mc.gross_amount), 0) as volume,
                AVG(mc.commission_rate) as avg_rate
            FROM merchant_commissions mc
            JOIN merchant_tier_history mth ON mc.merchant_id = mth.merchant_id AND mth.is_current = TRUE
            JOIN commission_tiers ct ON mth.tier_id = ct.id
            WHERE mc.created_at >= {$dateCondition}
            GROUP BY ct.tier_name, ct.tier_level
            ORDER BY ct.tier_level
        ");
        $stmt->execute();
        $tierDistribution = $stmt->fetchAll();
        
        return [
            'summary' => $analytics,
            'tier_distribution' => $tierDistribution,
            'platform_revenue' => $analytics['total_commission'] + $analytics['total_platform_fees'],
            'effective_commission_rate' => $analytics['total_gross_volume'] > 0 
                ? ($analytics['total_commission'] / $analytics['total_gross_volume']) 
                : 0
        ];
    }
    
    /**
     * Get merchant commission report
     */
    public function getMerchantCommissionReport($merchantId, $period = '30_days') {
        $dateCondition = match($period) {
            '7_days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
            '30_days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
            '90_days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
            '1_year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
            default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
        };
        
        // Get commission summary
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(gross_amount), 0) as total_gross,
                COALESCE(SUM(commission_amount), 0) as total_commission,
                COALESCE(SUM(platform_fee), 0) as total_platform_fees,
                COALESCE(SUM(payment_processing_fee), 0) as total_processing_fees,
                COALESCE(SUM(net_amount), 0) as total_net,
                AVG(commission_rate) as avg_commission_rate,
                AVG(volume_discount) as avg_volume_discount,
                AVG(performance_adjustment) as avg_performance_adjustment
            FROM merchant_commissions 
            WHERE merchant_id = ? AND created_at >= {$dateCondition}
        ");
        $stmt->execute([$merchantId]);
        $summary = $stmt->fetch();
        
        // Get detailed commission history
        $stmt = $this->pdo->prepare("
            SELECT 
                mc.*,
                o.created_at as order_date,
                o.status as order_status
            FROM merchant_commissions mc
            JOIN orders o ON mc.order_id = o.id
            WHERE mc.merchant_id = ? AND mc.created_at >= {$dateCondition}
            ORDER BY mc.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$merchantId]);
        $commissions = $stmt->fetchAll();
        
        // Get current tier info
        $currentTier = $this->getMerchantTierInfo($merchantId);
        
        return [
            'summary' => $summary,
            'commissions' => $commissions,
            'current_tier' => $currentTier,
            'potential_savings' => $this->calculatePotentialSavings($merchantId),
            'next_tier_requirements' => $this->getNextTierRequirements($merchantId)
        ];
    }
    
    /**
     * Calculate commission structure scenarios
     */
    public function calculateCommissionScenarios($merchantId, $projectedVolume) {
        $scenarios = [];
        
        // Get all tiers
        $stmt = $this->pdo->prepare("
            SELECT * FROM commission_tiers ORDER BY tier_level
        ");
        $stmt->execute();
        $tiers = $stmt->fetchAll();
        
        foreach ($tiers as $tier) {
            $baseRate = $tier['commission_rate'];
            $volumeDiscount = $this->calculateVolumeDiscount($merchantId, $projectedVolume);
            $performanceAdjustment = $this->calculatePerformanceAdjustment($merchantId);
            
            $finalRate = max(0.01, min(0.15, $baseRate - $volumeDiscount + $performanceAdjustment));
            $commission = $projectedVolume * $finalRate;
            $platformFee = $this->calculatePlatformFee($projectedVolume);
            $processingFee = $this->calculatePaymentProcessingFee($projectedVolume);
            
            $scenarios[] = [
                'tier' => $tier['tier_name'],
                'base_rate' => $baseRate,
                'final_rate' => $finalRate,
                'commission' => $commission,
                'platform_fee' => $platformFee,
                'processing_fee' => $processingFee,
                'total_fees' => $commission + $platformFee + $processingFee,
                'merchant_net' => $projectedVolume - ($commission + $platformFee + $processingFee),
                'requirements_met' => $this->checkTierRequirements($merchantId, $tier)
            ];
        }
        
        return $scenarios;
    }
    
    /**
     * Helper methods
     */
    private function assignDefaultTier($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM commission_tiers WHERE tier_name = 'Starter' LIMIT 1
        ");
        $stmt->execute();
        $tierId = $stmt->fetchColumn();
        
        if ($tierId) {
            $stmt = $this->pdo->prepare("
                INSERT INTO merchant_tier_history (merchant_id, tier_id, started_at, is_current)
                VALUES (?, ?, NOW(), TRUE)
            ");
            $stmt->execute([$merchantId, $tierId]);
        }
        
        return 'Starter';
    }
    
    private function getMerchantTierInfo($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT ct.*, mth.started_at
            FROM merchant_tier_history mth
            JOIN commission_tiers ct ON mth.tier_id = ct.id
            WHERE mth.merchant_id = ? AND mth.is_current = TRUE
        ");
        $stmt->execute([$merchantId]);
        return $stmt->fetch();
    }
    
    private function calculatePotentialSavings($merchantId) {
        // Calculate savings if merchant reached next tier
        $nextTier = $this->getNextTierRequirements($merchantId);
        if (!$nextTier) return null;
        
        $currentVolume = $this->getMerchantVolume($merchantId, 30);
        $currentRate = $this->getCategoryCommissionRate(null, $this->getMerchantCommissionTier($merchantId));
        $nextTierRate = $nextTier['commission_rate'];
        
        $savings = $currentVolume * ($currentRate - $nextTierRate);
        
        return [
            'monthly_savings' => $savings,
            'annual_savings' => $savings * 12,
            'next_tier' => $nextTier['tier_name']
        ];
    }
    
    private function getNextTierRequirements($merchantId) {
        $currentTier = $this->getMerchantTierInfo($merchantId);
        if (!$currentTier) return null;
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM commission_tiers 
            WHERE tier_level > ? 
            ORDER BY tier_level ASC 
            LIMIT 1
        ");
        $stmt->execute([$currentTier['tier_level']]);
        return $stmt->fetch();
    }
    
    private function getMerchantVolume($merchantId, $days) {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(gross_amount), 0)
            FROM merchant_commissions 
            WHERE merchant_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$merchantId, $days]);
        return $stmt->fetchColumn();
    }
    
    private function checkTierRequirements($merchantId, $tier) {
        $stats = $this->getMerchantStats($merchantId);
        return $stats['total_sales'] >= $tier['min_volume'] && 
               $stats['order_count'] >= $tier['min_orders'];
    }
    
    private function getMerchantStats($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT total_sales, order_count FROM merchant_statistics WHERE merchant_id = ?
        ");
        $stmt->execute([$merchantId]);
        $stats = $stmt->fetch();
        
        return $stats ?: ['total_sales' => 0, 'order_count' => 0];
    }
    
    private function notifyTierUpgrade($merchantId, $newTierId) {
        // Implementation would send notification about tier upgrade
        // This could integrate with the NotificationSystem
    }
}
?>