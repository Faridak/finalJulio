-- Advanced Security System Database Schema

-- User roles table
CREATE TABLE IF NOT EXISTS user_roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role_name (role_name),
    INDEX idx_active (is_active)
);

-- Permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_permission_name (permission_name),
    INDEX idx_category (category)
);

-- Role-permission mapping table
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    INDEX idx_role (role_id),
    INDEX idx_permission (permission_id)
);

-- API tokens table
CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at)
);

-- Rate limiting table
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL, -- IP address, user ID, or API key
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_created (created_at)
);

-- Security logs table
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    event_type VARCHAR(100) NOT NULL,
    details JSON,
    severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_severity (severity),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created (created_at)
);

-- Blocked IPs table
CREATE TABLE IF NOT EXISTS blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) UNIQUE NOT NULL,
    blocked_until DATETIME NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip_address (ip_address),
    INDEX idx_blocked_until (blocked_until)
);

-- Insert default roles
INSERT IGNORE INTO user_roles (role_name, description) VALUES
('super_admin', 'Full system access with all permissions'),
('admin', 'Administrative access to most system functions'),
('manager', 'Manager access to business operations'),
('sales', 'Sales team access to commission and customer data'),
('customer', 'Customer access to account and order information'),
('guest', 'Limited guest access');

-- Insert default permissions
INSERT IGNORE INTO permissions (permission_name, description, category) VALUES
('view_dashboard', 'View system dashboard', 'system'),
('manage_users', 'Create, edit, and delete users', 'system'),
('manage_roles', 'Manage user roles and permissions', 'system'),
('view_reports', 'View business reports and analytics', 'analytics'),
('manage_products', 'Create, edit, and delete products', 'catalog'),
('manage_orders', 'View and manage orders', 'sales'),
('manage_inventory', 'Manage inventory levels and locations', 'inventory'),
('view_financials', 'View financial reports and data', 'finance'),
('manage_commissions', 'Manage sales commissions and tiers', 'finance'),
('manage_marketing', 'Manage marketing campaigns and expenses', 'marketing'),
('view_customers', 'View customer information', 'customers'),
('manage_customers', 'Manage customer accounts', 'customers'),
('process_payments', 'Process payments and refunds', 'finance'),
('manage_shipments', 'Manage shipping and fulfillment', 'operations'),
('view_audit_logs', 'View security and audit logs', 'security'),
('manage_api_tokens', 'Create and revoke API tokens', 'security');

-- Assign permissions to roles
-- Super Admin (all permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT ur.id, p.id
FROM user_roles ur
CROSS JOIN permissions p
WHERE ur.role_name = 'super_admin';

-- Admin (most permissions except security-sensitive ones)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT ur.id, p.id
FROM user_roles ur
JOIN permissions p ON p.permission_name NOT IN ('manage_roles', 'view_audit_logs')
WHERE ur.role_name = 'admin';

-- Manager (business operations)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT ur.id, p.id
FROM user_roles ur
JOIN permissions p ON p.permission_name IN ('view_dashboard', 'view_reports', 'manage_products', 'manage_orders', 'manage_inventory', 'view_financials', 'manage_commissions', 'manage_marketing', 'view_customers', 'manage_customers', 'manage_shipments')
WHERE ur.role_name = 'manager';

-- Sales (sales-specific permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT ur.id, p.id
FROM user_roles ur
JOIN permissions p ON p.permission_name IN ('view_dashboard', 'manage_orders', 'view_customers', 'manage_customers')
WHERE ur.role_name = 'sales';

-- Customer (customer-specific permissions)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT ur.id, p.id
FROM user_roles ur
JOIN permissions p ON p.permission_name IN ('view_dashboard', 'manage_orders', 'view_customers')
WHERE ur.role_name = 'customer';

-- Update users table to use role names instead of role IDs
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'customer' AFTER email,
ADD INDEX IF NOT EXISTS idx_user_role (role);