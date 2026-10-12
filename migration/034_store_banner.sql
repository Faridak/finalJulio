-- Migration 034: per-store banner image (below jumbotron) + uploaded image support.
ALTER TABLE supplier_store
    ADD COLUMN IF NOT EXISTS banner_image VARCHAR(255) NULL AFTER jumbotron_cta_url,
    ADD COLUMN IF NOT EXISTS banner_link  VARCHAR(255) NULL AFTER banner_image;
