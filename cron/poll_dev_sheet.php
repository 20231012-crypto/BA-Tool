<?php
/**
 * cron/poll_dev_sheet.php — Background poller cho dev sheet.
 *
 * Chạy long-running, sleep 15s giữa mỗi poll. Bot poll tab tuần hiện tại
 * (+ các tab khác có task tracked trong DB) → cập nhật DB nếu Dev đổi
 * trạng thái trên sheet.
 *
 * SETUP trên Windows (chạy auto khi khởi động máy):
 *   1. Mở Task Scheduler (Win+R → taskschd.msc)
 *   2. Create Basic Task → Tên: "BA Tool - Dev Sheet Poller"
 *   3. Trigger: When the computer starts
 *   4. Action: Start a program
 *      Program: C:\xampp\php\php.exe
 *      Arguments: -f "C:\Vscode\VScode\BA.Tool\cron\poll_dev_sheet.php"
 *      Start in:  C:\Vscode\VScode\BA.Tool
 *   5. Trong Properties:
 *      - Run whether user is logged on or not
 *      - If task fails, restart every: 1 minute, attempt 99 times
 *
 * Manual run để test:
 *   cd C:\Vscode\VScode\BA.Tool
 *   C:\xampp\php\php.exe -f cron\poll_dev_sheet.php
 *   (Ctrl+C để dừng. Mỗi 15s sẽ in 1 dòng log.)
 */

set_time_limit(0);
ini_set('memory_limit', '256M');
date_default_timezone_set('Asia/Ho_Chi_Minh');

chdir(__DIR__ . '/..');
require_once 'config/db.php';
require_once 'services/DevSheetService.php';

const POLL_INTERVAL_SEC = 15;
const LOG_FILE = __DIR__ . '/poll_dev_sheet.log';

function poll_log($msg) {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    fwrite(STDOUT, $line);
    @file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

poll_log("=== Poller started · interval " . POLL_INTERVAL_SEC . "s ===");

while(true) {
    try {
        $db  = (new Database())->getConnection();
        $dss = new DevSheetService($db);
        $stats = $dss->pollChanges();
        poll_log(sprintf(
            "scanned=%d updated=%d skipped=%d errors=%d",
            $stats['scanned'] ?? 0,
            $stats['updated'] ?? 0,
            $stats['skipped'] ?? 0,
            count($stats['errors'] ?? [])
        ));
        if(!empty($stats['errors'])) {
            foreach($stats['errors'] as $e) poll_log("  ! $e");
        }
    } catch(Throwable $e) {
        poll_log("FATAL: " . $e->getMessage());
        // Tiếp tục loop — đừng để 1 lỗi giết poller
    }
    sleep(POLL_INTERVAL_SEC);
}
