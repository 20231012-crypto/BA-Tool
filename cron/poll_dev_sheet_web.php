<?php
/**
 * HTTP endpoint để poll dev sheet — dùng cho Railway cron hoặc external cron.
 * Gọi: GET /cron/poll_dev_sheet_web.php?key=SECRET
 * Chạy 1 lần poll rồi trả JSON kết quả.
 */
set_time_limit(120);
date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Content-Type: application/json; charset=utf-8');

// Simple auth key — chống gọi trái phép
$secret = getenv('CRON_SECRET') ?: 'batool_cron_2026';
if (($_GET['key'] ?? '') !== $secret) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid key']);
    exit;
}

chdir(__DIR__ . '/..');
require_once 'config/db.php';
require_once 'services/DevSheetService.php';

try {
    $db  = (new Database())->getConnection();
    if (!$db) throw new Exception('DB connection failed');
    $dss = new DevSheetService($db);
    $stats = $dss->pollChanges();
    echo json_encode([
        'success' => true,
        'time'    => date('Y-m-d H:i:s'),
        'stats'   => $stats
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
