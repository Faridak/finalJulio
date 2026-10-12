-- Migration 018: cycle-count timestamp + (supplier_products already exists).
ALTER TABLE inventory_bins
    ADD COLUMN IF NOT EXISTS last_counted_at DATETIME NULL AFTER utilization_percentage;

CREATE INDEX IF NOT EXISTS idx_bins_last_counted ON inventory_bins (last_counted_at);
