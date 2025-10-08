-- Database migration script for Enhanced Tax Management
-- This script adds new tables and columns to support advanced tax features

-- 1. Tax Exemptions table for B2B sales and non-profit customers
CREATE TABLE IF NOT EXISTS tax_exemptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    exemption_type ENUM('b2b', 'non_profit', 'government', 'other') NOT NULL,
    exemption_certificate_number VARCHAR(100),
    exemption_rate DECIMAL(5,2) DEFAULT 0.00,
    effective_date DATE NOT NULL,
    expiry_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tax_exemptions_customer (customer_id),
    INDEX idx_tax_exemptions_type (exemption_type),
    INDEX idx_tax_exemptions_active (is_active)
);

ALTER TABLE tax_exemptions ADD CONSTRAINT fk_tax_exemptions_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE;

-- 2. Tax Audit Trail table for complete tax calculation history
CREATE TABLE IF NOT EXISTS tax_audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    transaction_type ENUM('order', 'invoice', 'refund') NOT NULL,
    tax_rule_id INT,
    customer_id INT,
    country_id INT,
    state_id INT,
    product_category VARCHAR(100),
    tax_type ENUM('sales', 'vat', 'gst', 'hst') NOT NULL,
    tax_rate_applied DECIMAL(5,2) NOT NULL,
    tax_amount_calculated DECIMAL(10,2) NOT NULL,
    exemption_applied BOOLEAN DEFAULT FALSE,
    exemption_id INT,
    reverse_charge_applied BOOLEAN DEFAULT FALSE,
    calculated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    calculated_by INT,
    notes TEXT,
    INDEX idx_tax_audit_transaction (transaction_id, transaction_type),
    INDEX idx_tax_audit_customer (customer_id),
    INDEX idx_tax_audit_date (calculated_at),
    INDEX idx_tax_audit_rule (tax_rule_id)
);

ALTER TABLE tax_audit_trail ADD CONSTRAINT fk_tax_audit_tax_rule FOREIGN KEY (tax_rule_id) REFERENCES tax_rules(id) ON DELETE SET NULL;
ALTER TABLE tax_audit_trail ADD CONSTRAINT fk_tax_audit_customer FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE tax_audit_trail ADD CONSTRAINT fk_tax_audit_country FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE SET NULL;
ALTER TABLE tax_audit_trail ADD CONSTRAINT fk_tax_audit_state FOREIGN KEY (state_id) REFERENCES states(id) ON DELETE SET NULL;
ALTER TABLE tax_audit_trail ADD CONSTRAINT fk_tax_audit_exemption FOREIGN KEY (exemption_id) REFERENCES tax_exemptions(id) ON DELETE SET NULL;
ALTER TABLE tax_audit_trail ADD CONSTRAINT fk_tax_audit_calculated_by FOREIGN KEY (calculated_by) REFERENCES users(id) ON DELETE SET NULL;

-- 3. Reverse Charge VAT table for EU B2B transactions
CREATE TABLE IF NOT EXISTS reverse_charge_vat (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_country_id INT NOT NULL,
    buyer_country_id INT NOT NULL,
    product_category VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    effective_date DATE NOT NULL,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_reverse_charge_rule (seller_country_id, buyer_country_id, product_category),
    INDEX idx_reverse_charge_seller (seller_country_id),
    INDEX idx_reverse_charge_buyer (buyer_country_id)
);

ALTER TABLE reverse_charge_vat ADD CONSTRAINT fk_reverse_charge_seller FOREIGN KEY (seller_country_id) REFERENCES countries(id) ON DELETE CASCADE;
ALTER TABLE reverse_charge_vat ADD CONSTRAINT fk_reverse_charge_buyer FOREIGN KEY (buyer_country_id) REFERENCES countries(id) ON DELETE CASCADE;

-- 4. Add columns to orders table for tax tracking
ALTER TABLE orders 
ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER shipping_cost,
ADD COLUMN IF NOT EXISTS tax_rate_applied DECIMAL(5,2) DEFAULT 0.00 AFTER tax_amount,
ADD COLUMN IF NOT EXISTS tax_exempt BOOLEAN DEFAULT FALSE AFTER tax_rate_applied,
ADD COLUMN IF NOT EXISTS tax_exemption_id INT AFTER tax_exempt,
ADD COLUMN IF NOT EXISTS reverse_charge_applied BOOLEAN DEFAULT FALSE AFTER tax_exemption_id,
ADD COLUMN IF NOT EXISTS tax_audit_trail_id INT AFTER reverse_charge_applied;

-- 5. Add columns to order_items table for detailed tax tracking
ALTER TABLE order_items 
ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00 AFTER price,
ADD COLUMN IF NOT EXISTS tax_rate_applied DECIMAL(5,2) DEFAULT 0.00 AFTER tax_amount;

-- 6. Add foreign key constraints for the new columns
-- These foreign key constraints will be added to the orders table
-- Note: If they already exist, you may need to manually skip this section

ALTER TABLE orders ADD CONSTRAINT fk_orders_tax_exemption FOREIGN KEY (tax_exemption_id) REFERENCES tax_exemptions(id) ON DELETE SET NULL;
ALTER TABLE orders ADD CONSTRAINT fk_orders_tax_audit_trail FOREIGN KEY (tax_audit_trail_id) REFERENCES tax_audit_trail(id) ON DELETE SET NULL;

-- 7. Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_orders_tax ON orders(tax_amount, tax_rate_applied);
CREATE INDEX IF NOT EXISTS idx_order_items_tax ON order_items(tax_amount, tax_rate_applied);