-- Migration 022: transactional email queue/log + product return window. Idempotent.

CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(160) NOT NULL,
    to_name VARCHAR(160) NULL,
    subject VARCHAR(255) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    template VARCHAR(60) NULL,
    reference_type VARCHAR(40) NULL,
    reference_id INT NULL,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    attempts INT NOT NULL DEFAULT 0,
    error VARCHAR(500) NULL,
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email_status (status),
    INDEX idx_email_ref (reference_type, reference_id)
);

-- Per-product return window (days). NULL/0 = use the store default (30).
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS return_window_days INT NULL AFTER category_id;
