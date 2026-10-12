-- Migration 039: work-order materials (BOM) + engineering revenue account.
CREATE TABLE IF NOT EXISTS work_order_materials (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    quote_id   INT NOT NULL,
    product_id INT NOT NULL,
    quantity   INT NOT NULL,
    unit_cost  DECIMAL(10,2) NOT NULL,        -- cost captured at consumption time
    total_cost DECIMAL(12,2) NOT NULL,
    added_by   INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_wom_quote (quote_id),
    CONSTRAINT fk_wom_quote FOREIGN KEY (quote_id) REFERENCES quotes(id),
    CONSTRAINT fk_wom_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO chart_of_accounts (account_code, account_name, account_type)
SELECT '4400','Engineering Revenue','revenue'
WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE account_code='4400');
