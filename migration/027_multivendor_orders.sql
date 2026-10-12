-- Migration 027: Multi-vendor order splitting. One customer order can contain
-- items from several sellers; each seller gets a sub-order with its own subtotal,
-- commission, fulfilment status and shipment. Idempotent.

CREATE TABLE IF NOT EXISTS merchant_suborders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    merchant_id INT NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    commission DECIMAL(12,2) NOT NULL DEFAULT 0,
    net DECIMAL(12,2) NOT NULL DEFAULT 0,
    item_count INT NOT NULL DEFAULT 0,
    ownership ENUM('own','marketplace') NOT NULL DEFAULT 'own',
    status ENUM('processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'processing',
    shipment_id INT NULL,
    shipped_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_suborder (order_id, merchant_id),
    INDEX idx_sub_merchant (merchant_id),
    INDEX idx_sub_status (status)
);

-- Tag a shipment with the seller it belongs to (one shipment per seller/order).
ALTER TABLE shipments
    ADD COLUMN IF NOT EXISTS merchant_id INT NULL AFTER order_id;
CREATE INDEX IF NOT EXISTS idx_shipments_merchant ON shipments (order_id, merchant_id);
