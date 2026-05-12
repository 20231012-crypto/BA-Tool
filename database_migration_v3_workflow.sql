-- =====================================================================
-- BA.Tool Migration v3 — Workflow + Notifications
-- Yêu cầu: Đã chạy database_migration_v2.sql trước đó
-- Ngày: 2026-05-07
-- =====================================================================

USE ba_tool;

-- ---------------------------------------------------------------------
-- Bảng notifications — Thông báo workflow giữa Lead ↔ BA
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL              COMMENT 'Người NHẬN thông báo',
    from_user_id INT NULL             COMMENT 'Người TẠO thông báo (NULL = system)',
    task_id INT NULL                  COMMENT 'Liên kết tới task',
    type VARCHAR(30) NOT NULL DEFAULT 'next_step'
                                      COMMENT 'next_step | assigned | new_task | cancel',
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    INDEX idx_user_unread (user_id, is_read, created_at DESC),
    INDEX idx_task (task_id),
    FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (task_id)      REFERENCES tasks(id) ON DELETE CASCADE
);

SELECT 'notifications table' AS object, COUNT(*) AS rows_count FROM notifications;
