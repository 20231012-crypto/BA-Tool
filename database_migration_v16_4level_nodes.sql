-- ============================================================
-- Migration V16: Track 4 cấp module → tính năng → logic → tính năng ẩn
-- (đã có module_node_id + feature_node_id từ v10, thêm 2 cấp còn lại)
-- ============================================================

USE ba_tool;
SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS add_column_if_not_exists;
DELIMITER $$
CREATE PROCEDURE add_column_if_not_exists(IN tbl VARCHAR(64), IN col VARCHAR(64), IN col_def TEXT)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', col, ' ', col_def);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END $$
DELIMITER ;

CALL add_column_if_not_exists('tasks', 'logic_node_id',  'INT NULL COMMENT "FK system_nodes (cấp 3 - logic)"');
CALL add_column_if_not_exists('tasks', 'hidden_node_id', 'INT NULL COMMENT "FK system_nodes (cấp 4 - tính năng ẩn)"');

-- Add FKs nếu chưa có
SET @fk1 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND CONSTRAINT_NAME='fk_tasks_logic_node');
SET @sql := IF(@fk1=0, 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_logic_node FOREIGN KEY (logic_node_id) REFERENCES system_nodes(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @fk2 := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tasks' AND CONSTRAINT_NAME='fk_tasks_hidden_node');
SET @sql := IF(@fk2=0, 'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_hidden_node FOREIGN KEY (hidden_node_id) REFERENCES system_nodes(id) ON DELETE SET NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

DROP PROCEDURE IF EXISTS add_column_if_not_exists;

SELECT 'Migration V16 done.' AS status;
