-- Migration 014: product columns to receive legacy attributes cleanly
-- SKU (legacy IdProducto) and short description (legacy DescCorta). Idempotent.

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS sku VARCHAR(100) NULL AFTER name,
    ADD COLUMN IF NOT EXISTS short_description VARCHAR(500) NULL AFTER description;

CREATE INDEX IF NOT EXISTS idx_products_sku ON products (sku);
