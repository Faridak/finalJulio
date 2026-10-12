-- Migration 021: Bank reconciliation — import bank / credit-card statements and
-- match each line to the general ledger, A/P payments, or A/R receipts. Idempotent.

CREATE TABLE IF NOT EXISTS bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    account_kind ENUM('bank','credit_card') NOT NULL DEFAULT 'bank',
    account_mask VARCHAR(32) NULL,               -- last 4 etc.
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    gl_account_code VARCHAR(10) NOT NULL DEFAULT '1000',  -- ledger account this maps to
    opening_balance DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bank (name, account_mask)
);

CREATE TABLE IF NOT EXISTS bank_statement_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NOT NULL,
    txn_date DATE NOT NULL,
    description VARCHAR(255) NULL,
    reference VARCHAR(100) NULL,
    amount DECIMAL(14,2) NOT NULL,               -- + deposit / - withdrawal
    running_balance DECIMAL(14,2) NULL,
    status ENUM('unmatched','matched','ignored') NOT NULL DEFAULT 'unmatched',
    match_ref_type VARCHAR(40) NULL,             -- ledger | accounts_payable | accounts_receivable | manual
    match_ref_id INT NULL,
    match_confidence DECIMAL(4,3) NULL,          -- 0..1
    matched_by INT NULL,
    matched_at DATETIME NULL,
    import_batch VARCHAR(40) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_bsl_account (bank_account_id),
    INDEX idx_bsl_status (status),
    INDEX idx_bsl_date (txn_date),
    UNIQUE KEY uq_line (bank_account_id, txn_date, amount, reference)
);
