-- Payment Gateway Database Schema
-- Tables to support payment processing, transactions, refunds, and commission management

-- Payment transactions table
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_method ENUM('stripe', 'paypal', 'bank_transfer', 'wallet') NOT NULL,
    gateway_reference VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    platform_fee DECIMAL(10,2) DEFAULT 0,
    gateway_fee DECIMAL(10,2) DEFAULT 0,
    net_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'refunded') DEFAULT 'pending',
    failure_reason TEXT NULL,
    raw_response JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    INDEX idx_payment_order (order_id),
    INDEX idx_payment_status (status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_gateway_reference (gateway_reference)
);

-- Payment refunds table
CREATE TABLE IF NOT EXISTS payment_refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    gateway_refund_id VARCHAR(255) NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    processed_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES payment_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_refund_transaction (transaction_id),
    INDEX idx_refund_status (status)
);

-- Merchant commission tracking
CREATE TABLE IF NOT EXISTS merchant_commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    merchant_id INT NOT NULL,
    gross_amount DECIMAL(10,2) NOT NULL,
    commission_rate DECIMAL(5,4) NOT NULL DEFAULT 0.0500, -- 5% default
    commission_amount DECIMAL(10,2) NOT NULL,
    net_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending_payout', 'paid_out', 'on_hold', 'cancelled') DEFAULT 'pending_payout',
    payout_date TIMESTAMP NULL,
    payout_reference VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_commission_merchant (merchant_id),
    INDEX idx_commission_status (status),
    INDEX idx_commission_order (order_id)
);

-- Merchant payout methods (bank accounts, PayPal, etc.)
CREATE TABLE IF NOT EXISTS merchant_payout_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    method_type ENUM('bank_account', 'paypal', 'stripe_express') NOT NULL,
    account_details JSON NOT NULL, -- Store encrypted account details
    is_verified BOOLEAN DEFAULT FALSE,
    is_primary BOOLEAN DEFAULT FALSE,
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    verification_documents JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_payout_merchant (merchant_id),
    INDEX idx_payout_verified (is_verified)
);

-- Platform wallet system for users
CREATE TABLE IF NOT EXISTS user_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10,2) DEFAULT 0.00,
    pending_balance DECIMAL(10,2) DEFAULT 0.00, -- Funds on hold
    currency VARCHAR(3) DEFAULT 'USD',
    status ENUM('active', 'frozen', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_wallet (user_id, currency),
    INDEX idx_wallet_user (user_id)
);

-- Wallet transactions (deposits, withdrawals, purchases)
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    transaction_type ENUM('deposit', 'withdrawal', 'purchase', 'refund', 'commission') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    reference_type ENUM('order', 'payment', 'payout', 'manual') NULL,
    reference_id INT NULL,
    description VARCHAR(500) NOT NULL,
    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES user_wallets(id) ON DELETE CASCADE,
    INDEX idx_wallet_trans_wallet (wallet_id),
    INDEX idx_wallet_trans_type (transaction_type),
    INDEX idx_wallet_trans_reference (reference_type, reference_id)
);

-- Payment method configurations
CREATE TABLE IF NOT EXISTS payment_method_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_name VARCHAR(50) NOT NULL,
    is_enabled BOOLEAN DEFAULT TRUE,
    configuration JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_payment_method (method_name)
);

-- Saved payment methods for customers
CREATE TABLE IF NOT EXISTS customer_payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    payment_method ENUM('stripe', 'paypal') NOT NULL,
    gateway_payment_method_id VARCHAR(255) NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    card_last4 VARCHAR(4) NULL,
    card_brand VARCHAR(20) NULL,
    expires_at DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_customer_payment_methods (customer_id),
    INDEX idx_payment_method_gateway (gateway_payment_method_id)
);

-- Dispute/chargeback tracking
CREATE TABLE IF NOT EXISTS payment_disputes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    dispute_type ENUM('chargeback', 'inquiry', 'retrieval_request') NOT NULL,
    gateway_dispute_id VARCHAR(255) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    reason VARCHAR(255) NOT NULL,
    status ENUM('warning_needs_response', 'warning_under_review', 'warning_closed', 'needs_response', 'under_review', 'charge_refunded', 'won', 'lost') NOT NULL,
    evidence_due_by TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES payment_transactions(id) ON DELETE CASCADE,
    INDEX idx_dispute_transaction (transaction_id),
    INDEX idx_dispute_status (status),
    INDEX idx_dispute_due_date (evidence_due_by)
);

-- Payment gateway webhooks log
CREATE TABLE IF NOT EXISTS payment_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gateway VARCHAR(50) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_id VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    processed BOOLEAN DEFAULT FALSE,
    processing_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    INDEX idx_webhook_gateway (gateway),
    INDEX idx_webhook_processed (processed),
    INDEX idx_webhook_event (event_id)
);

-- Insert default payment method configurations
INSERT INTO payment_method_configs (method_name, is_enabled, configuration) VALUES
('stripe', TRUE, JSON_OBJECT(
    'publishable_key', 'pk_test_...',
    'webhook_secret', 'whsec_...',
    'commission_rate', 0.029,
    'fixed_fee', 0.30
)),
('paypal', TRUE, JSON_OBJECT(
    'client_id', 'paypal_client_id',
    'mode', 'sandbox',
    'webhook_id', 'webhook_id',
    'commission_rate', 0.034
)) ON DUPLICATE KEY UPDATE 
method_name = VALUES(method_name);

-- Add payment-related columns to orders table if they don't exist
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS payment_method ENUM('stripe', 'paypal', 'bank_transfer', 'wallet') NULL AFTER status,
ADD COLUMN IF NOT EXISTS payment_transaction_id INT NULL AFTER payment_method,
ADD COLUMN IF NOT EXISTS payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending' AFTER payment_transaction_id,
ADD INDEX IF NOT EXISTS idx_orders_payment_status (payment_status),
ADD INDEX IF NOT EXISTS idx_orders_payment_transaction (payment_transaction_id);

-- Add foreign key constraint for payment_transaction_id
ALTER TABLE orders 
ADD CONSTRAINT fk_orders_payment_transaction 
FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions(id) ON DELETE SET NULL;

-- Add merchant payout settings to user_profiles
ALTER TABLE user_profiles 
ADD COLUMN IF NOT EXISTS payout_method ENUM('bank_account', 'paypal', 'stripe_express') NULL AFTER bio,
ADD COLUMN IF NOT EXISTS payout_details JSON NULL AFTER payout_method,
ADD COLUMN IF NOT EXISTS payout_schedule ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly' AFTER payout_details,
ADD COLUMN IF NOT EXISTS minimum_payout DECIMAL(10,2) DEFAULT 25.00 AFTER payout_schedule;