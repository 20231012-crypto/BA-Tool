-- ============================================================
-- Migration V6: Seed 2 luồng quy trình mẫu được liên kết
-- 1) WF_BA_INTAKE  — BA tiếp nhận → duyệt → phân công dev (hoặc tự xử lý)
-- 2) WF_DEV_EXEC   — Dev nhận → code → BA test → nếu lỗi back, OK thì nghiệm thu
-- Liên kết: WF1 kết thúc khi BA bấm "Phân công Dev" → API assign_dev
--           kích hoạt event "dev_assigned" → WF2 tự chạy.
-- ============================================================

SET NAMES utf8mb4;

-- Xoá quy trình mẫu cũ để tránh nhầm lẫn (nếu tồn tại)
DELETE FROM workflows WHERE code IN ('WF_BA_DEFAULT', 'WF_BA_INTAKE', 'WF_DEV_EXEC');

-- ─── Workflow 1: WF_BA_INTAKE ────────────────────────────────
INSERT INTO workflows (code, name, group_name, description, status, definition)
VALUES (
    'WF_BA_INTAKE',
    '1. BA tiếp nhận & phân công',
    'BA',
    'BA tiếp nhận YC mới → Lead duyệt → kiểm tra "Cần dev?": có → phân công Dev (kích hoạt WF_DEV_EXEC); không → BA tự xử lý.',
    'active',
    '{"nodes":[{"id":"n1","type":"start","x":40,"y":80,"label":"YC mới được tạo","config":{"trigger":"task_created","filter_field":"","filter_op":"equals","filter_value":""}},{"id":"n2","type":"task","x":260,"y":80,"label":"BA tiếp nhận","config":{"status":"Todo - chờ xác nhận với Sếp","assignee_role":"ba"}},{"id":"n3","type":"approval","x":480,"y":80,"label":"Lead duyệt","config":{"approver_role":"lead"}},{"id":"n4","type":"condition","x":700,"y":80,"label":"Cần Dev?","config":{"field":"task_type","op":"contains","value":"Phát triển"}},{"id":"n5","type":"task","x":920,"y":-40,"label":"Phân công Dev","config":{"status":"Dion - đang xử lý","assignee_role":"ba"}},{"id":"n6","type":"notify","x":1140,"y":-40,"label":"Thông báo Dev","config":{"target_role":"dev","message":"Bạn có YC mới được giao. Vào dashboard để nhận việc."}},{"id":"n7","type":"end","x":1360,"y":-40,"label":"➡ Chuyển sang WF_DEV_EXEC","config":{"status":"Dion - đang xử lý"}},{"id":"n8","type":"task","x":920,"y":200,"label":"BA tự xử lý","config":{"status":"Dion - đang xử lý","assignee_role":"ba"}},{"id":"n9","type":"end","x":1140,"y":200,"label":"BA hoàn tất","config":{"status":"Dion - Chờ nghiệm thu"}}],"edges":[{"id":"e1","from":"n1","to":"n2"},{"id":"e2","from":"n2","to":"n3"},{"id":"e3","from":"n3","to":"n4"},{"id":"e4","from":"n4","to":"n5","branch":"true","label":"Có"},{"id":"e5","from":"n4","to":"n8","branch":"false","label":"Không"},{"id":"e6","from":"n5","to":"n6"},{"id":"e7","from":"n6","to":"n7"},{"id":"e8","from":"n8","to":"n9"}]}'
);

-- ─── Workflow 2: WF_DEV_EXEC ─────────────────────────────────
INSERT INTO workflows (code, name, group_name, description, status, definition)
VALUES (
    'WF_DEV_EXEC',
    '2. Dev thực thi & BA nghiệm thu',
    'Dev',
    'Tự kích hoạt khi Dev được giao việc (từ WF_BA_INTAKE) → Dev nhận → code → báo xong → BA test → nếu lỗi back về Dev sửa, không lỗi → Lead nghiệm thu → hoàn thành.',
    'active',
    '{"nodes":[{"id":"n1","type":"start","x":40,"y":120,"label":"Dev được giao","config":{"trigger":"dev_assigned","filter_field":"","filter_op":"equals","filter_value":""}},{"id":"n2","type":"task","x":240,"y":120,"label":"Dev nhận việc","config":{"status":"Dion - đang xử lý","assignee_role":"dev"}},{"id":"n3","type":"task","x":460,"y":120,"label":"Dev đang code","config":{"status":"Dion - đang xử lý","assignee_role":"dev"}},{"id":"n4","type":"task","x":680,"y":120,"label":"Dev báo xong","config":{"status":"Dion - đang xử lý","assignee_role":"dev"}},{"id":"n5","type":"task","x":900,"y":120,"label":"BA test","config":{"status":"Dion - Chờ nghiệm thu","assignee_role":"ba"}},{"id":"n6","type":"condition","x":1120,"y":120,"label":"Có lỗi?","config":{"field":"dev_status","op":"equals","value":"Cần sửa"}},{"id":"n7","type":"notify","x":1340,"y":-20,"label":"Báo Dev sửa","config":{"target_role":"dev","message":"BA yêu cầu sửa lại — vui lòng kiểm tra ghi chú và làm lại."}},{"id":"n8","type":"approval","x":1340,"y":260,"label":"Lead nghiệm thu","config":{"approver_role":"lead"}},{"id":"n9","type":"end","x":1560,"y":260,"label":"Hoàn tất","config":{"status":"Kinkin nghiệm thu"}}],"edges":[{"id":"e1","from":"n1","to":"n2"},{"id":"e2","from":"n2","to":"n3"},{"id":"e3","from":"n3","to":"n4"},{"id":"e4","from":"n4","to":"n5"},{"id":"e5","from":"n5","to":"n6"},{"id":"e6","from":"n6","to":"n7","branch":"true","label":"Có lỗi"},{"id":"e7","from":"n7","to":"n3","label":"↩ Quay lại sửa"},{"id":"e8","from":"n6","to":"n8","branch":"false","label":"OK"},{"id":"e9","from":"n8","to":"n9"}]}'
);

SELECT 'Migration V6: Seeded 2 linked workflows.' AS status;
SELECT id, code, name, group_name, status FROM workflows ORDER BY id;
