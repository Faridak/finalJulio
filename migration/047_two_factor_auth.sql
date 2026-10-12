-- Migration 047: two-factor authentication (TOTP) — TwoFactorAuth + two-factor-auth.php.
-- profile.php queried user_2fa on every load and login.php had 2FA commented
-- out "until tables are set up". These are those tables.
CREATE TABLE IF NOT EXISTS user_2fa (
    user_id         INT PRIMARY KEY,
    secret_key      VARCHAR(64) NOT NULL,
    is_enabled      TINYINT(1) NOT NULL DEFAULT 0,
    last_used_step  BIGINT NULL,            -- last accepted TOTP time-step (replay guard)
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NULL,
    CONSTRAINT fk_2fa_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_2fa_backup_codes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    code_hash   VARCHAR(255) NOT NULL,
    is_used     TINYINT(1) NOT NULL DEFAULT 0,
    used_at     DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_2fabc_user (user_id, is_used),
    CONSTRAINT fk_2fabc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- device_fingerprint = sha256 of a random per-device cookie token (never the raw token).
CREATE TABLE IF NOT EXISTS user_trusted_devices (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    device_name         VARCHAR(120) NOT NULL,
    device_fingerprint  CHAR(64) NOT NULL,
    user_agent          VARCHAR(500) NULL,
    ip_address          VARCHAR(45) NULL,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    last_used           DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NULL,
    UNIQUE KEY uq_td_fingerprint (device_fingerprint),
    KEY idx_td_user (user_id, is_active),
    CONSTRAINT fk_td_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
