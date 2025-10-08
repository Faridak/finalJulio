-- Credit Management & Collections Schema
-- This migration adds tables for customer credit limits, credit applications, collections, aging reports, and credit risk scoring

-- Create customer credit limits table
CREATE TABLE IF NOT EXISTS customer_credit_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    credit_limit DECIMAL(15,2) DEFAULT 0.00,
    used_credit DECIMAL(15,2) DEFAULT 0.00,
    available_credit DECIMAL(15,2) DEFAULT 0.00,
    credit_status ENUM('active', 'suspended', 'closed') DEFAULT 'active',
    last_review_date DATE,
    next_review_date DATE,
    credit_score INT DEFAULT 0,
    risk_level ENUM('low', 'medium', 'high') DEFAULT 'low',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customer_credit_customer (customer_id),
    INDEX idx_customer_credit_status (credit_status),
    INDEX idx_customer_credit_risk (risk_level)
);

-- Create credit applications table
CREATE TABLE IF NOT EXISTS credit_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    application_date DATE NOT NULL,
    requested_credit_limit DECIMAL(15,2) NOT NULL,
    approved_credit_limit DECIMAL(15,2) DEFAULT 0.00,
    application_status ENUM('pending', 'approved', 'rejected', 'under_review') DEFAULT 'pending',
    reviewer_id INT,
    review_date DATE,
    review_notes TEXT,
    supporting_documents JSON,
    decision_reason TEXT,
    effective_date DATE,
    expiry_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_credit_applications_customer (customer_id),
    INDEX idx_credit_applications_status (application_status),
    INDEX idx_credit_applications_date (application_date)
);

-- Create collections table
CREATE TABLE IF NOT EXISTS collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_id INT,
    invoice_number VARCHAR(50),
    original_amount DECIMAL(15,2) NOT NULL,
    outstanding_amount DECIMAL(15,2) NOT NULL,
    due_date DATE NOT NULL,
    days_overdue INT DEFAULT 0,
    collection_status ENUM('new', 'in_progress', 'escalated', 'resolved', 'written_off') DEFAULT 'new',
    assigned_collector INT,
    last_contact_date DATE,
    next_action_date DATE,
    collection_notes TEXT,
    resolution_date DATE,
    resolution_amount DECIMAL(15,2) DEFAULT 0.00,
    resolution_type ENUM('paid', 'partial_payment', 'written_off', 'disputed') DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_collections_customer (customer_id),
    INDEX idx_collections_status (collection_status),
    INDEX idx_collections_overdue (days_overdue),
    INDEX idx_collections_invoice (invoice_id)
);

-- Create aging reports table
CREATE TABLE IF NOT EXISTS aging_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    report_date DATE NOT NULL,
    current_amount DECIMAL(15,2) DEFAULT 0.00,
    days_1_30 DECIMAL(15,2) DEFAULT 0.00,
    days_31_60 DECIMAL(15,2) DEFAULT 0.00,
    days_61_90 DECIMAL(15,2) DEFAULT 0.00,
    days_91_120 DECIMAL(15,2) DEFAULT 0.00,
    days_over_120 DECIMAL(15,2) DEFAULT 0.00,
    total_outstanding DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aging_reports_customer (customer_id),
    INDEX idx_aging_reports_date (report_date)
);

-- Create credit risk scores table
CREATE TABLE IF NOT EXISTS credit_risk_scores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    score_date DATE NOT NULL,
    payment_history_score INT DEFAULT 0,
    credit_utilization_score INT DEFAULT 0,
    length_of_credit_score INT DEFAULT 0,
    new_credit_score INT DEFAULT 0,
    credit_mix_score INT DEFAULT 0,
    overall_score INT DEFAULT 0,
    risk_category ENUM('excellent', 'good', 'fair', 'poor', 'very_poor') DEFAULT 'fair',
    factors TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_credit_risk_customer (customer_id),
    INDEX idx_credit_risk_date (score_date),
    INDEX idx_credit_risk_category (risk_category)
);

-- Add foreign key constraints (if not already existing)
-- Note: We're not adding foreign key constraints here to avoid issues with existing data
-- In a production environment, these would be added with proper error handling

-- Add credit-related columns to existing accounts_receivable table
ALTER TABLE accounts_receivable 
ADD COLUMN IF NOT EXISTS credit_limit_applied BOOLEAN DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS credit_approved_amount DECIMAL(15,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS days_overdue INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS collection_status ENUM('not_due', 'current', 'overdue_30', 'overdue_60', 'overdue_90', 'overdue_120', 'written_off') DEFAULT 'not_due';

-- Add credit-related columns to existing customers table (if it exists)
-- Since there's no dedicated customers table, we'll use users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS credit_score INT DEFAULT 0,
ADD COLUMN IF NOT EXISTS risk_level ENUM('low', 'medium', 'high') DEFAULT 'low';

-- Insert default credit risk scoring factors
INSERT IGNORE INTO credit_risk_scores (customer_id, score_date, payment_history_score, credit_utilization_score, length_of_credit_score, new_credit_score, credit_mix_score, overall_score, risk_category, factors) 
SELECT id, CURDATE(), 80, 75, 70, 65, 60, 70, 'fair', 'Initial risk assessment' 
FROM users 
WHERE role = 'customer';

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_accounts_receivable_collection ON accounts_receivable (collection_status, days_overdue);
CREATE INDEX IF NOT EXISTS idx_accounts_receivable_credit ON accounts_receivable (credit_limit_applied, credit_approved_amount);