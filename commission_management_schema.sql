-- Commission Management System Database Schema
-- Advanced fee structures, tiered commissions, and merchant performance tracking

-- Commission tiers definition
CREATE TABLE IF NOT EXISTS commission_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_name VARCHAR(50) NOT NULL UNIQUE,
    tier_level INT NOT NULL UNIQUE,
    commission_rate DECIMAL(5,4) NOT NULL DEFAULT 0.0500, -- 5% default
    min_volume DECIMAL(12,2) NOT NULL DEFAULT 0,
    min_orders INT NOT NULL DEFAULT 0,
    description TEXT NULL,
    benefits JSON NULL, -- Store tier benefits as JSON
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_commission_tiers_level (tier_level),
    INDEX idx_commission_tiers_active (is_active)
);

-- Category-specific commission rates
CREATE TABLE IF NOT EXISTS category_commission_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    commission_rate DECIMAL(5,4) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tier_id) REFERENCES commission_tiers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tier_category (tier_id, category),
    INDEX idx_category_rates_category (category),
    INDEX idx_category_rates_active (is_active)
);

-- Merchant tier history (tracks tier changes over time)
CREATE TABLE IF NOT EXISTS merchant_tier_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    tier_id INT NOT NULL,
    started_at TIMESTAMP NOT NULL,
    ended_at TIMESTAMP NULL,
    is_current BOOLEAN DEFAULT TRUE,
    upgrade_reason ENUM('volume', 'orders', 'performance', 'manual', 'promotion') DEFAULT 'volume',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tier_id) REFERENCES commission_tiers(id) ON DELETE CASCADE,
    INDEX idx_merchant_tier_history_merchant (merchant_id),
    INDEX idx_merchant_tier_history_current (merchant_id, is_current),
    INDEX idx_merchant_tier_history_dates (started_at, ended_at)
);

-- Enhanced merchant_commissions table (updating existing)
ALTER TABLE merchant_commissions 
ADD COLUMN IF NOT EXISTS platform_fee DECIMAL(10,2) DEFAULT 0 AFTER commission_amount,
ADD COLUMN IF NOT EXISTS payment_processing_fee DECIMAL(10,2) DEFAULT 0 AFTER platform_fee,
ADD COLUMN IF NOT EXISTS tier VARCHAR(50) NULL AFTER net_amount,
ADD COLUMN IF NOT EXISTS volume_discount DECIMAL(5,4) DEFAULT 0 AFTER tier,
ADD COLUMN IF NOT EXISTS performance_adjustment DECIMAL(5,4) DEFAULT 0 AFTER volume_discount,
ADD COLUMN IF NOT EXISTS calculation_details JSON NULL AFTER performance_adjustment,
ADD INDEX IF NOT EXISTS idx_merchant_commissions_tier (tier),
ADD INDEX IF NOT EXISTS idx_merchant_commissions_date (created_at);

-- Merchant statistics (aggregated data for performance calculations)
CREATE TABLE IF NOT EXISTS merchant_statistics (
    merchant_id INT PRIMARY KEY,
    total_sales DECIMAL(15,2) DEFAULT 0,
    order_count INT DEFAULT 0,
    avg_order_value DECIMAL(10,2) DEFAULT 0,
    last_sale_date TIMESTAMP NULL,
    first_sale_date TIMESTAMP NULL,
    total_commission_paid DECIMAL(12,2) DEFAULT 0,
    current_tier_id INT NULL,
    performance_score DECIMAL(5,2) DEFAULT 0,
    volume_last_30_days DECIMAL(12,2) DEFAULT 0,
    orders_last_30_days INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (current_tier_id) REFERENCES commission_tiers(id) ON DELETE SET NULL,
    INDEX idx_merchant_stats_sales (total_sales),
    INDEX idx_merchant_stats_orders (order_count),
    INDEX idx_merchant_stats_performance (performance_score),
    INDEX idx_merchant_stats_volume_30d (volume_last_30_days)
);

-- Commission adjustments (manual adjustments by admin)
CREATE TABLE IF NOT EXISTS commission_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    order_id INT NULL,
    adjustment_type ENUM('bonus', 'penalty', 'refund', 'correction', 'promotion') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    percentage DECIMAL(5,4) NULL,
    reason VARCHAR(500) NOT NULL,
    applied_by INT NOT NULL,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    effective_date TIMESTAMP NOT NULL,
    status ENUM('pending', 'applied', 'cancelled') DEFAULT 'pending',
    metadata JSON NULL,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (applied_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_commission_adjustments_merchant (merchant_id),
    INDEX idx_commission_adjustments_type (adjustment_type),
    INDEX idx_commission_adjustments_status (status),
    INDEX idx_commission_adjustments_date (effective_date)
);

