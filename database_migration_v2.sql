-- =====================================================================
-- BA.Tool Database Migration v2
-- Mục đích: Đồng bộ schema với Google Sheet chuẩn
-- Ngày: 2026-05-07
--
-- HƯỚNG DẪN:
--   1. BACKUP DB hiện tại trước khi chạy: mysqldump -u root ba_tool > backup.sql
--   2. Chạy file này: mysql -u root ba_tool < database_migration_v2.sql
--   3. Kiểm tra lại bằng: SHOW TABLES; DESC tasks;
--
-- LƯU Ý: File này IDEMPOTENT — có thể chạy lại nhiều lần không lỗi
-- =====================================================================

USE ba_tool;

-- ---------------------------------------------------------------------
-- PHASE 1.3: Tạo các bảng mới (modules, features, breaktasks)
-- ---------------------------------------------------------------------

-- Bảng catalog Module (Tab "Danh sách chức năng" trong Sheet)
CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed dữ liệu mẫu module (từ Sheet)
INSERT IGNORE INTO modules (name) VALUES
    ('Điều phối hàng'),
    ('Quản lý khối'),
    ('Quản lý kho hàng'),
    ('Vận đơn'),
    ('In tem'),
    ('Kiểm hóa'),
    ('Quản lý link đích'),
    ('Quản lý link nguồn'),
    ('Danh sách BOT'),
    ('Quản lý tài khoản');

-- Bảng features (Tính năng con của module)
CREATE TABLE IF NOT EXISTS features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_module_feature (module_id, name)
);

-- Bảng BreakTask (Chia nhỏ task)
CREATE TABLE IF NOT EXISTS breaktasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parent_task_id INT NOT NULL,
    sub_description TEXT NOT NULL,
    status VARCHAR(50) DEFAULT 'Todo - chờ xác nhận với Sếp',
    estimated_hours DECIMAL(5,2) NULL,
    actual_hours DECIMAL(5,2) NULL,
    due_date DATETIME NULL,
    completed_at DATETIME NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------------------
-- PHASE 1.1 + 1.2: Mở rộng bảng tasks (10 cột mới + đổi DATE → DATETIME)
-- ---------------------------------------------------------------------

-- Đổi DATE → DATETIME cho 3 cột thời gian (giữ nguyên dữ liệu cũ)
ALTER TABLE tasks
    MODIFY COLUMN start_date DATETIME NOT NULL,
    MODIFY COLUMN expected_end_date DATETIME NOT NULL,
    MODIFY COLUMN actual_end_date DATETIME NULL;

-- Thêm 10 cột mới (sử dụng IF NOT EXISTS pattern qua procedure để idempotent)
-- MySQL 8.0+ hỗ trợ IF NOT EXISTS cho ADD COLUMN; nếu version cũ hơn dùng procedure dưới

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

CALL add_column_if_not_exists('tasks', 'ma_yc',                  'VARCHAR(20) NULL UNIQUE COMMENT "Mã YC tự sinh: YC346, YC347..."');
CALL add_column_if_not_exists('tasks', 'module_id',              'INT NULL COMMENT "FK -> modules.id"');
CALL add_column_if_not_exists('tasks', 'feature',                'VARCHAR(255) NULL COMMENT "Tính năng cụ thể"');
CALL add_column_if_not_exists('tasks', 'ba_description',         'TEXT NULL COMMENT "Mô tả YC do BA viết lại"');
CALL add_column_if_not_exists('tasks', 'classification',         'VARCHAR(50) NULL COMMENT "Hệ thống - Thực hiện | Người dùng - Lỗi"');
CALL add_column_if_not_exists('tasks', 'ba_submission_date',     'DATE NULL COMMENT "Ngày BA đưa YC cho dev"');
CALL add_column_if_not_exists('tasks', 'actual_start_datetime',  'DATETIME NULL COMMENT "Bắt đầu code thực tế"');
CALL add_column_if_not_exists('tasks', 'acceptance_date',        'DATE NULL COMMENT "Ngày nghiệm thu"');
CALL add_column_if_not_exists('tasks', 'implementing_unit',      'VARCHAR(20) NULL COMMENT "DION | Kinkin"');
CALL add_column_if_not_exists('tasks', 'dev_actual_day',         'VARCHAR(20) NULL COMMENT "Thứ trong tuần Dev làm"');

