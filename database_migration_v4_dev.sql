-- ============================================================
-- Migration V4: Dev Management System
-- Thêm các cột quản lý dev vào bảng tasks
-- ============================================================

SET NAMES utf8mb4;

DROP PROCEDURE IF EXISTS migrate_v4_dev;

DELIMITER $$
CREATE PROCEDURE migrate_v4_dev()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'dev_id'
    ) THEN
        ALTER TABLE tasks
            ADD COLUMN dev_id              INT NULL AFTER assignee_id,
            ADD COLUMN dev_status          VARCHAR(50) NULL AFTER dev_id,
            ADD COLUMN dev_notes           TEXT NULL AFTER dev_status,
            ADD COLUMN dev_attachment_url  VARCHAR(500) NULL AFTER dev_notes,
            ADD COLUMN dev_start_at        DATETIME NULL AFTER dev_attachment_url,
            ADD COLUMN dev_end_at          DATETIME NULL AFTER dev_start_at;

        ALTER TABLE tasks
            ADD CONSTRAINT fk_tasks_dev_id
            FOREIGN KEY (dev_id) REFERENCES users(id) ON DELETE SET NULL;
    END IF;
END$$
DELIMITER ;

CALL migrate_v4_dev();
DROP PROCEDURE IF EXISTS migrate_v4_dev;

-- Mở rộng ENUM role để thêm 'dev'
ALTER TABLE users MODIFY COLUMN role ENUM('lead','ba','dev') NOT NULL DEFAULT 'ba';

-- Seed demo dev user (bỏ qua nếu đã tồn tại)
INSERT IGNORE INTO users (username, password, full_name, role)
VALUES
    ('dev1', '$2y$10$placeholder_will_be_replaced', 'Nguyễn Dev Hùng', 'dev'),
    ('dev2', '$2y$10$placeholder_will_be_replaced', 'Trần Dev Minh', 'dev');

SELECT 'Migration V4 done.' AS status;
