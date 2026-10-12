-- Migration 049: re-apply 045's role ENUM change.
-- The old runner skipped the first statement after a file's header comment,
-- so 045 was recorded as applied while users.role still lacked 'staff'
-- (scoped staff accounts could not be created). MODIFY is idempotent.
ALTER TABLE users MODIFY role ENUM('user','customer','merchant','admin','staff') DEFAULT 'user';
ALTER TABLE employees ADD COLUMN IF NOT EXISTS user_id INT NULL;
