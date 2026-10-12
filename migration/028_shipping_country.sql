-- Migration 028: capture the customer's shipping country on the order.
-- Drives PayPal account routing (MX -> PayPal Mexico, else PayPal USA) and
-- future tax/shipping-zone logic. Idempotent.
ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS shipping_country CHAR(2) NULL DEFAULT NULL AFTER shipping_address;
CREATE INDEX IF NOT EXISTS idx_orders_country ON orders (shipping_country);
