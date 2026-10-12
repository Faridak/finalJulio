-- Migration 046: buyer <-> seller messaging (messages.php + MessagingSystem).
-- The code shipped without its tables, so messages.php fatalled on every load.
CREATE TABLE IF NOT EXISTS conversations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    type        ENUM('buyer_seller','support','dispute') NOT NULL DEFAULT 'buyer_seller',
    subject     VARCHAR(255) NOT NULL,
    created_by  INT NOT NULL,
    status      ENUM('active','closed') NOT NULL DEFAULT 'active',
    metadata    LONGTEXT NULL CHECK (metadata IS NULL OR JSON_VALID(metadata)),
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_conv_status (status),
    KEY idx_conv_updated (updated_at),
    CONSTRAINT fk_conv_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS conversation_participants (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id  INT NOT NULL,
    user_id          INT NOT NULL,
    role             ENUM('buyer','seller','admin') NOT NULL,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    last_read_at     DATETIME NULL,
    joined_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_conv_user (conversation_id, user_id),
    KEY idx_cp_user (user_id, is_active),
    CONSTRAINT fk_cp_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_cp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id  INT NOT NULL,
    sender_id        INT NOT NULL,
    message_text     TEXT NOT NULL,
    message_type     ENUM('text','image','file','system') NOT NULL DEFAULT 'text',
    attachment_url   VARCHAR(500) NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_msg_conv_created (conversation_id, created_at),
    KEY idx_msg_sender (sender_id),
    CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_blocks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id  INT NOT NULL,
    blocked_id  INT NOT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_block (blocker_id, blocked_id),
    CONSTRAINT fk_ub_blocker FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_ub_blocked FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_reports (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    message_id   INT NOT NULL,
    reporter_id  INT NOT NULL,
    reason       VARCHAR(255) NOT NULL,
    status       ENUM('pending','reviewed','dismissed') NOT NULL DEFAULT 'pending',
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_mr_status (status),
    CONSTRAINT fk_mr_msg FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE,
    CONSTRAINT fk_mr_reporter FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
