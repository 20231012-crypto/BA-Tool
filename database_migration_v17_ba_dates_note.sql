-- Migration v17: BA estimate date range + assignee note
-- Run once against kinkin_ba_tool database

ALTER TABLE tasks
    ADD COLUMN ba_start_date DATE        NULL COMMENT 'Ngày BA ước tính bắt đầu (Lead/BA nội bộ, không hiện với BA nhân viên)' AFTER dev_planned_end,
    ADD COLUMN ba_end_date   DATE        NULL COMMENT 'Ngày BA ước tính kết thúc' AFTER ba_start_date,
    ADD COLUMN assignee_note TEXT        NULL COMMENT 'Ghi chú nội bộ của Lead khi phân công (hiển thị trong chi tiết task của BA nhân viên)' AFTER ba_end_date;
