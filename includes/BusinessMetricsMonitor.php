<?php
/**
 * Business Metrics Monitoring System
 * Tracks key business metrics and sends alerts when thresholds are breached
 */

class BusinessMetricsMonitor {
    private $pdo;
    private $notificationSystem;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->notificationSystem = new NotificationSystem($pdo);
    }
    
    /**
     * Check all business metrics and send alerts if thresholds are breached
     */
    public function checkMetricsAndAlert() {
        try {
            $alerts = [];
            
            // Check sales velocity
            $salesVelocityAlert = $this->checkSalesVelocity();
            if ($salesVelocityAlert) {
                $alerts[] = $salesVelocityAlert;
            }
            
            // Check conversion rates
            $conversionRateAlert = $this->checkConversionRates();
            if ($conversionRateAlert) {
                $alerts[] = $conversionRateAlert;
            }
            
            // Check commission thresholds
            $commissionAlert = $this->checkCommissionThresholds();
            if ($commissionAlert) {
                $alerts[] = $commissionAlert;
            }
            
            // Check inventory levels
            $inventoryAlert = $this->checkInventoryLevels();
            if ($inventoryAlert) {
                $alerts[] = $inventoryAlert;
            }
            
            // Check financial metrics
            $financialAlert = $this->checkFinancialMetrics();
            if ($financialAlert) {
                $alerts[] = $financialAlert;
            }
            
            // Send alerts
            if (!empty($alerts)) {
                $this->sendAlerts($alerts);
            }
            
            return ['success' => true, 'alerts_sent' => count($alerts), 'alerts' => $alerts];
            
        } catch (Exception $e) {
            error_log("Business metrics monitoring failed: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Check sales velocity metrics
     */
    private function checkSalesVelocity() {
        try {
            // Get sales velocity for the last hour
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as orders_last_hour,
                    SUM(total_amount) as revenue_last_hour
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                AND status NOT IN ('cancelled', 'refunded')
            ");
            $result = $stmt->fetch();
            
            // Get average sales velocity for the last 24 hours
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*)/24 as avg_orders_per_hour,
                    SUM(total_amount)/24 as avg_revenue_per_hour
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND status NOT IN ('cancelled', 'refunded')
            ");
            $avg = $stmt->fetch();
            
            // Check if current velocity is significantly different from average
            if ($avg['avg_orders_per_hour'] > 0) {
                $orderVelocityChange = (($result['orders_last_hour'] - $avg['avg_orders_per_hour']) / $avg['avg_orders_per_hour']) * 100;
                $revenueVelocityChange = (($result['revenue_last_hour'] - $avg['avg_revenue_per_hour']) / $avg['avg_revenue_per_hour']) * 100;
                
                // Alert if velocity drops by more than 50% or increases by more than 200%
                if ($orderVelocityChange < -50 || $revenueVelocityChange < -50) {
                    return [
                        'type' => 'sales_velocity_drop',
                        'severity' => 'high',
                        'message' => "Sales velocity dropped significantly: {$result['orders_last_hour']} orders (avg: " . round($avg['avg_orders_per_hour'], 2) . "), $" . number_format($result['revenue_last_hour'], 2) . " revenue (avg: $" . number_format($avg['avg_revenue_per_hour'], 2) . ")"
                    ];
                } elseif ($orderVelocityChange > 200 || $revenueVelocityChange > 200) {
                    return [
                        'type' => 'sales_velocity_spike',
                        'severity' => 'medium',
                        'message' => "Sales velocity spiked: {$result['orders_last_hour']} orders (avg: " . round($avg['avg_orders_per_hour'], 2) . "), $" . number_format($result['revenue_last_hour'], 2) . " revenue (avg: $" . number_format($avg['avg_revenue_per_hour'], 2) . ")"
                    ];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Sales velocity check failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check conversion rate metrics
     */
    private function checkConversionRates() {
        try {
            // Get conversion rate for the last 24 hours
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(CASE WHEN status NOT IN ('cancelled', 'refunded') THEN 1 END) as completed_orders,
                    COUNT(*) as total_orders
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $result = $stmt->fetch();
            
            if ($result['total_orders'] > 0) {
                $conversionRate = ($result['completed_orders'] / $result['total_orders']) * 100;
                
                // Get average conversion rate for the last 7 days
                $stmt = $this->pdo->query("
                    SELECT 
                        COUNT(CASE WHEN status NOT IN ('cancelled', 'refunded') THEN 1 END) as completed_orders,
                        COUNT(*) as total_orders
                    FROM orders 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ");
                $avgResult = $stmt->fetch();
                
                $avgConversionRate = ($avgResult['total_orders'] > 0) ? ($avgResult['completed_orders'] / $avgResult['total_orders']) * 100 : 0;
                
                // Alert if conversion rate drops significantly
                if ($avgConversionRate > 0 && $conversionRate < ($avgConversionRate * 0.7)) {
                    return [
                        'type' => 'conversion_rate_drop',
                        'severity' => 'high',
                        'message' => "Conversion rate dropped to " . round($conversionRate, 2) . "% (7-day avg: " . round($avgConversionRate, 2) . "%)"
                    ];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Conversion rate check failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check commission thresholds
     */
    private function checkCommissionThresholds() {
        try {
            // Check for salespeople approaching commission tier thresholds
            $stmt = $this->pdo->query("
                SELECT 
                    sc.salesperson_id,
                    u.username,
                    sc.total_sales,
                    ct.min_sales_threshold,
                    ct.tier_name,
                    ct.commission_rate,
                    (ct.min_sales_threshold - sc.total_sales) as sales_needed
                FROM sales_commissions sc
                JOIN users u ON sc.salesperson_id = u.id
                JOIN commission_tiers ct ON ct.min_sales_threshold > sc.total_sales
                WHERE ct.min_sales_threshold - sc.total_sales <= 1000  -- Within $1000 of next tier
                ORDER BY sc.salesperson_id, ct.min_sales_threshold ASC
            ");
            $approachingTiers = $stmt->fetchAll();
            
            if (!empty($approachingTiers)) {
                $alerts = [];
                foreach ($approachingTiers as $tier) {
                    $alerts[] = [
                        'type' => 'commission_tier_approaching',
                        'severity' => 'info',
                        'message' => "Salesperson {$tier['username']} needs $" . number_format($tier['sales_needed'], 2) . " more sales to reach {$tier['tier_name']} tier ({$tier['commission_rate']}% commission)"
                    ];
                }
                return $alerts;
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Commission threshold check failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check inventory levels
     */
    private function checkInventoryLevels() {
        try {
            // Check for low stock items
            $stmt = $this->pdo->query("
                SELECT 
                    p.id,
                    p.name,
                    pv.sku,
                    pi.quantity_on_hand,
                    pi.reorder_point
                FROM product_inventory pi
                JOIN products p ON pi.product_id = p.id
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                WHERE pi.quantity_on_hand <= pi.reorder_point
                ORDER BY pi.quantity_on_hand ASC
                LIMIT 10
            ");
            $lowStockItems = $stmt->fetchAll();
            
            if (!empty($lowStockItems)) {
                $itemsList = [];
                foreach ($lowStockItems as $item) {
                    $itemsList[] = "{$item['name']} (SKU: {$item['sku']}) - {$item['quantity_on_hand']} in stock";
                }
                
                return [
                    'type' => 'low_inventory',
                    'severity' => 'high',
                    'message' => "Low inventory alert for " . count($lowStockItems) . " items: " . implode(', ', $itemsList)
                ];
            }
            
            // Check for out of stock items
            $stmt = $this->pdo->query("
                SELECT 
                    p.id,
                    p.name,
                    pv.sku,
                    pi.quantity_on_hand
                FROM product_inventory pi
                JOIN products p ON pi.product_id = p.id
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                WHERE pi.quantity_on_hand = 0
                ORDER BY p.name
                LIMIT 10
            ");
            $outOfStockItems = $stmt->fetchAll();
            
            if (!empty($outOfStockItems)) {
                $itemsList = [];
                foreach ($outOfStockItems as $item) {
                    $itemsList[] = "{$item['name']} (SKU: {$item['sku']})";
                }
                
                return [
                    'type' => 'out_of_stock',
                    'severity' => 'critical',
                    'message' => "Out of stock alert for " . count($outOfStockItems) . " items: " . implode(', ', $itemsList)
                ];
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log("Inventory level check failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check financial metrics
     */
    private function checkFinancialMetrics() {
        try {
            $alerts = [];
            
            // Check for negative margins
            $stmt = $this->pdo->query("
                SELECT 
                    p.id,
                    p.name,
                    pv.sku,
                    (p.price - p.cost) as margin,
                    ((p.price - p.cost) / p.price) * 100 as margin_percentage
                FROM products p
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                WHERE p.cost > 0 AND p.price > 0
                AND ((p.price - p.cost) / p.price) < 0.1  -- Less than 10% margin
                ORDER BY ((p.price - p.cost) / p.price) ASC
                LIMIT 10
            ");
            $lowMarginProducts = $stmt->fetchAll();
            
            if (!empty($lowMarginProducts)) {
                $itemsList = [];
                foreach ($lowMarginProducts as $item) {
                    $itemsList[] = "{$item['name']} (SKU: {$item['sku']}) - " . round($item['margin_percentage'], 2) . "% margin";
                }
                
                $alerts[] = [
                    'type' => 'low_margin',
                    'severity' => 'medium',
                    'message' => "Low margin alert for " . count($lowMarginProducts) . " products: " . implode(', ', $itemsList)
                ];
            }
            
            // Check for payment failures
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as failed_payments_last_24h
                FROM payment_transactions 
                WHERE status = 'failed'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $paymentResult = $stmt->fetch();
            
            if ($paymentResult['failed_payments_last_24h'] > 5) {
                $alerts[] = [
                    'type' => 'payment_failures',
                    'severity' => 'high',
                    'message' => "High number of payment failures: {$paymentResult['failed_payments_last_24h']} failed payments in the last 24 hours"
                ];
            }
            
            return !empty($alerts) ? $alerts : null;
            
        } catch (Exception $e) {
            error_log("Financial metrics check failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Send alerts to administrators
     */
    private function sendAlerts($alerts) {
        try {
            // Get admin users to notify
            $stmt = $this->pdo->prepare("
                SELECT id, email FROM users 
                WHERE role IN ('admin', 'super_admin') 
                AND status = 'active'
            ");
            $stmt->execute();
            $admins = $stmt->fetchAll();
            
            foreach ($alerts as $alert) {
                // Handle case where checkCommissionThresholds returns an array of alerts
                if (isset($alert[0]) && is_array($alert[0])) {
                    foreach ($alert as $subAlert) {
                        $this->sendAlertToAdmins($admins, $subAlert);
                    }
                } else {
                    $this->sendAlertToAdmins($admins, $alert);
                }
            }
            
        } catch (Exception $e) {
            error_log("Sending alerts failed: " . $e->getMessage());
        }
    }
    
    /**
     * Send alert to administrators
     */
    private function sendAlertToAdmins($admins, $alert) {
        $title = $this->formatAlertTitle($alert['type']);
        $message = $alert['message'];
        $isImportant = ($alert['severity'] === 'high' || $alert['severity'] === 'critical');
        
        foreach ($admins as $admin) {
            $this->notificationSystem->createNotification(
                $admin['id'],
                'system',
                $title,
                $message,
                '/admin/reports.php',
                'View Reports',
                ['alert_type' => $alert['type'], 'severity' => $alert['severity']],
                $isImportant
            );
        }
        
        // Log the alert
        error_log("Business Alert ({$alert['type']}): {$message}");
    }
    
    /**
     * Format alert title based on alert type
     */
    private function formatAlertTitle($alertType) {
        $titles = [
            'sales_velocity_drop' => 'Sales Velocity Drop',
            'sales_velocity_spike' => 'Sales Velocity Spike',
            'conversion_rate_drop' => 'Conversion Rate Drop',
            'commission_tier_approaching' => 'Commission Tier Approaching',
            'low_inventory' => 'Low Inventory Alert',
            'out_of_stock' => 'Out of Stock Alert',
            'low_margin' => 'Low Product Margin',
            'payment_failures' => 'Payment Failures Detected'
        ];
        
        return $titles[$alertType] ?? 'Business Alert';
    }
    
    /**
     * Get business metrics dashboard data
     */
    public function getDashboardMetrics() {
        try {
            $metrics = [];
            
            // Get sales metrics
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 END) as orders_last_hour,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as orders_last_24h,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as orders_last_7d,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN total_amount ELSE 0 END) as revenue_last_24h,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN total_amount ELSE 0 END) as revenue_last_7d
                FROM orders 
                WHERE status NOT IN ('cancelled', 'refunded')
            ");
            $metrics['sales'] = $stmt->fetch();
            
            // Get inventory metrics
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_products,
                    COUNT(CASE WHEN pi.quantity_on_hand <= pi.reorder_point THEN 1 END) as low_stock_items,
                    COUNT(CASE WHEN pi.quantity_on_hand = 0 THEN 1 END) as out_of_stock_items,
                    SUM(pi.quantity_on_hand * p.price) as total_inventory_value
                FROM product_inventory pi
                JOIN products p ON pi.product_id = p.id
            ");
            $metrics['inventory'] = $stmt->fetch();
            
            // Get financial metrics
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments_24h,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments_24h,
                    SUM(CASE WHEN status = 'completed' THEN net_amount ELSE 0 END) as total_revenue_24h
                FROM payment_transactions 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $metrics['financial'] = $stmt->fetch();
            
            // Get user metrics
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as new_users_24h,
                    COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as active_users_24h,
                    COUNT(*) as total_users
                FROM users 
                WHERE status = 'active'
            ");
            $metrics['users'] = $stmt->fetch();
            
            return $metrics;
            
        } catch (Exception $e) {
            error_log("Getting dashboard metrics failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get detailed sales report
     */
    public function getSalesReport($period = '7d') {
        try {
            $interval = '7 DAY';
            switch ($period) {
                case '1d':
                    $interval = '1 DAY';
                    break;
                case '7d':
                    $interval = '7 DAY';
                    break;
                case '30d':
                    $interval = '30 DAY';
                    break;
                case '90d':
                    $interval = '90 DAY';
                    break;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as orders,
                    SUM(total_amount) as revenue,
                    AVG(total_amount) as avg_order_value,
                    COUNT(DISTINCT customer_id) as unique_customers
                FROM orders 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL $interval)
                AND status NOT IN ('cancelled', 'refunded')
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");
            $stmt->execute();
            $dailyData = $stmt->fetchAll();
            
            // Calculate totals
            $totals = [
                'total_orders' => 0,
                'total_revenue' => 0,
                'avg_daily_orders' => 0,
                'avg_daily_revenue' => 0
            ];
            
            foreach ($dailyData as $day) {
                $totals['total_orders'] += $day['orders'];
                $totals['total_revenue'] += $day['revenue'];
            }
            
            if (count($dailyData) > 0) {
                $totals['avg_daily_orders'] = round($totals['total_orders'] / count($dailyData), 2);
                $totals['avg_daily_revenue'] = round($totals['total_revenue'] / count($dailyData), 2);
            }
            
            return [
                'daily_data' => $dailyData,
                'totals' => $totals
            ];
            
        } catch (Exception $e) {
            error_log("Getting sales report failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get top selling products
     */
    public function getTopSellingProducts($limit = 10, $period = '30d') {
        try {
            $interval = '30 DAY';
            switch ($period) {
                case '7d':
                    $interval = '7 DAY';
                    break;
                case '30d':
                    $interval = '30 DAY';
                    break;
                case '90d':
                    $interval = '90 DAY';
                    break;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id,
                    p.name,
                    pv.sku,
                    COUNT(oi.id) as units_sold,
                    SUM(oi.quantity * oi.price) as revenue
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN products p ON oi.product_id = p.id
                LEFT JOIN product_variants pv ON p.id = pv.product_id
                WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL $interval)
                AND o.status NOT IN ('cancelled', 'refunded')
                GROUP BY p.id, p.name, pv.sku
                ORDER BY revenue DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Getting top selling products failed: " . $e->getMessage());
            return false;
        }
    }
}
?>