-- Migration 048: buyer <-> seller disputes, per seller portion of an order.
-- Replaces the stale EscrowSystem dispute flow (it targeted escrow_* tables and
-- order statuses that never existed). Refunds go through the existing
-- ReturnService so payment, inventory and the ledger stay consistent.
CREATE TABLE IF NOT EXISTS order_disputes (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    order_id            INT NOT NULL,
    merchant_id         INT NOT NULL,               -- seller whose items are disputed
    buyer_id            INT NOT NULL,
    reason              ENUM('not_received','not_as_described','damaged','wrong_item','missing_parts','other') NOT NULL,
    description         TEXT NOT NULL,
    requested_outcome   ENUM('full_refund','partial_refund','replacement','other') NOT NULL DEFAULT 'full_refund',
    amount_requested    DECIMAL(10,2) NULL,
    status              ENUM('open','seller_responded','under_review','resolved_buyer','resolved_seller','closed') NOT NULL DEFAULT 'open',
    priority            ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    resolution_notes    TEXT NULL,
    return_request_id   INT NULL,                   -- refund created on a buyer-favour resolution
    resolved_by         INT NULL,
    resolved_at         DATETIME NULL,
    seller_due_at       DATETIME NULL,              -- seller must answer before this; then admin may decide
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_disp_status (status, priority),
    KEY idx_disp_order (order_id),
    KEY idx_disp_merchant (merchant_id, status),
    KEY idx_disp_buyer (buyer_id),
    CONSTRAINT fk_disp_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_dispute_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    dispute_id  INT NOT NULL,
    user_id     INT NOT NULL,
    author_role ENUM('buyer','seller','admin') NOT NULL,
    body        TEXT NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_dm_dispute (dispute_id, created_at),
    CONSTRAINT fk_dm_dispute FOREIGN KEY (dispute_id) REFERENCES order_disputes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
