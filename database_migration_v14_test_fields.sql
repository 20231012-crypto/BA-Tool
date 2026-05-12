-- ============================================================
-- Migration V14: Test fields for Dev sync format
-- Thêm các cột phục vụ header dev-specific khi đồng bộ:
--   "Người thực hiện test", "Ngày test", "Trạng thái test"
-- ============================================================

USE ba_tool;
SET NAMES utf8mb4;

-- Đảm bảo procedure add_column_if_not_exists tồn tại (đã có từ v2 nhưng bị DROP IF EXISTS)
DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DELIMITER $$
CREATE PROCEDURE add_column_if_not_exists(
    IN tbl VARCHAR(64),
    IN col VARCHAR(64),
    IN col_def TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = tbl
          AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', col, ' ', col_def);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

CALL add_column_if_not_exists('tasks', 'tester_id',   'INT NULL COMMENT "FK -> users.id (người test)"');
CALL add_column_if_not_exists('tasks', 'test_date',   'DATE NULL COMMENT "Ngày test"');
CALL add_column_if_not_exists('tasks', 'test_status', 'VARCHAR(50) NULL COMMENT "Trạng thái test: Pass/Fail/Đang test/..."');

-- FK tester_id -> users (nếu chưa có)
SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND CONSTRAINT_NAME = 'fk_tasks_tester'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_tester FOREIGN KEY (tester_id) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "FK fk_tasks_tester already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP PROCEDURE IF EXISTS add_column_if_not_exists;

SELECT 'Migration V14 done.' AS status;
