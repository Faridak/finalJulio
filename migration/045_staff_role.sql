-- Migration 045: 'staff' role — limited employees (warehouse, sales, marketing…)
-- get admin-area logins gated by their staff_scope; full admins keep 'admin'.
ALTER TABLE users MODIFY role ENUM('user','customer','merchant','admin','staff') DEFAULT 'user';
ALTER TABLE employees ADD COLUMN IF NOT EXISTS user_id INT NULL;
