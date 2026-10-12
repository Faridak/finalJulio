-- Migration 043: search performance — FULLTEXT keyword index + filter indexes.
-- Keyword search switches from LIKE '%..%' (full scan) to MATCH..AGAINST (index),
-- with relevance ranking. Category/price filters narrow via B-tree indexes —
-- the "search inside a category" effect without physically splitting tables.
ALTER TABLE products ADD FULLTEXT INDEX IF NOT EXISTS ft_products_search (name, description, sku);
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_price (price);
ALTER TABLE products ADD INDEX IF NOT EXISTS idx_products_inventory (inventory);
