-- Migration 042: password reset tokens (hashed, single-use, 1h expiry).
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pr_email (email),
    INDEX idx_pr_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
