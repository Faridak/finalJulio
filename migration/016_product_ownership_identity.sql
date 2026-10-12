-- Migration 016: Product ownership model + supplier billing + internal identity
-- Distinguishes OUR products (internal SKU + barcode, scannable) from supplier
-- marketplace listings, and captures how a selling supplier is billed
-- (storage rent when we hold stock, subscription when digital-only). Idempotent.

-- --- Products: ownership + identity + pricing ------------------------------
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS ownership ENUM('own','marketplace') NOT NULL DEFAULT 'own' AFTER merchant_id,
    ADD COLUMN IF NOT EXISTS source_supplier_id INT NULL AFTER ownership,
    ADD COLUMN IF NOT EXISTS fulfillment ENUM('warehouse','digital') NULL AFTER source_supplier_id,
    ADD COLUMN IF NOT EXISTS barcode VARCHAR(64) NULL AFTER sku,
    ADD COLUMN IF NOT EXISTS margin_percentage DECIMAL(5,2) NULL AFTER cost_price;

CREATE INDEX IF NOT EXISTS idx_products_barcode ON products (barcode);
CREATE INDEX IF NOT EXISTS idx_products_ownership ON products (ownership);

-- --- Suppliers: platform role + billing model -----------------------------
ALTER TABLE suppliers
    ADD COLUMN IF NOT EXISTS platform_role ENUM('vendor','seller','both') NOT NULL DEFAULT 'vendor'
        COMMENT 'vendor = supplies parts we own; seller = sells on our platform',
    ADD COLUMN IF NOT EXISTS billing_model ENUM('none','storage_rent','subscription') NOT NULL DEFAULT 'none',
    ADD COLUMN IF NOT EXISTS subscription_fee_monthly DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS storage_rent_monthly DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Existing migrated catalog is OURS, sourced from the legacy business.
UPDATE products SET ownership = 'own' WHERE ownership IS NULL;
