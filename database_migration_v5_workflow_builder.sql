-- ============================================================
-- Migration V5: Workflow Builder (1Office-like)
-- Cho phép Lead cấu hình quy trình tự động với các node và điều kiện
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS workflows (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(50) NOT NULL UNIQUE,
    name          VARCHAR(255) NOT NULL,
    group_name    VARCHAR(100) NULL,
    description   TEXT NULL,
    status        ENUM('active','inactive','draft') NOT NULL DEFAULT 'draft',
    definition    LONGTEXT NULL COMMENT 'JSON: nodes + edges',
    created_by    INT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_workflows_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed quy trình mẫu (BA chuẩn)
INSERT INTO workflows (code, name, group_name, description, status, definition)
VALUES (
    'WF_BA_DEFAULT',
    'Quy trình BA xử lý yêu cầu chuẩn',
    'BA',
    'Quy trình mặc định: tiếp nhận → phân công BA → BA xử lý → BA test → nghiệm thu',
    'active',
    '{"nodes":[{"id":"n1","type":"start","x":40,"y":40,"label":"Bắt đầu"},{"id":"n2","type":"task","x":220,"y":40,"label":"Phân công BA","config":{"status":"Todo - chờ xác nhận với Sếp","assignee_role":"lead"}},{"id":"n3","type":"task","x":400,"y":40,"label":"BA xử lý","config":{"status":"Dion - đang xử lý","assignee_role":"ba"}},{"id":"n4","type":"condition","x":580,"y":40,"label":"Cần dev?","config":{"field":"task_type","op":"contains","value":"Phát triển"}},{"id":"n5","type":"task","x":760,"y":-40,"label":"Giao Dev","config":{"status":"Dion - đang xử lý","assignee_role":"dev"}},{"id":"n6","type":"task","x":760,"y":120,"label":"BA test","config":{"status":"Dion - Chờ nghiệm thu","assignee_role":"ba"}},{"id":"n7","type":"approval","x":940,"y":40,"label":"Lead nghiệm thu","config":{"approver_role":"lead"}},{"id":"n8","type":"end","x":1120,"y":40,"label":"Hoàn thành","config":{"status":"Kinkin nghiệm thu"}}],"edges":[{"id":"e1","from":"n1","to":"n2"},{"id":"e2","from":"n2","to":"n3"},{"id":"e3","from":"n3","to":"n4"},{"id":"e4","from":"n4","to":"n5","label":"Có","branch":"true"},{"id":"e5","from":"n4","to":"n6","label":"Không","branch":"false"},{"id":"e6","from":"n5","to":"n6"},{"id":"e7","from":"n6","to":"n7"},{"id":"e8","from":"n7","to":"n8"}]}'
);

SELECT 'Migration V5 done.' AS status;
