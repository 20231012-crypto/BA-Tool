<?php
/**
 * auto_sync_daemon.php — Background daemon tự đồng bộ BA Sheet theo lịch.
 *
 * Đọc cấu hình (giờ, bật/tắt) từ DB mỗi phút.
 * Khi đến giờ cấu hình → chạy sync 1 lần/ngày.
 *
 * Chạy: C:\xampp\php\php.exe -f "C:\xampp\htdocs\BA.Tool\cron\auto_sync_daemon.php"
 */

set_time_limit(0);
ini_set('memory_limit', '256M');
date_default_timezone_set('Asia/Ho_Chi_Minh');

chdir(__DIR__ . '/..');
require_once 'config/db.php';
require_once 'models/BotSettings.php';
require_once 'services/TaskSyncService.php';

const CHECK_INTERVAL = 60; // Kiểm tra mỗi 60 giây
const LOG_FILE = __DIR__ . '/auto_sync.log';

function sync_log($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    fwrite(STDOUT, $line);
    @file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

$lastSyncDate = ''; // Track ngày đã sync để không sync trùng

sync_log("=== Auto-sync daemon started ===");

while (true) {
    try {
        $db  = (new Database())->getConnection();
        $bs  = new BotSettings($db);
        $cfg = $bs->get();

        $enabled = (int)($cfg['enabled'] ?? 0);
        $hour    = (int)($cfg['schedule_hour'] ?? 23);
        $minute  = (int)($cfg['schedule_minute'] ?? 0);
        $today   = date('Y-m-d');
        $nowH    = (int)date('H');
        $nowM    = (int)date('i');

        if ($enabled && $nowH === $hour && $nowM === $minute && $lastSyncDate !== $today) {
            sync_log("Triggered! Bắt đầu sync BA Sheet...");
            $svc = new TaskSyncService($db);
            $result = $svc->runSync();
            if ($result['success']) {
                sync_log("Sync thành công: " . ($result['message'] ?? ''));
            } else {
                sync_log("Sync thất bại: " . ($result['message'] ?? 'Unknown'));
            }
            $lastSyncDate = $today;
        }
    } catch (Throwable $e) {
        sync_log("ERROR: " . $e->getMessage());
    }

    sleep(CHECK_INTERVAL);
}
