-- ============================================================
-- Migration V7: Form Configuration (Google Forms-like editor)
-- Cho phép Lead BA cấu hình form công khai: label, options dropdown,
-- required, hidden, thứ tự hiển thị. Cũng có thể thêm field tuỳ chỉnh.
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS form_settings (
    id            INT PRIMARY KEY DEFAULT 1,
    title         VARCHAR(255) NOT NULL DEFAULT 'Yêu cầu hỗ trợ hệ thống',
    description   TEXT NULL,
    success_msg   TEXT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_form_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS form_fields (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    field_key     VARCHAR(50) NOT NULL UNIQUE,
    label         VARCHAR(255) NOT NULL,
    field_type    ENUM('text','textarea','dropdown','date','file','section') NOT NULL DEFAULT 'text',
    required      TINYINT(1) NOT NULL DEFAULT 0,
    placeholder   VARCHAR(255) NULL,
    options_json  TEXT NULL COMMENT 'JSON array of strings for dropdown',
    display_order INT NOT NULL DEFAULT 100,
    is_visible    TINYINT(1) NOT NULL DEFAULT 1,
    is_builtin    TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = mapped to tasks column, không xoá được',
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cột JSON lưu giá trị custom field (nếu user thêm field mới ngoài builtin)
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS custom_data LONGTEXT NULL;

-- Seed form settings (1 row duy nhất)
INSERT INTO form_settings (id, title, description, success_msg)
VALUES (
    1,
    'Yêu cầu hỗ trợ hệ thống',
    'Form này phục vụ cho việc log lỗi, nâng cấp hệ thống của KinKin',
    'Cảm ơn bạn đã gửi yêu cầu! Đội BA sẽ tiếp nhận và phản hồi trong thời gian sớm nhất.'
)
ON DUPLICATE KEY UPDATE id = id;

-- Seed các field builtin từ form hiện tại
INSERT INTO form_fields (field_key, label, field_type, required, placeholder, options_json, display_order, is_visible, is_builtin) VALUES
('requester_name', 'Tên người yêu cầu', 'text', 1, 'Nguyễn Văn A', NULL, 10, 1, 1),
('requester_dept', 'Phòng ban', 'dropdown', 1, NULL,
 '["Ban lãnh đạo - Giám đốc","Phòng kinh doanh Hà Nội 1","Phòng kinh doanh Hà Nội 2","Phòng kinh doanh Hồ Chí Minh","CSKH","Kho Hồ Chí Minh","Kho Hà Nội","Kho Nhật Bản","Data","IT","Kế toán"]',
 20, 1, 1),
('system_name', 'Tên hệ thống', 'dropdown', 1, NULL,
 '["quanly.vanchuyenkinkin","khoden.vanchuyenkinkin","khodi.vanchuyenkinkin","vanchuyenkinkin","Winform kho đi","Winform kho đến","App Danka","App B2C","Tool.vanchuyenkinkin"]',
 30, 1, 1),
('task_type', 'Loại yêu cầu', 'dropdown', 1, NULL,
 '["Fix lỗi hệ thống","Nâng cấp hệ thống","Thay đổi dữ liệu","Lấy dữ liệu"]',
 40, 1, 1),
('priority_requester', 'Mức độ ưu tiên mong muốn', 'dropdown', 1, NULL,
 '["4. Gấp - Quan trọng","3. Không gấp - Quan trọng","2. Gấp - Không quan trọng","1. Không gấp - Không quan trọng"]',
 50, 1, 1),
('description', 'Mô tả yêu cầu chi tiết', 'textarea', 1, 'Mô tả rõ yêu cầu của bạn là gì, cho ai dùng, giải quyết vấn đề gì...', NULL, 60, 1, 1),
('start_date', 'Ngày bắt đầu dự kiến', 'date', 1, NULL, NULL, 70, 1, 1),
('expected_end_date', 'Ngày hoàn thành mong muốn', 'date', 1, NULL, NULL, 80, 1, 1),
('attachment', 'File tài liệu đính kèm', 'file', 0, 'Hình ảnh màn hình, file Excel mô tả...', NULL, 90, 1, 1)
ON DUPLICATE KEY UPDATE field_key = field_key;

SELECT 'Migration V7 done.' AS status;
