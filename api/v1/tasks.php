<?php
/**
 * REST API — Tasks
 *
 * Auth: Header "Authorization: Bearer <token>"
 *
 * GET    /api/v1/tasks.php              → Danh sách tasks
 * GET    /api/v1/tasks.php?id=123       → Chi tiết 1 task
 * GET    /api/v1/tasks.php?ma_yc=YC001  → Tìm theo mã YC
 * POST   /api/v1/tasks.php              → Tạo task mới
 * PUT    /api/v1/tasks.php?id=123       → Cập nhật task
 * PATCH  /api/v1/tasks.php?id=123       → Cập nhật task (alias PUT)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/ApiKey.php';
require_once __DIR__ . '/../../models/Task.php';

date_default_timezone_set('Asia/Ho_Chi_Minh');

$database = new Database();
$db = $database->getConnection();
if (!$db) { jsonError(500, 'Database connection failed'); }

$method = $_SERVER['REQUEST_METHOD'];

// ── Auth ──────────────────────────────────────────────────────
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
    $token = trim($m[1]);
} else {
    $token = $_GET['token'] ?? '';
}

if (!$token) { jsonError(401, 'Missing API token. Use header: Authorization: Bearer <token>'); }

$apiKey = new ApiKey($db);
$key = $apiKey->authenticate($token, $method);
if (!$key) { jsonError(403, 'Invalid or inactive API token, or method not allowed for this key'); }

// ── Route ─────────────────────────────────────────────────────
$task = new Task($db);

switch ($method) {
    case 'GET':    handleGet($task, $db); break;
    case 'POST':   handlePost($task, $db); break;
    case 'PUT':
    case 'PATCH':  handlePut($task, $db); break;
    default:       jsonError(405, "Method $method not supported");
}

// ── GET ───────────────────────────────────────────────────────
function handleGet($task, $db) {
    // Single task by id or ma_yc
    if (!empty($_GET['id'])) {
        $row = $task->getById(intval($_GET['id']));
        if (!$row) jsonError(404, 'Task not found');
        echo json_encode(['success' => true, 'data' => formatTask($row)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!empty($_GET['ma_yc'])) {
        $stmt = $db->prepare("SELECT * FROM tasks WHERE ma_yc = ?");
        $stmt->execute([trim($_GET['ma_yc'])]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) jsonError(404, 'Task not found');
        echo json_encode(['success' => true, 'data' => formatTask($row)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // List with optional filters
    $where = []; $params = [];

    if (!empty($_GET['status'])) {
        $where[] = "t.status = ?"; $params[] = $_GET['status'];
    }
    if (!empty($_GET['assignee_id'])) {
        $where[] = "t.assignee_id = ?"; $params[] = intval($_GET['assignee_id']);
    }
    if (!empty($_GET['system_name'])) {
        $where[] = "t.system_name LIKE ?"; $params[] = '%' . $_GET['system_name'] . '%';
    }
    if (!empty($_GET['dev_status'])) {
        $where[] = "t.dev_status = ?"; $params[] = $_GET['dev_status'];
    }

    $limit  = max(1, min(500, intval($_GET['limit'] ?? 100)));
    $offset = max(0, intval($_GET['offset'] ?? 0));

    $sql = "SELECT t.*, u.full_name AS assignee_name, d.full_name AS dev_name
            FROM tasks t
            LEFT JOIN users u ON t.assignee_id = u.id
            LEFT JOIN users d ON t.dev_id = d.id";
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Total count
    $countSql = "SELECT COUNT(*) FROM tasks t";
    if ($where) $countSql .= " WHERE " . implode(' AND ', $where);
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'total'   => (int)$total,
        'limit'   => $limit,
        'offset'  => $offset,
        'data'    => array_map('formatTask', $rows),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── POST ──────────────────────────────────────────────────────
function handlePost($task, $db) {
    $input = getJsonInput();

    $required = ['requester_name', 'requester_dept', 'system_name', 'description', 'task_type', 'priority_requester', 'start_date', 'expected_end_date'];
    foreach ($required as $f) {
        if (empty($input[$f])) jsonError(400, "Missing required field: $f");
    }

    // Convert date format
    if (strlen($input['start_date']) === 10) $input['start_date'] .= ' 08:00:00';
    if (strlen($input['expected_end_date']) === 10) $input['expected_end_date'] .= ' 17:00:00';

    $input['attachment_url'] = $input['attachment_url'] ?? null;

    $maYc = $task->create($input);
    if (!$maYc) jsonError(500, 'Failed to create task');

    // Auto-link system_id
    if (!empty($input['system_name'])) {
        $stmt = $db->prepare("UPDATE tasks t JOIN systems s ON s.name COLLATE utf8mb4_unicode_ci = t.system_name COLLATE utf8mb4_unicode_ci SET t.system_id = s.id WHERE t.ma_yc = ?");
        $stmt->execute([$maYc]);
    }

    // Webhook sync
    try {
        require_once __DIR__ . '/../../services/BASheetWebhook.php';
        (new BASheetWebhook($db))->syncTask($db->lastInsertId());
    } catch (Throwable $e) { /* silent */ }

    $created = $db->prepare("SELECT * FROM tasks WHERE ma_yc = ?");
    $created->execute([$maYc]);
    $row = $created->fetch(PDO::FETCH_ASSOC);

    http_response_code(201);
    echo json_encode(['success' => true, 'ma_yc' => $maYc, 'data' => formatTask($row)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── PUT/PATCH ─────────────────────────────────────────────────
function handlePut($task, $db) {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) jsonError(400, 'Missing query param: id');

    $existing = $task->getById($id);
    if (!$existing) jsonError(404, 'Task not found');

    $input = getJsonInput();
    $allowed = [
        'status', 'priority_ba', 'office_link', 'assignee_id', 'dev_id',
        'dev_status', 'dev_notes', 'dev_start_at', 'dev_end_at', 'dev_deadline',
        'dev_planned_start', 'dev_planned_end', 'implementing_unit', 'classification',
        'ba_description', 'acceptance_date', 'actual_end_date', 'actual_start_datetime',
        'test_status', 'test_date', 'tester_id',
    ];

    $data = array_intersect_key($input, array_flip($allowed));
    if (empty($data)) jsonError(400, 'No valid fields to update');

    $ok = $task->update($id, $data, 'lead');
    if (!$ok) jsonError(500, 'Failed to update task');

    // Webhook sync
    try {
        require_once __DIR__ . '/../../services/BASheetWebhook.php';
        (new BASheetWebhook($db))->syncTask($id);
    } catch (Throwable $e) { /* silent */ }

    $updated = $task->getById($id);
    echo json_encode(['success' => true, 'data' => formatTask($updated)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Helpers ───────────────────────────────────────────────────
function jsonError($code, $message) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!$data) jsonError(400, 'Invalid JSON body');
    return $data;
}

function formatTask($row) {
    if (!$row) return null;
    // Clean up — return relevant fields
    $fields = [
        'id', 'ma_yc', 'requester_name', 'requester_dept', 'system_name',
        'description', 'task_type', 'priority_requester', 'priority_ba',
        'status', 'start_date', 'expected_end_date', 'actual_end_date',
        'assignee_id', 'assignee_name', 'dev_id', 'dev_name',
        'dev_status', 'dev_notes', 'dev_start_at', 'dev_end_at',
        'dev_deadline', 'dev_planned_start', 'dev_planned_end',
        'implementing_unit', 'classification', 'office_link',
        'acceptance_date', 'test_status', 'test_date',
        'created_at',
    ];
    $out = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $row)) $out[$f] = $row[$f];
    }
    return $out;
}
