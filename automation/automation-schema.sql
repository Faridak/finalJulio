-- Business Automation System Database Schema

-- Financial periods table for month-end closing
CREATE TABLE IF NOT EXISTS financial_periods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    period VARCHAR(7) NOT NULL, -- YYYY-MM format
    total_revenue DECIMAL(15,2) DEFAULT 0,
    total_orders INT DEFAULT 0,
    total_commissions DECIMAL(15,2) DEFAULT 0,
    total_marketing DECIMAL(15,2) DEFAULT 0,
    net_income DECIMAL(15,2) DEFAULT 0,
    closed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_period (period),
    INDEX idx_period (period),
    INDEX idx_closed_at (closed_at)
);

-- Add ROI and revenue columns to marketing_campaigns table
ALTER TABLE marketing_campaigns
ADD COLUMN IF NOT EXISTS roi_percentage DECIMAL(8,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS actual_revenue DECIMAL(15,2) DEFAULT 0.00,
ADD INDEX IF NOT EXISTS idx_roi (roi_percentage);

-- Add automation tracking columns to sales_commissions table
ALTER TABLE sales_commissions
ADD COLUMN IF NOT EXISTS last_tier_update TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS tier_update_count INT DEFAULT 0,
ADD INDEX IF NOT EXISTS idx_last_tier_update (last_tier_update);

-- Add automation tracking columns to product_inventory table
ALTER TABLE product_inventory
ADD COLUMN IF NOT EXISTS last_alert_sent TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS alert_count INT DEFAULT 0,
ADD INDEX IF NOT EXISTS idx_last_alert (last_alert_sent);

-- Automation logs table
CREATE TABLE IF NOT EXISTS automation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    process_name VARCHAR(100) NOT NULL,
    status ENUM('success', 'failed', 'warning') DEFAULT 'success',
    details JSON,
    execution_time DECIMAL(10,4), -- Execution time in seconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_process_name (process_name),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Scheduled tasks table
CREATE TABLE IF NOT EXISTS scheduled_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(100) NOT NULL,
    task_description TEXT,
    cron_expression VARCHAR(50) NOT NULL,
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_task_name (task_name),
    INDEX idx_next_run (next_run),
    INDEX idx_active (is_active)
);

-- Insert default scheduled tasks
INSERT IGNORE INTO scheduled_tasks (task_name, task_description, cron_expression, next_run, is_active) VALUES
('commission_tier_progression', 'Automatically progress sales commissions to next tier', '0 2 * * *', DATE_ADD(NOW(), INTERVAL 1 DAY), TRUE),
('inventory_alerts', 'Check inventory levels and send alerts for low stock', '*/30 * * * *', DATE_ADD(NOW(), INTERVAL 30 MINUTE), TRUE),
('marketing_roi_calculation', 'Calculate and update marketing ROI in real-time', '0 3 * * *', DATE_ADD(NOW(), INTERVAL 1 DAY), TRUE),
('financial_period_closing', 'Automatically close financial periods (month-end processing)', '0 1 1 * *', DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 MONTH), '%Y-%m-01 01:00:00'), TRUE),
('cleanup_old_records', 'Clean up old automation records', '0 4 1 * *', DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 1 MONTH), '%Y-%m-01 04:00:00'), TRUE);

-- Add indexes to existing tables for better automation performance
ALTER TABLE orders 
ADD INDEX IF NOT EXISTS idx_created_month (DATE_FORMAT(created_at, '%Y-%m'));

ALTER TABLE merchant_commissions
ADD INDEX IF NOT EXISTS idx_created_month (DATE_FORMAT(created_at, '%Y-%m'));

ALTER TABLE marketing_expenses
ADD INDEX IF NOT EXISTS idx_expense_month (DATE_FORMAT(expense_date, '%Y-%m'));