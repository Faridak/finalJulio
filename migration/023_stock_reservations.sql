-- Migration 023: Stock reservations (time-limited holds + abandonment recovery).
-- Oversell is already prevented by OrderProcessor's FOR UPDATE lock; this closes
-- the leak where an abandoned (unpaid) order keeps stock deducted forever.
-- Idempotent.

CREATE TABLE IF NOT EXISTS stock_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    location_id INT NULL,
    quantity INT NOT NULL,
    status ENUM('held','committed','expired','released') NOT NULL DEFAULT 'held',
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_resv_order (order_id),
    INDEX idx_resv_status_expiry (status, expires_at),
    INDEX idx_resv_product (product_id)
);
