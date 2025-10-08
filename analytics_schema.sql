-- Advanced Analytics Database Schema
-- Supporting tables for comprehensive business intelligence and reporting

-- Performance monitoring table
CREATE TABLE IF NOT EXISTS performance_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    execution_time FLOAT NOT NULL, -- in milliseconds
    memory_usage INT NOT NULL, -- in bytes
    query_count INT DEFAULT 0,
    user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_performance_endpoint (endpoint),
    INDEX idx_performance_time (execution_time),
    INDEX idx_performance_date (created_at)
);

-- Analytics cache table for pre-calculated metrics
CREATE TABLE IF NOT EXISTS analytics_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cache_key VARCHAR(255) NOT NULL UNIQUE,
    metric_type ENUM('merchant', 'platform', 'product', 'category') NOT NULL,
    entity_id INT NULL, -- merchant_id, product_id, etc.
    period_type ENUM('daily', 'weekly', 'monthly', 'yearly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    data JSON NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_analytics_cache_key (cache_key),
    INDEX idx_analytics_cache_type (metric_type, entity_id),
    INDEX idx_analytics_cache_period (period_start, period_end),
    INDEX idx_analytics_cache_expires (expires_at)
);

-- Product view tracking
CREATE TABLE IF NOT EXISTS product_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NULL,
    session_id VARCHAR(128) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    referrer_url TEXT NULL,
    view_duration INT NULL, -- seconds spent viewing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_product_views_product (product_id),
    INDEX idx_product_views_user (user_id),
    INDEX idx_product_views_session (session_id),
    INDEX idx_product_views_date (created_at)
);

-- Search analytics
CREATE TABLE IF NOT EXISTS search_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    search_query VARCHAR(500) NOT NULL,
    user_id INT NULL,
    session_id VARCHAR(128) NULL,
    results_count INT DEFAULT 0,
    clicked_product_id INT NULL,
    click_position INT NULL, -- position in search results
    filters_used JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (clicked_product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_search_query (search_query),
    INDEX idx_search_user (user_id),
    INDEX idx_search_session (session_id),
    INDEX idx_search_date (created_at)
);

-- User session tracking
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    user_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    pages_visited INT DEFAULT 1,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    session_duration INT NULL, -- in seconds
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_active (is_active),
    INDEX idx_sessions_activity (last_activity)
);

-- Merchant performance metrics (daily aggregation)
CREATE TABLE IF NOT EXISTS merchant_daily_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    date DATE NOT NULL,
    orders_count INT DEFAULT 0,
    orders_value DECIMAL(12,2) DEFAULT 0,
    new_customers_count INT DEFAULT 0,
    product_views INT DEFAULT 0,
    conversion_rate DECIMAL(5,4) DEFAULT 0,
    average_order_value DECIMAL(10,2) DEFAULT 0,
    commission_earned DECIMAL(10,2) DEFAULT 0,
    reviews_received INT DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_merchant_date (merchant_id, date),
    INDEX idx_merchant_metrics_date (date),
    INDEX idx_merchant_metrics_merchant (merchant_id)
);

-- Platform daily metrics
CREATE TABLE IF NOT EXISTS platform_daily_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL UNIQUE,
    total_users INT DEFAULT 0,
    new_users INT DEFAULT 0,
    active_users INT DEFAULT 0,
    total_merchants INT DEFAULT 0,
    new_merchants INT DEFAULT 0,
    total_products INT DEFAULT 0,
    new_products INT DEFAULT 0,
    total_orders INT DEFAULT 0,
    orders_value DECIMAL(15,2) DEFAULT 0,
    commission_collected DECIMAL(12,2) DEFAULT 0,
    platform_fees_collected DECIMAL(12,2) DEFAULT 0,
    total_page_views INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    bounce_rate DECIMAL(5,4) DEFAULT 0,
    avg_session_duration INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_platform_metrics_date (date)
);