-- Commission rules engine (dynamic rules for special conditions)
CREATE TABLE IF NOT EXISTS commission_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    rule_type ENUM('volume_discount', 'performance_bonus', 'category_special', 'time_limited', 'custom') NOT NULL,
    conditions JSON NOT NULL, -- Store rule conditions
    actions JSON NOT NULL, -- Store rule actions (rate adjustments, etc.)
    priority INT DEFAULT 100,
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_commission_rules_type (rule_type),
    INDEX idx_commission_rules_active (is_active),
    INDEX idx_commission_rules_dates (start_date, end_date),
    INDEX idx_commission_rules_priority (priority)
);

-- Commission payouts (enhanced payout tracking)
CREATE TABLE IF NOT EXISTS commission_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    payout_period_start DATE NOT NULL,
    payout_period_end DATE NOT NULL,
    total_orders INT NOT NULL,
    gross_amount DECIMAL(12,2) NOT NULL,
    total_commission DECIMAL(12,2) NOT NULL,
    total_platform_fees DECIMAL(12,2) NOT NULL,
    total_processing_fees DECIMAL(12,2) NOT NULL,
    adjustments_amount DECIMAL(12,2) DEFAULT 0,
    net_payout_amount DECIMAL(12,2) NOT NULL,
    payout_method ENUM('bank_transfer', 'paypal', 'stripe', 'check', 'wallet') NOT NULL,
    payout_reference VARCHAR(255) NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    failure_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_commission_payouts_merchant (merchant_id),
    INDEX idx_commission_payouts_period (payout_period_start, payout_period_end),
    INDEX idx_commission_payouts_status (status),
    INDEX idx_commission_payouts_date (created_at)
);

-- Fee structure templates (for different marketplace models)
CREATE TABLE IF NOT EXISTS fee_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    structure_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    base_commission_rate DECIMAL(5,4) NOT NULL,
    platform_fee_rate DECIMAL(5,4) DEFAULT 0,
    payment_processing_rate DECIMAL(5,4) DEFAULT 0.029,
    minimum_fee DECIMAL(8,2) DEFAULT 0,
    maximum_fee DECIMAL(8,2) NULL,
    applicable_categories JSON NULL,
    special_conditions JSON NULL,
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fee_structures_active (is_active),
    INDEX idx_fee_structures_default (is_default)
);

-- Commission analytics (daily aggregations for reporting)
CREATE TABLE IF NOT EXISTS commission_daily_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    total_orders INT DEFAULT 0,
    total_merchants INT DEFAULT 0,
    gross_volume DECIMAL(15,2) DEFAULT 0,
    total_commission DECIMAL(12,2) DEFAULT 0,
    total_platform_fees DECIMAL(12,2) DEFAULT 0,
    total_processing_fees DECIMAL(12,2) DEFAULT 0,
    avg_commission_rate DECIMAL(5,4) DEFAULT 0,
    avg_order_value DECIMAL(10,2) DEFAULT 0,
    by_tier JSON NULL, -- Stats broken down by tier
    by_category JSON NULL, -- Stats broken down by category
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_date (date),
    INDEX idx_commission_analytics_date (date)
);

-- Insert default commission tiers
INSERT INTO commission_tiers (tier_name, tier_level, commission_rate, min_volume, min_orders, description, benefits) VALUES
('Starter', 1, 0.0600, 0, 0, 'New merchants starting their journey', JSON_OBJECT(
    'support', 'Basic email support',
    'features', JSON_ARRAY('Basic analytics', 'Standard processing')
)),
('Bronze', 2, 0.0550, 1000, 10, 'Established merchants with consistent sales', JSON_OBJECT(
    'support', 'Priority email support',
    'features', JSON_ARRAY('Advanced analytics', 'Faster payouts', 'Marketing tools')
)),
('Silver', 3, 0.0500, 5000, 50, 'Growing merchants with substantial volume', JSON_OBJECT(
    'support', 'Phone and email support',
    'features', JSON_ARRAY('Premium analytics', 'Weekly payouts', 'Featured listings', 'Bulk tools')
)),
('Gold', 4, 0.0450, 25000, 200, 'High-volume merchants driving significant sales', JSON_OBJECT(
    'support', 'Dedicated account manager',
    'features', JSON_ARRAY('Real-time analytics', 'Daily payouts', 'Priority listings', 'Custom integrations')
)),
('Platinum', 5, 0.0400, 100000, 500, 'Top-tier merchants with exceptional performance', JSON_OBJECT(
    'support', 'Premium account management',
    'features', JSON_ARRAY('Custom analytics', 'Instant payouts', 'Premium placement', 'White-label options')
))
ON DUPLICATE KEY UPDATE tier_name = VALUES(tier_name);

