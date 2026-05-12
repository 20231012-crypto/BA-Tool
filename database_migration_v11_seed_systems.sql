-- ============================================================
-- Migration V11: Seed 9 hệ thống chuẩn của KinKin + link tasks ↔ systems
-- ============================================================
SET NAMES utf8mb4;

-- Add columns liên kết task → system + module/feature node
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS system_id       INT NULL AFTER system_name;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS module_node_id  INT NULL AFTER module_id;
ALTER TABLE tasks ADD COLUMN IF NOT EXISTS feature_node_id INT NULL AFTER feature;

-- Seed các hệ thống chuẩn (idempotent qua INSERT IGNORE on code)
INSERT IGNORE INTO systems (name, code, description, color, created_by) VALUES
('quanly.vanchuyenkinkin',  'QLKK',     'Hệ thống quản lý vận chuyển KinKin (web)', '#dc3545', NULL),
('khoden.vanchuyenkinkin',  'KHODEN',   'Hệ thống kho đến', '#fd7e14', NULL),
('khodi.vanchuyenkinkin',   'KHODI',    'Hệ thống kho đi',  '#20c997', NULL),
('vanchuyenkinkin',         'VCKK',     'Trang chính vanchuyenkinkin', '#0d6efd', NULL),
('Winform kho đi',          'WFKD',     'Phần mềm Windows Form cho kho đi',  '#6f42c1', NULL),
('Winform kho đến',         'WFKDEN',   'Phần mềm Windows Form cho kho đến', '#6610f2', NULL),
('App Danka',               'DANKA',    'Ứng dụng mobile Danka', '#198754', NULL),
('App B2C',                 'B2C',      'Ứng dụng mobile B2C',   '#0dcaf0', NULL),
('Tool.vanchuyenkinkin',    'TOOL',     'Công cụ phụ trợ',       '#6c757d', NULL);

-- Backfill: với task đã tồn tại, link system_id từ system_name
UPDATE tasks t
JOIN systems s ON s.name = t.system_name
SET t.system_id = s.id
WHERE t.system_id IS NULL;

SELECT 'Migration V11 done.' AS status;
SELECT id, name, code FROM systems ORDER BY id;