-- Category performance tracking
CREATE TABLE IF NOT EXISTS category_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL,
    date DATE NOT NULL,
    product_count INT DEFAULT 0,
    orders_count INT DEFAULT 0,
    revenue DECIMAL(12,2) DEFAULT 0,
    views_count INT DEFAULT 0,
    conversion_rate DECIMAL(5,4) DEFAULT 0,
    average_price DECIMAL(10,2) DEFAULT 0,
    average_rating DECIMAL(3,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_category_date (category, date),
    INDEX idx_category_metrics_category (category),
    INDEX idx_category_metrics_date (date)
);

-- Revenue attribution tracking
CREATE TABLE IF NOT EXISTS revenue_attribution (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    attribution_source ENUM('direct', 'search', 'referral', 'social', 'email', 'paid', 'organic') NOT NULL,
    source_detail VARCHAR(255) NULL, -- specific campaign, referrer, etc.
    first_touch_channel VARCHAR(100) NULL,
    last_touch_channel VARCHAR(100) NULL,
    touchpoints JSON NULL, -- array of all touchpoints in customer journey
    customer_lifetime_value DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_attribution_order (order_id),
    INDEX idx_attribution_source (attribution_source),
    INDEX idx_attribution_date (created_at)
);

-- A/B testing framework
CREATE TABLE IF NOT EXISTS ab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('draft', 'active', 'paused', 'completed') DEFAULT 'draft',
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    traffic_allocation DECIMAL(5,2) DEFAULT 50.00, -- percentage
    success_metric VARCHAR(100) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_ab_tests_status (status),
    INDEX idx_ab_tests_dates (start_date, end_date)
);

-- A/B test variants
CREATE TABLE IF NOT EXISTS ab_test_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    variant_name VARCHAR(100) NOT NULL,
    traffic_percentage DECIMAL(5,2) NOT NULL,
    is_control BOOLEAN DEFAULT FALSE,
    configuration JSON NULL,
    conversions INT DEFAULT 0,
    participants INT DEFAULT 0,
    conversion_rate DECIMAL(5,4) DEFAULT 0,
    FOREIGN KEY (test_id) REFERENCES ab_tests(id) ON DELETE CASCADE,
    INDEX idx_ab_variants_test (test_id)
);

