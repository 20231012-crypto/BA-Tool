-- Migration v18: Poller configuration fields
ALTER TABLE bot_settings ADD COLUMN IF NOT EXISTS poller_enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Bật/tắt dev sheet poller';
ALTER TABLE bot_settings ADD COLUMN IF NOT EXISTS poller_interval INT NOT NULL DEFAULT 15 COMMENT 'Interval poll dev sheet (giây)';
