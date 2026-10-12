-- Migration 050: security_events — profile.php's "recent security activity"
-- read this table, but nothing created or wrote it (the profile page died
-- there). Security::logSecurityEvent() now writes here as well as to logs/.
CREATE TABLE IF NOT EXISTS security_events (
    id          BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,
    event_type  VARCHAR(64) NOT NULL,
    severity    ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(500) NULL,
    details     TEXT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_se_user (user_id, created_at),
    KEY idx_se_type (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
