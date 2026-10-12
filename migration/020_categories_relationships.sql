-- Migration 020: Hierarchical categories + product relationships (accessories/
-- components/compatibility). Pricing tables already exist (product_discounts,
-- product_promotions, product_variants) and are wired by PricingService. Idempotent.

CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NULL,
    level TINYINT NOT NULL DEFAULT 1,      -- 1 = category, 2 = subcategory, ...
    sort_order INT NOT NULL DEFAULT 0,
    attributes TEXT NULL,                   -- JSON: facet defaults for this category
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cat_parent (parent_id),
    INDEX idx_cat_level (level),
    UNIQUE KEY uq_cat (parent_id, name)
);

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS category_id INT NULL AFTER subcategory;
CREATE INDEX IF NOT EXISTS idx_products_category_id ON products (category_id);

-- Product-to-product relationships: what a product needs or pairs with.
-- e.g. a toilet -> seat/hose/knob/connector (component) + wax ring (accessory);
--      a hose -> copper/plastic/metal (alternative material choices).
CREATE TABLE IF NOT EXISTS product_relationships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,                 -- the primary product
    related_product_id INT NOT NULL,         -- the component/accessory/alternative
    relationship_type ENUM('component','accessory','compatible','alternative','substitute','bundle')
        NOT NULL DEFAULT 'accessory',
    is_required TINYINT(1) NOT NULL DEFAULT 0,  -- fundamental to the primary product
    sort_order INT NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rel (product_id, related_product_id, relationship_type),
    INDEX idx_rel_product (product_id),
    INDEX idx_rel_related (related_product_id),
    INDEX idx_rel_type (relationship_type)
);
