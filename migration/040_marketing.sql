-- Migration 040: marketing department.

CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(60) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_by INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS marketing_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    channel ENUM('instagram','facebook','tiktok','x','whatsapp','email','other') NOT NULL DEFAULT 'instagram',
    product_id INT NULL,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 10,
    discount_id INT NULL,                     -- the product_discounts row it created
    promo_code VARCHAR(30) NULL,
    utm_url VARCHAR(500) NULL,
    post_text TEXT NULL,
    starts DATE NULL, ends DATE NULL,
    status ENUM('active','ended') NOT NULL DEFAULT 'active',
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(30) NOT NULL,
    discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    value DECIMAL(10,2) NOT NULL,
    product_id INT NULL,                      -- NULL = whole order
    user_id INT NULL,                         -- personal promotion: only this customer
    max_uses INT NOT NULL DEFAULT 0,          -- 0 = unlimited
    used_count INT NOT NULL DEFAULT 0,
    starts DATE NULL, ends DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_promo_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,                         -- the customer
    customer_email VARCHAR(190) NULL,
    subject VARCHAR(180) NOT NULL,
    kind ENUM('complaint','comment','question','return','other') NOT NULL DEFAULT 'comment',
    status ENUM('open','pending','resolved','closed') NOT NULL DEFAULT 'open',
    opened_by INT NULL,                       -- staff member who logged it
    assigned_to INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NULL,
    body TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(180) NOT NULL,
    body_html TEXT NOT NULL,
    segment ENUM('all','loyal','recent') NOT NULL DEFAULT 'all',
    recipients INT NOT NULL DEFAULT 0,
    sent_by INT NULL,
    sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    headline_a VARCHAR(200) NOT NULL,
    headline_b VARCHAR(200) NOT NULL,
    views_a INT NOT NULL DEFAULT 0, views_b INT NOT NULL DEFAULT 0,
    clicks_a INT NOT NULL DEFAULT 0, clicks_b INT NOT NULL DEFAULT 0,
    status ENUM('running','stopped') NOT NULL DEFAULT 'running',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS promo_code VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0;
