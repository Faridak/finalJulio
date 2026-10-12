-- Migration 036: internal staff chat (channels, DMs, messages, read cursors).
CREATE TABLE IF NOT EXISTS chat_channels (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NULL,                  -- NULL for direct messages
    type        ENUM('group','direct') NOT NULL DEFAULT 'group',
    dm_key      VARCHAR(40) NULL,                  -- 'dm:minUid:maxUid' unique per pair
    created_by  INT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dm (dm_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_members (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    channel_id    INT NOT NULL,
    user_id       INT NOT NULL,
    last_read_id  INT NOT NULL DEFAULT 0,
    joined_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member (channel_id, user_id),
    CONSTRAINT fk_cm_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS chat_messages (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    channel_id  INT NOT NULL,
    user_id     INT NOT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_msg_channel (channel_id, id),
    CONSTRAINT fk_msg_channel FOREIGN KEY (channel_id) REFERENCES chat_channels(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default company-wide channel.
INSERT INTO chat_channels (name, type, created_by)
SELECT 'General', 'group', NULL
WHERE NOT EXISTS (SELECT 1 FROM chat_channels WHERE name = 'General' AND type = 'group');
