-- Escrow and Buyer Protection Database Schema
-- Tables to support secure transaction handling and dispute resolution

-- Main escrow transactions table
CREATE TABLE IF NOT EXISTS escrow_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    payment_transaction_id INT NOT NULL,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    escrow_fee DECIMAL(10,2) DEFAULT 0,
    status ENUM('active', 'shipped', 'disputed', 'released', 'refunded', 'partially_refunded') DEFAULT 'active',
    tracking_number VARCHAR(100) NULL,
    carrier VARCHAR(50) NULL,
    release_date TIMESTAMP NOT NULL,
    released_at TIMESTAMP NULL,
    shipped_at TIMESTAMP NULL,
    release_reason ENUM('buyer_confirmed', 'auto_released', 'dispute_resolved_seller', 'dispute_resolved_buyer', 'admin_released') NULL,
    released_by INT NULL,
    auto_release_processed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_transaction_id) REFERENCES payment_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (released_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_escrow_order (order_id),
    INDEX idx_escrow_buyer (buyer_id),
    INDEX idx_escrow_seller (seller_id),
    INDEX idx_escrow_status (status),
    INDEX idx_escrow_release_date (release_date),
    INDEX idx_escrow_auto_release (auto_release_processed, release_date)
);

-- Escrow disputes table
CREATE TABLE IF NOT EXISTS escrow_disputes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escrow_id INT NOT NULL,
    initiated_by INT NOT NULL,
    dispute_reason ENUM('not_received', 'not_as_described', 'damaged', 'counterfeit', 'seller_unresponsive', 'other') NOT NULL,
    description TEXT NOT NULL,
    evidence JSON NULL, -- Store URLs to uploaded evidence files
    status ENUM('open', 'under_review', 'waiting_seller_response', 'waiting_buyer_response', 'escalated', 'resolved', 'closed') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    assigned_to INT NULL, -- Admin user handling the dispute
    resolution TEXT NULL,
    award_to_buyer_percentage DECIMAL(5,2) DEFAULT 0, -- 0-100%
    resolved_at TIMESTAMP NULL,
    resolved_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (escrow_id) REFERENCES escrow_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_dispute_escrow (escrow_id),
    INDEX idx_dispute_status (status),
    INDEX idx_dispute_assigned (assigned_to),
    INDEX idx_dispute_priority (priority)
);

-- Dispute messages/communication
CREATE TABLE IF NOT EXISTS dispute_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dispute_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_type ENUM('buyer', 'seller', 'admin') NOT NULL,
    message_text TEXT NOT NULL,
    attachments JSON NULL,
    is_internal BOOLEAN DEFAULT FALSE, -- Internal admin notes
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id) REFERENCES escrow_disputes(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_dispute_messages_dispute (dispute_id),
    INDEX idx_dispute_messages_sender (sender_id)
);

-- Escrow activity logs
CREATE TABLE IF NOT EXISTS escrow_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escrow_id INT NOT NULL,
    event_type ENUM('created', 'marked_shipped', 'dispute_initiated', 'dispute_resolved', 'released', 'refunded', 'auto_released') NOT NULL,
    user_id INT NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (escrow_id) REFERENCES escrow_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_escrow_logs_escrow (escrow_id),
    INDEX idx_escrow_logs_event (event_type),
    INDEX idx_escrow_logs_date (created_at)
);

-- Merchant ratings from buyers (extends the existing reviews system)
CREATE TABLE IF NOT EXISTS merchant_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    merchant_id INT NOT NULL,
    buyer_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    communication_rating INT NULL CHECK (communication_rating BETWEEN 1 AND 5),
    shipping_speed_rating INT NULL CHECK (shipping_speed_rating BETWEEN 1 AND 5),
    item_description_rating INT NULL CHECK (item_description_rating BETWEEN 1 AND 5),
    feedback_text TEXT NULL,
    is_verified_purchase BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_order_rating (order_id, buyer_id),
    INDEX idx_merchant_ratings_merchant (merchant_id),
    INDEX idx_merchant_ratings_buyer (buyer_id),
    INDEX idx_merchant_ratings_rating (rating)
);

-- Buyer protection policies
CREATE TABLE IF NOT EXISTS protection_policies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    policy_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    coverage_conditions JSON NOT NULL,
    max_coverage_amount DECIMAL(10,2) NULL,
    coverage_percentage DECIMAL(5,2) DEFAULT 100.00,
    escrow_period_days INT DEFAULT 14,
    applicable_categories JSON NULL, -- Product categories this policy applies to
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_protection_policies_active (is_active)
);

