-- Migration 029: admin audit trail. Records who did what sensitive action
-- (price change, payout, write-off approval, PO approve/pay, refund, role change)
-- with before/after context. Append-only; never updated or deleted by the app.
CREATE TABLE IF NOT EXISTS audit_log (
    id            BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT NULL,                       -- actor (NULL = system/cron)
    user_email    VARCHAR(190) NULL,              -- denormalized for durability
    action        VARCHAR(64) NOT NULL,           -- e.g. 'seller.payout', 'price.update'
    entity_type   VARCHAR(64) NULL,               -- e.g. 'product', 'merchant', 'order'
    entity_id     VARCHAR(64) NULL,
    summary       VARCHAR(255) NULL,              -- human sentence
    meta          JSON NULL,                      -- before/after + amounts
    ip_address    VARCHAR(45) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_action (action),
    INDEX idx_audit_entity (entity_type, entity_id),
    INDEX idx_audit_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
