<?php
/**
 * Analytics Setup and Data Population Script
 * Sets up analytics tables and populates them with sample data
 */

require_once 'config/database.php';

echo "<h2>Setting up Analytics System...</h2>\n";

try {
    // Read and execute the analytics schema
    $schemaFile = 'analytics_schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Analytics schema file not found: $schemaFile");
    }
    
    $schema = file_get_contents($schemaFile);
    $statements = explode(';', $schema);
    
    echo "<p>Executing analytics schema...</p>\n";
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo ".";
            } catch (PDOException $e) {
                // Ignore warnings about existing tables/columns
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'Duplicate column') === false) {
                    echo "<br>Warning: " . $e->getMessage() . "<br>\n";
                }
            }
        }
    }
    echo "<br><p>Analytics schema setup completed.</p>\n";
    
    // Populate sample analytics data
    echo "<p>Populating sample analytics data...</p>\n";
    
    // Generate sample product views
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO product_views (product_id, user_id, session_id, ip_address, view_duration, created_at)
        SELECT 
            p.id,
            CASE WHEN RAND() > 0.3 THEN u.id ELSE NULL END,
            CONCAT('sess_', FLOOR(RAND() * 10000)),
            CONCAT('192.168.1.', FLOOR(RAND() * 255)),
            FLOOR(RAND() * 300) + 30,
            DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 90) DAY)
        FROM products p
        CROSS JOIN (SELECT id FROM users WHERE role = 'customer' LIMIT 20) u
        WHERE RAND() < 0.1
        LIMIT 1000
    ");
    $stmt->execute();
    echo "Sample product views created.<br>\n";
    
    // Update product view counts
    $stmt = $pdo->prepare("
        UPDATE products p 
        SET views_count = (
            SELECT COUNT(*) FROM product_views pv WHERE pv.product_id = p.id
        )
    ");
    $stmt->execute();
    echo "Product view counts updated.<br>\n";
    
    // Generate sample search analytics
    $searchTerms = [
        'laptop', 'smartphone', 'headphones', 'camera', 'gaming', 'wireless', 'bluetooth',
        'laptop computer', 'phone case', 'charger', 'tablet', 'monitor', 'keyboard',
        'mouse', 'speaker', 'watch', 'fitness tracker', 'home decor', 'clothing'
    ];
    
    foreach ($searchTerms as $term) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO search_analytics (search_query, user_id, session_id, results_count, created_at)
            SELECT 
                ?,
                CASE WHEN RAND() > 0.4 THEN u.id ELSE NULL END,
                CONCAT('sess_', FLOOR(RAND() * 10000)),
                FLOOR(RAND() * 50) + 1,
                DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 90) DAY)
            FROM (SELECT id FROM users WHERE role = 'customer' LIMIT 10) u
            WHERE RAND() < 0.3
        ");
        $stmt->execute([$term]);
    }
    echo "Sample search analytics created.<br>\n";
    
    // Generate sample user sessions
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO user_sessions (session_id, user_id, ip_address, pages_visited, session_duration, created_at)
        SELECT 
            CONCAT('sess_', FLOOR(RAND() * 50000)),
            CASE WHEN RAND() > 0.2 THEN u.id ELSE NULL END,
            CONCAT('192.168.1.', FLOOR(RAND() * 255)),
            FLOOR(RAND() * 20) + 1,
            FLOOR(RAND() * 3600) + 60,
            DATE_SUB(NOW(), INTERVAL FLOOR(RAND() * 90) DAY)
        FROM (SELECT id FROM users LIMIT 50) u
        WHERE RAND() < 0.5
    ");
    $stmt->execute();
    echo "Sample user sessions created.<br>\n";
    
    // Calculate and populate merchant daily metrics for the last 30 days
    echo "<p>Calculating merchant daily metrics...</p>\n";
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'merchant'");
    $stmt->execute();
    $merchants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($merchants as $merchantId) {
        for ($i = 0; $i < 30; $i++) {
            $date = date('Y-m-d', strtotime("-$i days"));
            
            try {
                $stmt = $pdo->prepare("CALL CalculateMerchantMetrics(?, ?)");
                $stmt->execute([$merchantId, $date]);
            } catch (PDOException $e) {
                // Handle procedure call errors gracefully
                echo "Note: Stored procedure may not be available. Calculating metrics manually.<br>\n";
                
                // Manual calculation as fallback
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO merchant_daily_metrics (
                        merchant_id, date, orders_count, orders_value, 
                        average_order_value, commission_earned
                    )
                    SELECT 
                        ? as merchant_id,
                        ? as date,
                        COUNT(o.id) as orders_count,
                        COALESCE(SUM(o.total_amount), 0) as orders_value,
                        COALESCE(AVG(o.total_amount), 0) as average_order_value,
                        COALESCE(SUM(mc.commission_amount), 0) as commission_earned
                    FROM orders o
                    LEFT JOIN merchant_commissions mc ON o.id = mc.order_id
                    WHERE o.merchant_id = ? AND DATE(o.created_at) = ?
                ");
                $stmt->execute([$merchantId, $date, $merchantId, $date]);
                break; // Exit the merchant loop on first error to avoid spam
            }
        }
    }
    echo "Merchant daily metrics calculated.<br>\n";
    
    // Calculate platform daily metrics
    echo "<p>Calculating platform daily metrics...</p>\n";
    
    for ($i = 0; $i < 30; $i++) {
        $date = date('Y-m-d', strtotime("-$i days"));
        
        try {
            $stmt = $pdo->prepare("CALL CalculatePlatformMetrics(?)");
            $stmt->execute([$date]);
        } catch (PDOException $e) {
            // Manual calculation as fallback
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO platform_daily_metrics (
                    date, total_users, new_users, total_orders, orders_value
                )
                SELECT 
                    ? as date,
                    (SELECT COUNT(*) FROM users WHERE DATE(created_at) <= ?) as total_users,
                    (SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?) as new_users,
                    COUNT(o.id) as total_orders,
                    COALESCE(SUM(o.total_amount), 0) as orders_value
                FROM orders o
                WHERE DATE(o.created_at) = ?
            ");
            $stmt->execute([$date, $date, $date, $date]);
            break; // Exit loop on first error
        }
    }
    echo "Platform daily metrics calculated.<br>\n";
    
    // Create sample category metrics
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO category_metrics (category, date, product_count, orders_count, revenue)
        SELECT 
            p.category,
            CURDATE() as date,
            COUNT(DISTINCT p.id) as product_count,
            COUNT(o.id) as orders_count,
            COALESCE(SUM(o.total_amount), 0) as revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.created_at) = CURDATE()
        WHERE p.category IS NOT NULL
        GROUP BY p.category
    ");
    $stmt->execute();
    echo "Category metrics calculated.<br>\n";
    
    // Create sample cohort data
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO user_cohorts (user_id, cohort_month, months_since_signup, orders_count, revenue)
        SELECT 
            u.id,
            DATE_FORMAT(u.created_at, '%Y-%m-01') as cohort_month,
            PERIOD_DIFF(DATE_FORMAT(NOW(), '%Y%m'), DATE_FORMAT(u.created_at, '%Y%m')) as months_since_signup,
            COUNT(o.id) as orders_count,
            COALESCE(SUM(o.total_amount), 0) as revenue
        FROM users u
        LEFT JOIN orders o ON u.id = o.customer_id
        WHERE u.role = 'customer'
        GROUP BY u.id
    ");
    $stmt->execute();
    echo "User cohort data created.<br>\n";
    
    // Update conversion rates for products
    $stmt = $pdo->prepare("
        UPDATE products p 
        SET conversion_rate = (
            SELECT ROUND((COUNT(DISTINCT oi.order_id) / NULLIF(p.views_count, 0)) * 100, 4)
            FROM order_items oi 
            WHERE oi.product_id = p.id
        )
        WHERE p.views_count > 0
    ");
    $stmt->execute();
    echo "Product conversion rates updated.<br>\n";
    
    echo "<h3>Analytics System Setup Complete!</h3>\n";
    echo "<p>✅ Database schema created</p>\n";
    echo "<p>✅ Sample data populated</p>\n";
    echo "<p>✅ Metrics calculated</p>\n";
    echo "<p>✅ Analytics system ready</p>\n";
    
    echo "<hr>\n";
    echo "<h3>Next Steps:</h3>\n";
    echo "<ul>\n";
    echo "<li>Visit <a href='merchant/analytics-dashboard.php'>Merchant Analytics Dashboard</a></li>\n";
    echo "<li>Visit <a href='admin/analytics-dashboard.php'>Admin Analytics Dashboard</a></li>\n";
    echo "<li>Set up cron jobs for daily metric calculations</li>\n";
    echo "<li>Configure real-time data refresh intervals</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
    echo "<p>Please check your database configuration and try again.</p>\n";
}
?>