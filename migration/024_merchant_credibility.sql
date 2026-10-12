-- Migration 024: Supplier/merchant credibility scoring (affects subscription +
-- insurance fees). Sellers are users with role='merchant'. Idempotent.

CREATE TABLE IF NOT EXISTS merchant_credibility (
    merchant_id INT PRIMARY KEY,
    orders_count INT NOT NULL DEFAULT 0,
    revenue DECIMAL(14,2) NOT NULL DEFAULT 0,
    returns_count INT NOT NULL DEFAULT 0,
    refund_rate DECIMAL(6,3) NOT NULL DEFAULT 0,     -- returns / orders (0..1)
    avg_rating DECIMAL(3,2) NULL,                      -- customer satisfaction
    score TINYINT NOT NULL DEFAULT 0,                  -- 0..100
    tier ENUM('A','B','C','D') NOT NULL DEFAULT 'C',
    base_subscription DECIMAL(10,2) NOT NULL DEFAULT 99.00,
    subscription_fee DECIMAL(10,2) NOT NULL DEFAULT 99.00,  -- after tier multiplier
    insurance_rate DECIMAL(5,3) NOT NULL DEFAULT 1.000,     -- % of stored value / mo
    last_scored_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cred_tier (tier),
    INDEX idx_cred_score (score)
);
