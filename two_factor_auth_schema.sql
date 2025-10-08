-- Two-Factor Authentication Database Schema for VentDepot
-- Created to support comprehensive 2FA implementation

-- =====================================================
-- TWO-FACTOR AUTHENTICATION TABLES
-- =====================================================

-- Enhanced 2FA table with proper structure
CREATE TABLE IF NOT EXISTS user_2fa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNIQUE NOT NULL,
    secret_key VARCHAR(255) NOT NULL,
    is_enabled BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    enabled_at TIMESTAMP NULL,
    last_used TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_2fa_user (user_id),
    INDEX idx_2fa_enabled (is_enabled)
);

-- 2FA Backup Codes
CREATE TABLE IF NOT EXISTS user_2fa_backup_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL, -- Store hashed backup codes
    is_used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_backup_codes_user (user_id),
    INDEX idx_backup_codes_unused (user_id, is_used)
);

-- Trusted Devices for 2FA
CREATE TABLE IF NOT EXISTS user_trusted_devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    device_name VARCHAR(255) NOT NULL,
    device_fingerprint VARCHAR(255) NOT NULL, -- Hash of user agent + IP + user ID
    user_agent TEXT,
    ip_address VARCHAR(45),
    is_active BOOLEAN DEFAULT TRUE,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_trusted_devices_user (user_id),
    INDEX idx_trusted_devices_fingerprint (device_fingerprint),
    INDEX idx_trusted_devices_active (is_active, created_at)
);

-- Security Events Log (for 2FA and general security)
CREATE TABLE IF NOT EXISTS security_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL, -- 2fa_enabled, 2fa_disabled, 2fa_verification, login_failed, etc.
    user_id INT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    event_data JSON, -- Additional event details
    severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_security_events_type (event_type),
    INDEX idx_security_events_user (user_id),
    INDEX idx_security_events_created (created_at),
    INDEX idx_security_events_severity (severity)
);

-- Insert some initial data for testing
INSERT IGNORE INTO product_categories (name, slug, description, sort_order) VALUES
('Electronics', 'electronics', 'Electronic devices and accessories', 1),
('Clothing', 'clothing', 'Fashion and apparel', 2),
('Home & Garden', 'home-garden', 'Home improvement and garden supplies', 3),
('Sports & Outdoors', 'sports-outdoors', 'Sporting goods and outdoor equipment', 4),
('Books & Media', 'books-media', 'Books, movies, music and media', 5);

-- Update existing products table to ensure proper indexes for 2FA integration
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS two_factor_enabled BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS failed_login_attempts INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL;

-- Add indexes for better performance
CREATE INDEX IF NOT EXISTS idx_users_2fa_enabled ON users(two_factor_enabled);
CREATE INDEX IF NOT EXISTS idx_users_last_login ON users(last_login_at);
CREATE INDEX IF NOT EXISTS idx_users_locked ON users(locked_until);

-- Create a view for user security status
CREATE OR REPLACE VIEW user_security_status AS
SELECT 
    u.id,
    u.email,
    u.role,
    u.two_factor_enabled,
    u.last_login_at,
    u.failed_login_attempts,
    u.locked_until,
    u2fa.is_enabled as has_2fa_setup,
    u2fa.enabled_at as tfa_enabled_at,
    COUNT(utd.id) as trusted_devices_count,
    COUNT(CASE WHEN ubc.is_used = 0 THEN 1 END) as remaining_backup_codes
FROM users u
LEFT JOIN user_2fa u2fa ON u.id = u2fa.user_id
LEFT JOIN user_trusted_devices utd ON u.id = utd.user_id AND utd.is_active = 1
LEFT JOIN user_2fa_backup_codes ubc ON u.id = ubc.user_id
GROUP BY u.id, u.email, u.role, u.two_factor_enabled, u.last_login_at, 
         u.failed_login_attempts, u.locked_until, u2fa.is_enabled, u2fa.enabled_at;