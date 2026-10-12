-- Migration 037: chat profiles (nickname + avatar) and department channels.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS chat_nickname VARCHAR(40) NULL,
    ADD COLUMN IF NOT EXISTS chat_avatar   VARCHAR(255) NULL;