-- Protection claims (insurance-style claims)
CREATE TABLE IF NOT EXISTS protection_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escrow_id INT NOT NULL,
    policy_id INT NOT NULL,
    claimant_id INT NOT NULL,
    claim_type ENUM('non_delivery', 'significantly_not_as_described', 'damaged_in_transit', 'unauthorized_transaction') NOT NULL,
    claim_amount DECIMAL(10,2) NOT NULL,
    evidence JSON NOT NULL,
    status ENUM('submitted', 'under_review', 'approved', 'denied', 'paid', 'closed') DEFAULT 'submitted',
    reviewer_id INT NULL,
    review_notes TEXT NULL,
    approved_amount DECIMAL(10,2) NULL,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (escrow_id) REFERENCES escrow_transactions(id) ON DELETE CASCADE,
    FOREIGN KEY (policy_id) REFERENCES protection_policies(id) ON DELETE CASCADE,
    FOREIGN KEY (claimant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_protection_claims_escrow (escrow_id),
    INDEX idx_protection_claims_status (status),
    INDEX idx_protection_claims_claimant (claimant_id)
);

-- Add escrow_id column to orders table if it doesn't exist
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS escrow_id INT NULL AFTER payment_transaction_id,
ADD INDEX IF NOT EXISTS idx_orders_escrow (escrow_id);

-- Add foreign key constraint for escrow_id
ALTER TABLE orders 
ADD CONSTRAINT fk_orders_escrow 
FOREIGN KEY (escrow_id) REFERENCES escrow_transactions(id) ON DELETE SET NULL;

-- Add is_digital column to products if it doesn't exist
ALTER TABLE products 
ADD COLUMN IF NOT EXISTS is_digital BOOLEAN DEFAULT FALSE AFTER category,
ADD COLUMN IF NOT EXISTS requires_shipping BOOLEAN DEFAULT TRUE AFTER is_digital,
ADD INDEX IF NOT EXISTS idx_products_digital (is_digital);

-- Insert default protection policies
INSERT INTO protection_policies (policy_name, description, coverage_conditions, max_coverage_amount, escrow_period_days) VALUES
('Standard Buyer Protection', 'Covers purchases up to $2000 with full refund protection', JSON_OBJECT(
    'max_item_value', 2000,
    'eligible_payment_methods', JSON_ARRAY('stripe', 'paypal'),
    'excluded_categories', JSON_ARRAY('digital_goods', 'services')
), 2000.00, 14),

('Premium Buyer Protection', 'Enhanced protection for high-value items up to $10000', JSON_OBJECT(
    'max_item_value', 10000,
    'requires_signature_confirmation', true,
    'requires_insurance', true
), 10000.00, 21),

('Digital Goods Protection', 'Limited protection for digital products and services', JSON_OBJECT(
    'max_item_value', 500,
    'coverage_period_hours', 72,
    'applicable_categories', JSON_ARRAY('digital_goods', 'software', 'ebooks')
), 500.00, 3)
ON DUPLICATE KEY UPDATE 
policy_name = VALUES(policy_name);

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_escrow_auto_release_check ON escrow_transactions (status, release_date, auto_release_processed);
CREATE INDEX IF NOT EXISTS idx_disputes_open ON escrow_disputes (status, created_at);
CREATE INDEX IF NOT EXISTS idx_merchant_ratings_verified ON merchant_ratings (merchant_id, is_verified_purchase, rating);

-- Add escrow-related notification types
INSERT IGNORE INTO notification_types (name, description, is_email, is_sms, is_push) VALUES
('escrow_created', 'Escrow created for order', TRUE, FALSE, TRUE),
('escrow_shipped', 'Order shipped - tracking available', TRUE, FALSE, TRUE),
('escrow_release_reminder', 'Reminder to confirm receipt', TRUE, FALSE, TRUE),
('escrow_auto_release_warning', 'Auto-release in 24 hours warning', TRUE, FALSE, TRUE),
('escrow_released', 'Payment released to seller', TRUE, FALSE, TRUE),
('dispute_initiated', 'Dispute opened for order', TRUE, FALSE, TRUE),
('dispute_response_needed', 'Response needed for dispute', TRUE, FALSE, TRUE),
('dispute_resolved', 'Dispute has been resolved', TRUE, FALSE, TRUE),
('protection_claim_submitted', 'Protection claim submitted', TRUE, FALSE, TRUE),
('protection_claim_approved', 'Protection claim approved', TRUE, FALSE, TRUE);