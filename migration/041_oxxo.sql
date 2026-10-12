-- Migration 041: OXXO cash-voucher payments (Mexico).
CREATE TABLE IF NOT EXISTS oxxo_payments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    order_id    INT NOT NULL,
    reference   VARCHAR(20) NOT NULL,          -- 14-digit voucher reference
    amount      DECIMAL(12,2) NOT NULL,
    status      ENUM('pending','paid','expired','cancelled') NOT NULL DEFAULT 'pending',
    expires_at  DATETIME NOT NULL,
    paid_at     DATETIME NULL,
    confirmed_via ENUM('webhook','manual') NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_oxxo_ref (reference),
    UNIQUE KEY uq_oxxo_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
