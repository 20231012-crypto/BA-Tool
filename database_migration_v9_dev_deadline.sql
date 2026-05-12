-- ============================================================
-- Migration V9: Dev deadline (BA giao kèm hạn cho Dev)
-- ============================================================
SET NAMES utf8mb4;

ALTER TABLE tasks ADD COLUMN IF NOT EXISTS dev_deadline DATETIME NULL AFTER dev_end_at;

SELECT 'Migration V9 done.' AS status;
