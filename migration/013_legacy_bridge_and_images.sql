-- Migration 013: Legacy bridge + multi-image support
-- Extends the NEW schema to receive legacy data without changing its design.
-- Image paths are stored verbatim so legacy files resolve unchanged after a
-- 1:1 folder copy (no renaming). Idempotent.

-- Bridge columns on products (traceability + image slug preservation).
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS legacy_id VARCHAR(30) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS legacy_image_slug VARCHAR(255) NULL AFTER image_url;

-- Unique crosswalk so re-running the ETL upserts instead of duplicating.
CREATE UNIQUE INDEX IF NOT EXISTS idx_products_legacy_id ON products (legacy_id);

-- Multi-image table (functionality improvement + receives legacy photos).
-- image_path is the RELATIVE path preserved from the legacy site, e.g.
--   'imagenes/1 pieza 150036.jpg' or 'photogallery/original/MXSFB (1).jpg'
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    legacy_filename VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pi_product (product_id),
    INDEX idx_pi_primary (product_id, is_primary),
    UNIQUE KEY uq_pi_product_path (product_id, image_path)
);