-- Thêm FK cho module_id (chỉ tạo nếu chưa có)
SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tasks'
      AND CONSTRAINT_NAME = 'fk_tasks_module'
);
SET @sql := IF(@fk_exists = 0,
    'ALTER TABLE tasks ADD CONSTRAINT fk_tasks_module FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE SET NULL',
    'SELECT "FK fk_tasks_module already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- PHASE 1.4: Migrate dữ liệu enum cũ → giá trị mới (theo Sheet)
-- ---------------------------------------------------------------------

-- Migrate STATUS (chỉ update nếu giá trị cũ còn tồn tại)
UPDATE tasks SET status = 'Todo - chờ xác nhận với Sếp'  WHERE status = 'Chờ tiếp nhận';
UPDATE tasks SET status = 'Dion - đang xử lý'             WHERE status = 'Đang xử lý';
UPDATE tasks SET status = 'Dion - Chờ nghiệm thu'         WHERE status = 'Hoàn thành';
UPDATE tasks SET status = 'Kinkin nghiệm thu'             WHERE status = 'Đã nghiệm thu';
UPDATE tasks SET status = 'Huỷ'                           WHERE status = 'Tạm dừng/Hủy';

-- Migrate TASK_TYPE (loại yêu cầu)
UPDATE tasks SET task_type = 'Fix lỗi hệ thống'    WHERE task_type IN ('Fix bug', 'Sửa lỗi');
UPDATE tasks SET task_type = 'Nâng cấp hệ thống'   WHERE task_type IN ('Nâng cấp', 'Làm mới');
UPDATE tasks SET task_type = 'Thay đổi dữ liệu'    WHERE task_type IN ('Sửa dữ liệu', 'Cập nhật DL');
UPDATE tasks SET task_type = 'Lấy dữ liệu'         WHERE task_type IN ('Xuất dữ liệu', 'Lấy DL');
-- Các giá trị không khớp: giữ nguyên để admin xử lý thủ công

-- Migrate PRIORITY_REQUESTER (mức độ ưu tiên)
UPDATE tasks SET priority_requester = '4. Gấp - Quan trọng'              WHERE priority_requester IN ('Rất gấp', 'Urgent');
UPDATE tasks SET priority_requester = '3. Không gấp - Quan trọng'        WHERE priority_requester IN ('Gấp', 'High');
UPDATE tasks SET priority_requester = '2. Gấp - Không quan trọng'        WHERE priority_requester = 'Cao';
UPDATE tasks SET priority_requester = '1. Không gấp - Không quan trọng'  WHERE priority_requester IN ('Bình thường', 'Thấp');

UPDATE tasks SET priority_ba = '4. Gấp - Quan trọng'              WHERE priority_ba IN ('Rất gấp', 'Urgent');
UPDATE tasks SET priority_ba = '3. Không gấp - Quan trọng'        WHERE priority_ba IN ('Gấp', 'High');
UPDATE tasks SET priority_ba = '2. Gấp - Không quan trọng'        WHERE priority_ba = 'Cao';
UPDATE tasks SET priority_ba = '1. Không gấp - Không quan trọng'  WHERE priority_ba IN ('Bình thường', 'Thấp');

-- Migrate REQUESTER_DEPT (giá trị cũ → giá trị Sheet)
UPDATE tasks SET requester_dept = 'Kế toán'                 WHERE requester_dept IN ('Kế toán', 'Tài chính', 'Kế toán / Tài chính');
UPDATE tasks SET requester_dept = 'Phòng kinh doanh'        WHERE requester_dept IN ('Kinh doanh', 'Sales');
UPDATE tasks SET requester_dept = 'CSKH'                    WHERE requester_dept IN ('Chăm sóc khách hàng', 'Customer Service');
-- Các giá trị "Nhân sự", "Marketing", "Vận hành", "Khác" giữ nguyên (Sheet không có) — admin tự xử lý

-- Sinh Mã YC cho các task hiện có (theo thứ tự ID, bắt đầu YC001)
SET @yc_counter := 0;
UPDATE tasks
SET ma_yc = CONCAT('YC', LPAD(@yc_counter := @yc_counter + 1, 3, '0'))
WHERE ma_yc IS NULL
ORDER BY id ASC;

-- ---------------------------------------------------------------------
-- VERIFY: Kiểm tra schema sau migration
-- ---------------------------------------------------------------------
SELECT 'modules count' AS metric, COUNT(*) AS value FROM modules
UNION ALL SELECT 'tasks count', COUNT(*) FROM tasks
UNION ALL SELECT 'breaktasks count', COUNT(*) FROM breaktasks
UNION ALL SELECT 'tasks with ma_yc', COUNT(*) FROM tasks WHERE ma_yc IS NOT NULL;
