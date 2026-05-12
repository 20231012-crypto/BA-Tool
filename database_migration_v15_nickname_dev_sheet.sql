-- ============================================================
-- Migration V15: Nickname + Dev sheet workflow infrastructure
-- - users.nickname (tên ngắn dùng ghi vào sheet, vd "Minh", "Quang")
-- - bot_settings.dev_sheet_id (sheet riêng cho Dev workflow)
-- - tasks.sheet_tab + tasks.sheet_row (track row task nằm dòng nào trong sheet)
-- ============================================================

USE ba_tool;
SET NAMES utf8mb4;

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

-- 1. users.nickname
CALL add_column_if_not_exists('users', 'nickname', 'VARCHAR(50) NULL COMMENT "Tên ngắn ghi vào sheet (vd Minh, Quang)"');

-- Backfill nickname cho user chưa có: lấy token cuối của full_name
UPDATE users
SET nickname = TRIM(SUBSTRING_INDEX(full_name, ' ', -1))
WHERE (nickname IS NULL OR nickname = '')
  AND full_name IS NOT NULL
  AND full_name <> '';

-- 2. bot_settings.dev_sheet_id (sheet riêng cho Dev workflow mới)
CALL add_column_if_not_exists('bot_settings', 'dev_sheet_id',  'VARCHAR(255) NULL COMMENT "Sheet ID riêng cho Dev workflow"');
CALL add_column_if_not_exists('bot_settings', 'dev_sheet_url', 'VARCHAR(500) NULL COMMENT "URL gốc Dev sheet"');

-- Seed sẵn dev sheet ID user đã cung cấp
UPDATE bot_settings
SET dev_sheet_id  = '1oITXUNVMPWhCQL2JaNgSrE2iRvSGTOxEBzW1XlM__3A',
    dev_sheet_url = 'https://docs.google.com/spreadsheets/d/1oITXUNVMPWhCQL2JaNgSrE2iRvSGTOxEBzW1XlM__3A/edit'
WHERE id = 1 AND (dev_sheet_id IS NULL OR dev_sheet_id = '');

-- 3. tasks.sheet_tab + tasks.sheet_row (vị trí của task trong dev sheet)
CALL add_column_if_not_exists('tasks', 'sheet_tab', 'VARCHAR(40) NULL COMMENT "Tên tab tuần task được ghi vào, vd 04/05 - 08/05"');
CALL add_column_if_not_exists('tasks', 'sheet_row', 'INT NULL COMMENT "Số dòng (1-based) của task trong tab tương ứng"');

CREATE INDEX idx_tasks_sheet_tab_row ON tasks(sheet_tab, sheet_row);

DROP PROCEDURE IF EXISTS add_column_if_not_exists;

SELECT 'Migration V15 done.' AS status,
       (SELECT COUNT(*) FROM users WHERE nickname IS NOT NULL AND nickname <> '') AS users_with_nickname,
       (SELECT dev_sheet_id FROM bot_settings WHERE id = 1) AS dev_sheet_configured;
