-- ============================================================
-- Migration V10: Danh sách hệ thống + cây node (Module/Tính năng/Logic/Tính năng ẩn)
-- ============================================================
SET NAMES utf8mb4;

-- Bảng các hệ thống
CREATE TABLE IF NOT EXISTS systems (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    code        VARCHAR(50) NULL UNIQUE,
    description TEXT NULL,
    color       VARCHAR(20) NULL DEFAULT '#0d6efd',
    created_by  INT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_systems_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng gán BA (nhiều BA / 1 hệ thống)
CREATE TABLE IF NOT EXISTS system_assignees (
    system_id  INT NOT NULL,
    user_id    INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (system_id, user_id),
    CONSTRAINT fk_sa_system FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_user   FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng node cây cấu trúc
-- node_type: module / feature / logic / hidden
CREATE TABLE IF NOT EXISTS system_nodes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    system_id     INT NOT NULL,
    parent_id     INT NULL,
    node_type     ENUM('module','feature','logic','hidden') NOT NULL DEFAULT 'module',
    name          VARCHAR(255) NOT NULL,
    description   TEXT NULL,
    display_order INT NOT NULL DEFAULT 100,
    created_by    INT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sn_system FOREIGN KEY (system_id) REFERENCES systems(id)      ON DELETE CASCADE,
    CONSTRAINT fk_sn_parent FOREIGN KEY (parent_id) REFERENCES system_nodes(id) ON DELETE CASCADE,
    CONSTRAINT fk_sn_creator FOREIGN KEY (created_by) REFERENCES users(id)      ON DELETE SET NULL,
    INDEX idx_sn_system (system_id),
    INDEX idx_sn_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Migration V10 done.' AS status;