-- Insert category-specific rates (examples)
INSERT INTO category_commission_rates (tier_id, category, commission_rate) 
SELECT ct.id, 'Electronics', ct.commission_rate - 0.005 FROM commission_tiers ct
ON DUPLICATE KEY UPDATE commission_rate = VALUES(commission_rate);

INSERT INTO category_commission_rates (tier_id, category, commission_rate) 
SELECT ct.id, 'Books', ct.commission_rate - 0.010 FROM commission_tiers ct
ON DUPLICATE KEY UPDATE commission_rate = VALUES(commission_rate);

INSERT INTO category_commission_rates (tier_id, category, commission_rate) 
SELECT ct.id, 'Luxury Goods', ct.commission_rate + 0.010 FROM commission_tiers ct
ON DUPLICATE KEY UPDATE commission_rate = VALUES(commission_rate);

-- Insert default fee structure
INSERT INTO fee_structures (structure_name, description, base_commission_rate, platform_fee_rate, payment_processing_rate) VALUES
('Standard Marketplace', 'Default commission structure for general marketplace', 0.0500, 0.0100, 0.0290),
('Digital Goods', 'Reduced fees for digital products', 0.0300, 0.0050, 0.0290),
('High Volume', 'Special rates for high-volume merchants', 0.0350, 0.0075, 0.0290)
ON DUPLICATE KEY UPDATE structure_name = VALUES(structure_name);

-- Create indexes for optimal performance
CREATE INDEX IF NOT EXISTS idx_merchant_commissions_merchant_date ON merchant_commissions (merchant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_merchant_commissions_status_date ON merchant_commissions (status, created_at);
CREATE INDEX IF NOT EXISTS idx_commission_tiers_volume ON commission_tiers (min_volume, min_orders);

-- Add triggers to automatically update merchant statistics
DELIMITER //

CREATE TRIGGER IF NOT EXISTS update_merchant_stats_after_commission 
AFTER INSERT ON merchant_commissions
FOR EACH ROW
BEGIN
    INSERT INTO merchant_statistics (
        merchant_id, total_sales, order_count, avg_order_value, 
        last_sale_date, first_sale_date, total_commission_paid
    ) VALUES (
        NEW.merchant_id, NEW.gross_amount, 1, NEW.gross_amount,
        NEW.created_at, NEW.created_at, NEW.commission_amount
    ) ON DUPLICATE KEY UPDATE
        total_sales = total_sales + NEW.gross_amount,
        order_count = order_count + 1,
        avg_order_value = total_sales / order_count,
        last_sale_date = NEW.created_at,
        first_sale_date = COALESCE(first_sale_date, NEW.created_at),
        total_commission_paid = total_commission_paid + NEW.commission_amount;
END//

DELIMITER ;

-- Create view for merchant commission summary
CREATE OR REPLACE VIEW merchant_commission_summary AS
SELECT 
    ms.merchant_id,
    u.email as merchant_email,
    CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as merchant_name,
    ct.tier_name as current_tier,
    ms.total_sales,
    ms.order_count,
    ms.avg_order_value,
    ms.total_commission_paid,
    ms.performance_score,
    ms.volume_last_30_days,
    ms.orders_last_30_days,
    ms.last_sale_date,
    (ms.total_commission_paid / NULLIF(ms.total_sales, 0) * 100) as effective_commission_rate
FROM merchant_statistics ms
JOIN users u ON ms.merchant_id = u.id
LEFT JOIN user_profiles up ON u.id = up.user_id
LEFT JOIN commission_tiers ct ON ms.current_tier_id = ct.id
WHERE u.role = 'merchant';

-- Create view for commission analytics
CREATE OR REPLACE VIEW commission_analytics_view AS
SELECT 
    DATE(mc.created_at) as date,
    COUNT(*) as total_orders,
    COUNT(DISTINCT mc.merchant_id) as active_merchants,
    SUM(mc.gross_amount) as gross_volume,
    SUM(mc.commission_amount) as total_commission,
    SUM(mc.platform_fee) as total_platform_fees,
    SUM(mc.payment_processing_fee) as total_processing_fees,
    AVG(mc.commission_rate) as avg_commission_rate,
    AVG(mc.gross_amount) as avg_order_value
FROM merchant_commissions mc
GROUP BY DATE(mc.created_at)
ORDER BY date DESC;