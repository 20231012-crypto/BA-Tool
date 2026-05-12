-- ============================================================
-- Migration V13: User Groups (e.g. "Nhóm DION", "Nhóm Kinkin")
-- Cho phép gom nhân sự thành nhóm và lọc theo nhóm khi gán Dev.
-- ============================================================

USE ba_tool;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS user_groups (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    color       VARCHAR(20)  NOT NULL DEFAULT '#0d6efd',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_group_members (
    group_id    INT NOT NULL,
    user_id     INT NOT NULL,
    added_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, user_id),
    FOREIGN KEY (group_id) REFERENCES user_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed 2 nhóm mặc định khớp với "Đơn vị thực hiện"
INSERT IGNORE INTO user_groups (name, description, color) VALUES
    ('DION',   'Đội phát triển DION',   '#dc3545'),
    ('Kinkin', 'Đội phát triển Kinkin', '#0d6efd');

SELECT 'Migration V13 done.' AS status;
