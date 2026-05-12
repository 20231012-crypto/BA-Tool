<?php
/**
 * Cron sync script — chạy bằng dòng lệnh (không qua Apache).
 *
 * Usage Windows Task Scheduler:
 *   Program/script: C:\xampp\php\php.exe
 *   Add arguments:  C:\xampp\htdocs\BA.Tool\cron\sync_to_sheet.php
 *   Start in:       C:\xampp\htdocs\BA.Tool
 *
 * Usage cron Linux/Mac:
 *   0 23 * * *  /usr/bin/php /path/to/BA.Tool/cron/sync_to_sheet.php
 */

if(php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Script này chỉ chạy bằng CLI để bảo mật. Để trigger từ web, dùng API endpoint trigger_sync.\n");
}

chdir(dirname(__DIR__));
require_once 'config/db.php';
require_once 'services/TaskSyncService.php';

$db = (new Database())->getConnection();
$svc = new TaskSyncService($db);

$startTs = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Bắt đầu sync...\n";

$result = $svc->runSync();
$elapsed = round(microtime(true) - $startTs, 2);

if($result['success']) {
    echo "[" . date('Y-m-d H:i:s') . "] ✓ {$result['message']} ({$elapsed}s)\n";
    exit(0);
} else {
    echo "[" . date('Y-m-d H:i:s') . "] ✗ Lỗi: {$result['message']} ({$elapsed}s)\n";
    exit(1);
}
