<?php
/**
 * Business Logic Automation System
 * Automates business processes including commission tier progression,
 * inventory alerts, financial period closing, and marketing ROI calculations
 */

class BusinessAutomation {
    private $pdo;
    private $notificationSystem;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->notificationSystem = new NotificationSystem($pdo);
    }
    
    /**
     * Automatically progress sales commissions to next tier
     */
    public function autoProgressCommissionTiers() {
        try {
            // Get all salespeople with sales data
            $stmt = $this->pdo->query("
                SELECT 
                    sc.id,
                    sc.salesperson_id,
                    sc.total_sales,
                    sc.tier_level,
                    u.username,
                    u.email
                FROM sales_commissions sc
                JOIN users u ON sc.salesperson_id = u.id
            ");
            $salespeople = $stmt->fetchAll();
            
            $progressed = 0;
            $alerts = [];
            
            foreach ($salespeople as $salesperson) {
                // Get the next applicable tier
                $stmt = $this->pdo->prepare("
                    SELECT tier_name, commission_rate, min_sales_threshold
                    FROM commission_tiers
                    WHERE min_sales_threshold > ?
                    ORDER BY min_sales_threshold ASC
                    LIMIT 1
                ");
                $stmt->execute([$salesperson['total_sales']]);
                $nextTier = $stmt->fetch();
                
                // Check if salesperson qualifies for next tier
                if ($nextTier && $salesperson['tier_level'] !== $nextTier['tier_name']) {
                    // Update to next tier
                    $stmt = $this->pdo->prepare("
                        UPDATE sales_commissions
                        SET tier_level = ?, commission_rate = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $nextTier['tier_name'],
                        $nextTier['commission_rate'],
                        $salesperson['id']
                    ]);
                    
                    $progressed++;
                    
                    // Create notification for salesperson
                    $this->notificationSystem->createNotification(
                        $salesperson['salesperson_id'],
                        'promotion',
                        'Commission Tier Progression',
                        "Congratulations! You've progressed to the {$nextTier['tier_name']} commission tier. Your new commission rate is " . ($nextTier['commission_rate'] * 100) . "%.",
                        '/commission-dashboard.php',
                        'View Commission Dashboard',
                        ['tier' => $nextTier['tier_name'], 'rate' => $nextTier['commission_rate']],
                        true
                    );
                    
                    $alerts[] = [
                        'salesperson' => $salesperson['username'],
                        'new_tier' => $nextTier['tier_name'],
                        'old_tier' => $salesperson['tier_level']
                    ];
                }
            }
            
            return [
                'success' => true,
                'progressed_count' => $progressed,
                'alerts' => $alerts
            ];
            
        } catch (Exception $e) {
            error_log("Commission tier progression failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check inventory levels and send alerts for low stock
     */
    public function checkInventoryAndAlert() {
        try {
            // Get products with low inventory
            $stmt = $this->pdo->query("
                SELECT 
                    p.id,
                    p.name,
                    pv.sku,
                    pi.quantity_on_hand,
                    pi.reorder_point,
                    pi.location_id
                FROM product_inventory pi
                JOIN products p ON pi.product_id = p.id
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                WHERE pi.quantity_on_hand <= pi.reorder_point
                ORDER BY pi.quantity_on_hand ASC
            ");
            $lowStockItems = $stmt->fetchAll();
            
            $alerts = [];
            
            foreach ($lowStockItems as $item) {
                // Check if we've already sent an alert for this item recently
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as recent_alerts
                    FROM notifications
                    WHERE type = 'inventory'
                    AND data LIKE ?
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                ");
                $stmt->execute(['%"product_id":"' . $item['id'] . '"%']);
                $recentAlert = $stmt->fetch();
                
                // Only send alert if we haven't sent one recently
                if ($recentAlert['recent_alerts'] == 0) {
                    // Create notification for inventory managers
                    $stmt = $this->pdo->prepare("
                        SELECT id FROM users WHERE role IN ('admin', 'manager')
                    ");
                    $stmt->execute();
                    $managers = $stmt->fetchAll();
                    
                    foreach ($managers as $manager) {
                        $this->notificationSystem->createNotification(
                            $manager['id'],
                            'inventory',
                            'Low Inventory Alert',
                            "Product '{$item['name']}' (SKU: {$item['sku']}) is low on stock: {$item['quantity_on_hand']} remaining (reorder point: {$item['reorder_point']})",
                            '/admin/inventory.php',
                            'View Inventory',
                            ['product_id' => $item['id'], 'quantity' => $item['quantity_on_hand']],
                            true
                        );
                    }
                    
                    $alerts[] = [
                        'product' => $item['name'],
                        'sku' => $item['sku'],
                        'quantity' => $item['quantity_on_hand'],
                        'reorder_point' => $item['reorder_point']
                    ];
                }
            }
            
            return [
                'success' => true,
                'alert_count' => count($alerts),
                'alerts' => $alerts
            ];
            
        } catch (Exception $e) {
            error_log("Inventory alert check failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Automatically close financial periods (month-end processing)
     */
    public function autoCloseFinancialPeriod() {
        try {
            // Get current period (previous month)
            $period = date('Y-m', strtotime('first day of previous month'));
            
            // Check if period is already closed
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as closed_periods
                FROM financial_periods
                WHERE period = ?
            ");
            $stmt->execute([$period]);
            $existing = $stmt->fetch();
            
            if ($existing['closed_periods'] > 0) {
                return ['success' => true, 'message' => 'Period already closed'];
            }
            
            // Calculate period totals
            $stmt = $this->pdo->prepare("
                SELECT 
                    SUM(total_amount) as total_revenue,
                    COUNT(*) as total_orders
                FROM orders
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
                AND status NOT IN ('cancelled', 'refunded')
            ");
            $stmt->execute([$period]);
            $revenueData = $stmt->fetch();
            
            // Calculate commission expenses for the period
            $stmt = $this->pdo->prepare("
                SELECT SUM(commission_amount) as total_commissions
                FROM merchant_commissions
                WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
            ");
            $stmt->execute([$period]);
            $commissionData = $stmt->fetch();
            
            // Calculate marketing expenses for the period
            $stmt = $this->pdo->prepare("
                SELECT SUM(amount) as total_marketing
                FROM marketing_expenses
                WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?
            ");
            $stmt->execute([$period]);
            $marketingData = $stmt->fetch();
            
            // Insert period closure record
            $stmt = $this->pdo->prepare("
                INSERT INTO financial_periods (
                    period,
                    total_revenue,
                    total_orders,
                    total_commissions,
                    total_marketing,
                    net_income,
                    closed_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $netIncome = ($revenueData['total_revenue'] ?? 0) 
                        - ($commissionData['total_commissions'] ?? 0) 
                        - ($marketingData['total_marketing'] ?? 0);
            
            $stmt->execute([
                $period,
                $revenueData['total_revenue'] ?? 0,
                $revenueData['total_orders'] ?? 0,
                $commissionData['total_commissions'] ?? 0,
                $marketingData['total_marketing'] ?? 0,
                $netIncome
            ]);
            
            // Create notifications for admins
            $stmt = $this->pdo->prepare("
                SELECT id FROM users WHERE role = 'admin'
            ");
            $stmt->execute();
            $admins = $stmt->fetchAll();
            
            foreach ($admins as $admin) {
                $this->notificationSystem->createNotification(
                    $admin['id'],
                    'finance',
                    'Financial Period Closed',
                    "Financial period {$period} has been closed. Revenue: $" . number_format($revenueData['total_revenue'] ?? 0, 2) . ", Net Income: $" . number_format($netIncome, 2),
                    '/admin/financial-reports.php',
                    'View Reports',
                    ['period' => $period, 'net_income' => $netIncome],
                    true
                );
            }
            
            return [
                'success' => true,
                'period' => $period,
                'revenue' => $revenueData['total_revenue'] ?? 0,
                'net_income' => $netIncome
            ];
            
        } catch (Exception $e) {
            error_log("Financial period closing failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Calculate and update marketing ROI in real-time
     */
    public function calculateMarketingROI() {
        try {
            // Get recent marketing campaigns (last 30 days)
            $stmt = $this->pdo->query("
                SELECT 
                    mc.id,
                    mc.campaign_name,
                    mc.budget,
                    mc.start_date,
                    mc.end_date,
                    SUM(oi.quantity * oi.price) as campaign_revenue
                FROM marketing_campaigns mc
                LEFT JOIN marketing_attribution ma ON mc.id = ma.campaign_id
                LEFT JOIN order_items oi ON ma.order_id = oi.order_id
                WHERE mc.start_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY mc.id, mc.campaign_name, mc.budget, mc.start_date, mc.end_date
            ");
            $campaigns = $stmt->fetchAll();
            
            $updated = 0;
            
            foreach ($campaigns as $campaign) {
                // Calculate ROI
                $roi = 0;
                if ($campaign['budget'] > 0) {
                    $roi = (($campaign['campaign_revenue'] - $campaign['budget']) / $campaign['budget']) * 100;
                }
                
                // Update campaign with ROI
                $stmt = $this->pdo->prepare("
                    UPDATE marketing_campaigns
                    SET roi_percentage = ?, actual_revenue = ?
                    WHERE id = ?
                ");
                $stmt->execute([$roi, $campaign['campaign_revenue'], $campaign['id']]);
                
                $updated++;
                
                // If ROI is negative and significant, alert marketing team
                if ($roi < -20) { // More than 20% loss
                    // Create notification for marketing managers
                    $stmt = $this->pdo->prepare("
                        SELECT id FROM users WHERE role = 'manager' AND id IN (
                            SELECT user_id FROM user_roles WHERE role_name = 'marketing'
                        )
                    ");
                    $stmt->execute();
                    $marketers = $stmt->fetchAll();
                    
                    foreach ($marketers as $marketer) {
                        $this->notificationSystem->createNotification(
                            $marketer['id'],
                            'marketing',
                            'Campaign Underperforming',
                            "Campaign '{$campaign['campaign_name']}' is underperforming with ROI of {$roi}% (Revenue: $" . number_format($campaign['campaign_revenue'], 2) . ", Budget: $" . number_format($campaign['budget'], 2) . ")",
                            '/admin/marketing-campaigns.php',
                            'View Campaign',
                            ['campaign_id' => $campaign['id'], 'roi' => $roi],
                            true
                        );
                    }
                }
            }
            
            return [
                'success' => true,
                'updated_count' => $updated
            ];
            
        } catch (Exception $e) {
            error_log("Marketing ROI calculation failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Run all automated business processes
     */
    public function runAllAutomations() {
        $results = [];
        
        // Run commission tier progression
        $results['commission_tiers'] = $this->autoProgressCommissionTiers();
        
        // Run inventory alerts
        $results['inventory_alerts'] = $this->checkInventoryAndAlert();
        
        // Run marketing ROI calculation
        $results['marketing_roi'] = $this->calculateMarketingROI();
        
        // Check if it's the end of the month for financial closing
        if (date('j') == 1) { // First day of month
            $results['financial_closing'] = $this->autoCloseFinancialPeriod();
        }
        
        return $results;
    }
    
    /**
     * Get automation statistics
     */
    public function getAutomationStats() {
        try {
            $stats = [];
            
            // Get commission tier progression stats
            $stmt = $this->pdo->query("
                SELECT 
                    tier_level,
                    COUNT(*) as salesperson_count,
                    AVG(total_sales) as avg_sales
                FROM sales_commissions
                GROUP BY tier_level
                ORDER BY avg_sales DESC
            ");
            $stats['commission_tiers'] = $stmt->fetchAll();
            
            // Get inventory alert stats
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(CASE WHEN quantity_on_hand <= reorder_point THEN 1 END) as low_stock_items,
                    COUNT(CASE WHEN quantity_on_hand = 0 THEN 1 END) as out_of_stock_items,
                    COUNT(*) as total_items
                FROM product_inventory
            ");
            $stats['inventory'] = $stmt->fetch();
            
            // Get financial period stats
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as closed_periods,
                    SUM(net_income) as total_net_income,
                    AVG(net_income) as avg_period_income
                FROM financial_periods
            ");
            $stats['financial'] = $stmt->fetch();
            
            // Get marketing campaign stats
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_campaigns,
                    AVG(roi_percentage) as avg_roi,
                    COUNT(CASE WHEN roi_percentage < 0 THEN 1 END) as negative_roi_campaigns
                FROM marketing_campaigns
            ");
            $stats['marketing'] = $stmt->fetch();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Getting automation stats failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old automation records
     */
    public function cleanupOldRecords($daysOld = 90) {
        try {
            // Clean up old notifications (older than specified days)
            $stmt = $this->pdo->prepare("
                DELETE FROM notifications 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND type IN ('inventory', 'promotion', 'marketing', 'finance')
            ");
            $stmt->execute([$daysOld]);
            
            $deletedNotifications = $stmt->rowCount();
            
            // Clean up old security logs
            $stmt = $this->pdo->prepare("
                DELETE FROM security_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$daysOld]);
            
            $deletedLogs = $stmt->rowCount();
            
            return [
                'success' => true,
                'deleted_notifications' => $deletedNotifications,
                'deleted_logs' => $deletedLogs
            ];
            
        } catch (Exception $e) {
            error_log("Cleaning up old records failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>