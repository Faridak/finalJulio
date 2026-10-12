-- Migration 038: PM kanban layer over the existing engineering module.
ALTER TABLE engineering_tasks
    ADD COLUMN IF NOT EXISTS sort_order INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS progress_pct TINYINT NOT NULL DEFAULT 0;
ALTER TABLE quotes
    ADD COLUMN IF NOT EXISTS source_order_id INT NULL AFTER user_id;

-- Backlog tasks start unassigned; the PM delegates from the board.
ALTER TABLE engineering_tasks MODIFY assigned_to INT NULL;
