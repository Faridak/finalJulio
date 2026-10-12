-- Migration 019: Procurement PO (Type 1) — supplier completeness, customs lead
-- time, PO approval + payment (Accounts Payable) linkage. Idempotent.

-- Supplier: customs, banking, insurance, delivery aspects.
ALTER TABLE suppliers
    ADD COLUMN IF NOT EXISTS customs_clearance_days INT NOT NULL DEFAULT 0 COMMENT 'Extra days to clear customs',
    ADD COLUMN IF NOT EXISTS bank_name VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS bank_account VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS bank_swift VARCHAR(32) NULL,
    ADD COLUMN IF NOT EXISTS default_insurance_id INT NULL,
    ADD COLUMN IF NOT EXISTS delivery_terms VARCHAR(100) NULL COMMENT 'Incoterms / delivery aspect';

-- Purchase order: approval + payment state, AP link. (Existing status enum kept
-- for the fulfilment lifecycle; these track the money side.)
ALTER TABLE purchase_orders
    ADD COLUMN IF NOT EXISTS approval_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending' AFTER status,
    ADD COLUMN IF NOT EXISTS payment_status ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
    ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS ap_id INT NULL COMMENT 'accounts_payable row created on approval';

CREATE INDEX IF NOT EXISTS idx_po_approval ON purchase_orders (approval_status);
CREATE INDEX IF NOT EXISTS idx_po_payment ON purchase_orders (payment_status);
