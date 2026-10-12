-- Migration 044: product card thumbnails (legacy photos are up to 2000px).
ALTER TABLE products ADD COLUMN IF NOT EXISTS thumb_url VARCHAR(255) NULL AFTER image_url;