-- User participation in A/B tests
CREATE TABLE IF NOT EXISTS ab_test_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_id INT NOT NULL,
    variant_id INT NOT NULL,
    user_id INT NULL,
    session_id VARCHAR(128) NULL,
    converted BOOLEAN DEFAULT FALSE,
    conversion_value DECIMAL(10,2) NULL,
    participated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    converted_at TIMESTAMP NULL,
    FOREIGN KEY (test_id) REFERENCES ab_tests(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES ab_test_variants(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_ab_participants_test (test_id),
    INDEX idx_ab_participants_user (user_id),
    INDEX idx_ab_participants_session (session_id)
);

-- Cohort analysis data
CREATE TABLE IF NOT EXISTS user_cohorts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cohort_month DATE NOT NULL, -- first month user signed up
    months_since_signup INT NOT NULL,
    orders_count INT DEFAULT 0,
    revenue DECIMAL(10,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    last_order_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_cohort_month (user_id, months_since_signup),
    INDEX idx_cohorts_month (cohort_month),
    INDEX idx_cohorts_months_since (months_since_signup)
);

-- Add missing columns to existing tables for analytics
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS views_count INT DEFAULT 0 AFTER description,
ADD COLUMN IF NOT EXISTS conversion_rate DECIMAL(5,4) DEFAULT 0 AFTER views_count,
ADD COLUMN IF NOT EXISTS last_viewed TIMESTAMP NULL AFTER conversion_rate,
ADD INDEX IF NOT EXISTS idx_products_views (views_count),
ADD INDEX IF NOT EXISTS idx_products_conversion (conversion_rate);

-- Add analytics columns to orders table
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS referrer_source VARCHAR(100) NULL AFTER updated_at,
ADD COLUMN IF NOT EXISTS utm_campaign VARCHAR(100) NULL AFTER referrer_source,
ADD COLUMN IF NOT EXISTS utm_medium VARCHAR(100) NULL AFTER utm_campaign,
ADD COLUMN IF NOT EXISTS utm_source VARCHAR(100) NULL AFTER utm_medium,
ADD INDEX IF NOT EXISTS idx_orders_referrer (referrer_source),
ADD INDEX IF NOT EXISTS idx_orders_utm (utm_source, utm_medium, utm_campaign);

-- Add session tracking to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL AFTER updated_at,
ADD COLUMN IF NOT EXISTS login_count INT DEFAULT 0 AFTER last_login_at,
ADD COLUMN IF NOT EXISTS last_active_at TIMESTAMP NULL AFTER login_count,
ADD INDEX IF NOT EXISTS idx_users_last_login (last_login_at),
ADD INDEX IF NOT EXISTS idx_users_last_active (last_active_at);

-- Create stored procedures for common analytics queries
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS CalculateMerchantMetrics(IN merchant_id INT, IN target_date DATE)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION ROLLBACK;
    
    START TRANSACTION;
    
    INSERT INTO merchant_daily_metrics (
        merchant_id, date, orders_count, orders_value, new_customers_count,
        product_views, conversion_rate, average_order_value, commission_earned,
        reviews_received, average_rating
    ) VALUES (
        merchant_id,
        target_date,
        (SELECT COUNT(*) FROM orders WHERE merchant_id = merchant_id AND DATE(created_at) = target_date),
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE merchant_id = merchant_id AND DATE(created_at) = target_date),
        (SELECT COUNT(DISTINCT customer_id) FROM orders WHERE merchant_id = merchant_id AND DATE(created_at) = target_date),
        (SELECT COALESCE(SUM(views_count), 0) FROM products WHERE merchant_id = merchant_id),
        0, -- Will be calculated separately
        (SELECT COALESCE(AVG(total_amount), 0) FROM orders WHERE merchant_id = merchant_id AND DATE(created_at) = target_date),
        (SELECT COALESCE(SUM(commission_amount), 0) FROM merchant_commissions WHERE merchant_id = merchant_id AND DATE(created_at) = target_date),
        (SELECT COUNT(*) FROM product_reviews pr JOIN products p ON pr.product_id = p.id WHERE p.merchant_id = merchant_id AND DATE(pr.created_at) = target_date),
        (SELECT COALESCE(AVG(rating), 0) FROM product_reviews pr JOIN products p ON pr.product_id = p.id WHERE p.merchant_id = merchant_id AND DATE(pr.created_at) = target_date)
    ) ON DUPLICATE KEY UPDATE
        orders_count = VALUES(orders_count),
        orders_value = VALUES(orders_value),
        new_customers_count = VALUES(new_customers_count),
        product_views = VALUES(product_views),
        average_order_value = VALUES(average_order_value),
        commission_earned = VALUES(commission_earned),
        reviews_received = VALUES(reviews_received),
        average_rating = VALUES(average_rating),
        updated_at = CURRENT_TIMESTAMP;
    
    COMMIT;
END //

