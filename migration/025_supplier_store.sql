-- Migration 025: Supplier storefront builder (per merchant = users.role='merchant').
-- Idempotent.

CREATE TABLE IF NOT EXISTS supplier_store (
    merchant_id INT PRIMARY KEY,
    store_name VARCHAR(120) NULL,
    store_slug VARCHAR(140) NULL,
    theme_color VARCHAR(20) NOT NULL DEFAULT '#2563eb',
    -- jumbotron / hero
    jumbotron_headline VARCHAR(160) NULL,
    jumbotron_subtext VARCHAR(255) NULL,
    jumbotron_image VARCHAR(255) NULL,
    jumbotron_cta_text VARCHAR(60) NULL,
    jumbotron_cta_url VARCHAR(255) NULL,
    -- promo popup
    popup_enabled TINYINT(1) NOT NULL DEFAULT 0,
    popup_title VARCHAR(120) NULL,
    popup_text VARCHAR(400) NULL,
    popup_coupon VARCHAR(40) NULL,
    popup_starts DATE NULL,
    popup_ends DATE NULL,
    -- layout
    section_order VARCHAR(255) NOT NULL DEFAULT 'jumbotron,featured,products',
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_store_slug (store_slug)
);

-- Per-store product flags: featured + display order in the seller's storefront.
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER image_url,
    ADD COLUMN IF NOT EXISTS store_sort INT NOT NULL DEFAULT 0 AFTER is_featured;

CREATE INDEX IF NOT EXISTS idx_products_featured ON products (merchant_id, is_featured);
