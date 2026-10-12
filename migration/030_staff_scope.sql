-- Migration 030: staff scope for role granularity within the admin role.
-- 'all' = full admin (default, back-compatible). Others limit which admin
-- sections a staff member can open: finance, warehouse, catalog, orders, sellers.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS staff_scope VARCHAR(32) NOT NULL DEFAULT 'all' AFTER role;
