-- Migration 017: Inventory shrinkage control (loss / theft / damage)
-- Every stock discrepancy becomes a reason-coded, costed, audited event that
-- posts to the general ledger. Large write-offs require approval. Idempotent.

-- Shrinkage expense account.
INSERT INTO chart_of_accounts (account_code, account_name, account_type, balance, is_active, created_at)
SELECT '5200', 'Inventory Shrinkage & Loss', 'expense', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code = '5200');
-- Inventory overage / found-stock gain account.
INSERT INTO chart_of_accounts (account_code, account_name, account_type, balance, is_active, created_at)
SELECT '4300', 'Inventory Adjustment Gain', 'revenue', 0, 1, NOW()
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code = '4300');

-- Audit trail: one row per discrepancy, with reason, cost value, and approval.
CREATE TABLE IF NOT EXISTS inventory_adjustments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    location_id INT NOT NULL,
    bin_id INT NULL,
    reason ENUM('count','theft','damage','loss','expiry','found','transfer') NOT NULL DEFAULT 'count',
    system_qty INT NOT NULL DEFAULT 0,
    counted_qty INT NULL,
    delta INT NOT NULL,                       -- counted - system (negative = shortage)
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
    value DECIMAL(12,2) NOT NULL DEFAULT 0,   -- abs(delta) * unit_cost
    status ENUM('applied','pending_approval','rejected') NOT NULL DEFAULT 'applied',
    notes VARCHAR(500) NULL,
    counted_by INT NULL,
    approved_by INT NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_adj_product (product_id),
    INDEX idx_adj_reason (reason),
    INDEX idx_adj_status (status),
    INDEX idx_adj_bin (bin_id)
);
