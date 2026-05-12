-- ============================================================
-- Migration V8: Google Sheets Bot Sync settings
-- Lưu cấu hình kết nối Google Service Account để đẩy task sang sheet
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS bot_settings (
    id                INT PRIMARY KEY DEFAULT 1,
    sheet_url         VARCHAR(500) NULL,
    sheet_id          VARCHAR(255) NULL,
    bot_email         VARCHAR(255) NULL,
    credentials_path  VARCHAR(500) NOT NULL DEFAULT 'config/google-credentials.json',
    schedule_hour     TINYINT NOT NULL DEFAULT 23,
    schedule_minute   TINYINT NOT NULL DEFAULT 0,
    enabled           TINYINT(1) NOT NULL DEFAULT 1,
    last_sync_at      TIMESTAMP NULL,
    last_sync_status  VARCHAR(255) NULL,
    last_sync_error   TEXT NULL,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_bot_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed sẵn theo thông tin user đã cung cấp
INSERT INTO bot_settings (id, sheet_url, sheet_id, bot_email, credentials_path, schedule_hour, schedule_minute, enabled)
VALUES (
    1,
    'https://docs.google.com/spreadsheets/d/1N5YNAS-aykn9E_LNwqHCXEDq-hdFGAfJHg0lc2-saJc/edit?gid=0#gid=0',
    '1N5YNAS-aykn9E_LNwqHCXEDq-hdFGAfJHg0lc2-saJc',
    'bot-tool@innate-attic-467602-j6.iam.gserviceaccount.com',
    'config/google-credentials.json',
    23, 0, 1
)
ON DUPLICATE KEY UPDATE id = id;

SELECT 'Migration V8 done.' AS status;