CREATE PROCEDURE IF NOT EXISTS CalculatePlatformMetrics(IN target_date DATE)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION ROLLBACK;
    
    START TRANSACTION;
    
    INSERT INTO platform_daily_metrics (
        date, total_users, new_users, total_merchants, new_merchants,
        total_products, new_products, total_orders, orders_value,
        commission_collected, platform_fees_collected
    ) VALUES (
        target_date,
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) <= target_date),
        (SELECT COUNT(*) FROM users WHERE DATE(created_at) = target_date),
        (SELECT COUNT(*) FROM users WHERE role = 'merchant' AND DATE(created_at) <= target_date),
        (SELECT COUNT(*) FROM users WHERE role = 'merchant' AND DATE(created_at) = target_date),
        (SELECT COUNT(*) FROM products WHERE DATE(created_at) <= target_date),
        (SELECT COUNT(*) FROM products WHERE DATE(created_at) = target_date),
        (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = target_date),
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = target_date),
        (SELECT COALESCE(SUM(commission_amount), 0) FROM merchant_commissions WHERE DATE(created_at) = target_date),
        (SELECT COALESCE(SUM(platform_fee), 0) FROM payment_transactions WHERE DATE(created_at) = target_date)
    ) ON DUPLICATE KEY UPDATE
        total_users = VALUES(total_users),
        new_users = VALUES(new_users),
        total_merchants = VALUES(total_merchants),
        new_merchants = VALUES(new_merchants),
        total_products = VALUES(total_products),
        new_products = VALUES(new_products),
        total_orders = VALUES(total_orders),
        orders_value = VALUES(orders_value),
        commission_collected = VALUES(commission_collected),
        platform_fees_collected = VALUES(platform_fees_collected),
        updated_at = CURRENT_TIMESTAMP;
    
    COMMIT;
END //

DELIMITER ;

-- Create analytics dashboard views for common queries
CREATE VIEW IF NOT EXISTS merchant_summary_view AS
SELECT 
    u.id as merchant_id,
    u.email,
    CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as name,
    COUNT(DISTINCT p.id) as total_products,
    COUNT(DISTINCT o.id) as total_orders,
    COALESCE(SUM(o.total_amount), 0) as total_revenue,
    COALESCE(AVG(pr.rating), 0) as average_rating,
    COUNT(DISTINCT pr.id) as total_reviews,
    u.created_at as merchant_since
FROM users u
LEFT JOIN user_profiles up ON u.id = up.user_id
LEFT JOIN products p ON u.id = p.merchant_id
LEFT JOIN orders o ON u.id = o.merchant_id
LEFT JOIN product_reviews pr ON p.id = pr.product_id
WHERE u.role = 'merchant'
GROUP BY u.id;

CREATE VIEW IF NOT EXISTS product_performance_view AS
SELECT 
    p.id,
    p.name,
    p.category,
    p.price,
    p.views_count,
    COUNT(DISTINCT oi.order_id) as orders_count,
    SUM(oi.quantity) as units_sold,
    SUM(oi.quantity * oi.price) as revenue,
    AVG(pr.rating) as average_rating,
    COUNT(DISTINCT pr.id) as review_count,
    ROUND((COUNT(DISTINCT oi.order_id) / NULLIF(p.views_count, 0)) * 100, 4) as conversion_rate
FROM products p
LEFT JOIN order_items oi ON p.id = oi.product_id
LEFT JOIN product_reviews pr ON p.id = pr.product_id
GROUP BY p.id;

-- Insert default analytics notification types
INSERT IGNORE INTO notification_types (name, description, is_email, is_sms, is_push) VALUES
('daily_analytics_report', 'Daily analytics summary for merchants', TRUE, FALSE, FALSE),
('weekly_analytics_report', 'Weekly performance report', TRUE, FALSE, FALSE),
('monthly_analytics_report', 'Monthly business insights', TRUE, FALSE, FALSE),
('performance_alert', 'Performance threshold alerts', TRUE, FALSE, TRUE),
('revenue_milestone', 'Revenue milestone achievements', TRUE, FALSE, TRUE),
('conversion_alert', 'Conversion rate changes', FALSE, FALSE, TRUE);

-- Create indexes for optimal analytics performance
CREATE INDEX IF NOT EXISTS idx_orders_merchant_status_date ON orders (merchant_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_orders_customer_date ON orders (customer_id, created_at);
CREATE INDEX IF NOT EXISTS idx_order_items_product_date ON order_items (product_id, created_at);
CREATE INDEX IF NOT EXISTS idx_product_reviews_product_rating ON product_reviews (product_id, rating, created_at);
CREATE INDEX IF NOT EXISTS idx_commission_merchant_date ON merchant_commissions (merchant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_payment_transactions_date_status ON payment_transactions (created_at, status);