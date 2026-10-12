-- Migration 026: Marketplace seller payouts. When a SELLER-owned (marketplace)
-- product sells, the platform is an agent: it keeps commission and owes the
-- seller the rest (Merchant Payable). Payout runs settle that liability.
-- Idempotent.

CREATE TABLE IF NOT EXISTS merchant_payouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    period_start DATE NULL,
    period_end DATE NULL,
    gross_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    commission_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    net_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    order_count INT NOT NULL DEFAULT 0,
    status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
    method VARCHAR(40) NULL,
    reference VARCHAR(64) NULL,
    paid_at DATETIME NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payout_merchant (merchant_id),
    INDEX idx_payout_status (status)
);
