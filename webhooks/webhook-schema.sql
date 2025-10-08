-- Webhook System Database Schema
-- Tables to support webhook processing, logging, and configuration

-- Webhook log table
CREATE TABLE IF NOT EXISTS webhooks_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(100) NOT NULL,
    event_type VARCHAR(100) NULL,
    event_id VARCHAR(255) NULL,
    headers JSON NULL,
    payload JSON NULL,
    processed BOOLEAN DEFAULT FALSE,
    processing_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_webhook_source (source),
    INDEX idx_webhook_processed (processed),
    INDEX idx_webhook_created (created_at)
);

-- Webhook configurations table
CREATE TABLE IF NOT EXISTS webhook_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    source VARCHAR(100) NOT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    secret_key VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    verification_method ENUM('hmac', 'basic_auth', 'oauth', 'none') DEFAULT 'none',
    config_data JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_source (source)
);

-- Webhook subscriptions table
CREATE TABLE IF NOT EXISTS webhook_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    webhook_config_id INT NOT NULL,
    event_types JSON NOT NULL, -- Array of event types to subscribe to
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (webhook_config_id) REFERENCES webhook_configs(id) ON DELETE CASCADE,
    INDEX idx_subscription_user (user_id),
    INDEX idx_subscription_config (webhook_config_id)
);

-- Webhook events table for tracking and replaying events
CREATE TABLE IF NOT EXISTS webhook_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_config_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON NOT NULL,
    delivery_status ENUM('pending', 'delivered', 'failed') DEFAULT 'pending',
    delivery_attempts INT DEFAULT 0,
    last_delivery_attempt TIMESTAMP NULL,
    next_delivery_attempt TIMESTAMP NULL,
    failure_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_config_id) REFERENCES webhook_configs(id) ON DELETE CASCADE,
    INDEX idx_event_config (webhook_config_id),
    INDEX idx_event_status (delivery_status),
    INDEX idx_event_next_attempt (next_delivery_attempt)
);

-- Insert default webhook configurations
INSERT IGNORE INTO webhook_configs (source, webhook_url, secret_key, is_active, verification_method) VALUES
('shopify', '/webhooks/general-webhook.php?source=shopify', '', TRUE, 'hmac'),
('woocommerce', '/webhooks/general-webhook.php?source=woocommerce', '', TRUE, 'hmac'),
('amazon', '/webhooks/general-webhook.php?source=amazon', '', TRUE, 'none'),
('facebook', '/webhooks/general-webhook.php?source=facebook', '', TRUE, 'none'),
('google', '/webhooks/general-webhook.php?source=google', '', TRUE, 'none'),
('mailchimp', '/webhooks/general-webhook.php?source=mailchimp', '', TRUE, 'none'),
('shipstation', '/webhooks/general-webhook.php?source=shipstation', '', TRUE, 'none');

-- Add webhook-related columns to notifications table if they don't exist
ALTER TABLE notifications 
ADD COLUMN IF NOT EXISTS webhook_event_id INT NULL AFTER data,
ADD COLUMN IF NOT EXISTS webhook_source VARCHAR(100) NULL AFTER webhook_event_id,
ADD INDEX IF NOT EXISTS idx_notifications_webhook (webhook_event_id),
ADD INDEX IF NOT EXISTS idx_notifications_webhook_source (webhook_source);