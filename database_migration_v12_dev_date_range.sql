-- ============================================================
-- Migration V12: Dev work date range (BA cấu hình khoảng ngày)
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE tasks ADD COLUMN IF NOT EXISTS dev_planned_start DATE NULL AFTER dev_deadline;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS dev_planned_end   DATE NULL AFTER dev_planned_start;

-- Backfill: dev_planned_end = DATE(dev_deadline) nếu có
UPDATE tasks SET dev_planned_end = DATE(dev_deadline) WHERE dev_deadline IS NOT NULL AND dev_planned_end IS NULL;

SELECT 'Migration V12 done.' AS status;
