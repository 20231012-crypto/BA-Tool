<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once '../config/db.php';
require_once '../models/Task.php';
require_once '../models/User.php';
require_once '../models/Notification.php';
require_once '../models/Workflow.php';
require_once '../models/FormConfig.php';
require_once '../models/BotSettings.php';
require_once '../services/TaskSyncService.php';
require_once '../models/SystemRegistry.php';
require_once '../models/UserGroup.php';
require_once '../services/BASheetWebhook.php';
require_once '../models/ApiKey.php';

$database = new Database();
$db = $database->getConnection();

// Helper: gửi webhook khi task thay đổi (fire-and-forget)
function webhookSyncTask($db, $taskId) {
    try { (new BASheetWebhook($db))->syncTask($taskId); } catch(Throwable $e) { /* silent */ }
}

// ── Notification helper ────────────────────────────────────────────────────
function notifyOnTransition($db, $task, $oldStatus, $newStatus, $direction) {
    $notif    = new Notification($db);
    $actorId  = $_SESSION['user_id'];
    $actorRole= $_SESSION['role'];
    $actorName= $_SESSION['full_name'] ?? 'Ai đó';
    $maYc     = $task['ma_yc'] ?? ('#' . $task['id']);
    $sysName  = $task['system_name'] ?? '';

    $verb  = $direction === 'cancel' ? 'huỷ' : ($direction === 'reopen' ? 'mở lại' : 'chuyển bước');
    $title = "[$maYc] $actorName $verb công việc";
    $msg   = "$actorName vừa $verb \"$sysName\" ($maYc): $oldStatus → $newStatus";

    if($actorRole === 'ba') {
        $notif->createBulk($notif->getAllLeadIds(), $title, $msg, $task['id'], $actorId, 'next_step');
    } elseif($actorRole === 'lead') {
        $assigneeId = $task['assignee_id'] ?? null;
        if($assigneeId && (int)$assigneeId !== (int)$actorId) {
            $notif->create($assigneeId, $title, $msg, $task['id'], $actorId, 'next_step');
        }
        // Notify dev khi task vào giai đoạn có dev
        $devId = $task['dev_id'] ?? null;
        if($devId && (int)$devId !== (int)$actorId) {
            $notif->create($devId, $title, $msg, $task['id'], $actorId, 'next_step');
        }
    }
}

function notifyDevTransition($db, $task, $devStatus, $actorId, $actorName) {
    $notif   = new Notification($db);
    $maYc    = $task['ma_yc'] ?? ('#' . $task['id']);
    $sysName = $task['system_name'] ?? '';

    $verbMap = [
        'Dev đang làm'  => 'đã nhận và bắt đầu làm',
        'Dev đã xong'   => 'đã hoàn thành',
        'Cần sửa'       => 'đánh dấu Cần sửa cho',
    ];
    $verb  = $verbMap[$devStatus] ?? 'cập nhật';
    $title = "[$maYc] Dev $verb task";
    $msg   = "Dev $actorName $verb task \"$sysName\" ($maYc). Trạng thái: $devStatus";

    // Thông báo cho BA phụ trách + tất cả Lead
    $targets = $notif->getAllLeadIds();
    $baId = $task['assignee_id'] ?? null;
    if($baId && !in_array($baId, $targets)) $targets[] = $baId;

    $notif->createBulk($targets, $title, $msg, $task['id'], $actorId, 'dev_update');
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ══════════════════════════════════════════════════════════════════
// GET
// ══════════════════════════════════════════════════════════════════
if($_SERVER['REQUEST_METHOD'] === 'GET') {

    if($action === 'get_tasks') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $task = new Task($db);
        $role = $_SESSION['role'];
        // dev role không có dashboard riêng — TaskController đã chặn login,
        // nhưng để safe vẫn trả empty nếu lọt qua đây
        if($role === 'lead') {
            $stmt = $task->getAll();
        } elseif($role === 'ba') {
            $stmt = $task->getByAssignee($_SESSION['user_id']);
        } else {
            echo json_encode([]); exit;
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if($action === 'get_task_detail') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $task = new Task($db);
        $row  = $task->getById(intval($_GET['task_id']));
        echo json_encode($row ?: ['error'=>'not_found']);
        exit;
    }

    if($action === 'get_users') {
        if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['lead','ba'])) { echo json_encode(['error'=>'unauth']); exit; }
        $user = new User($db);
        echo json_encode($user->getAllWithPerformance()->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if($action === 'get_ba_list') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lead') { echo json_encode(['error'=>'unauth']); exit; }
        $user = new User($db);
        echo json_encode($user->getAllBA()->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if($action === 'get_dev_list') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $user = new User($db);
        $devs = $user->getAllDev()->fetchAll(PDO::FETCH_ASSOC);
        // Lọc theo nhóm nếu có
        $groupId = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
        if($groupId > 0) {
            $stmt = $db->prepare("SELECT user_id FROM user_group_members WHERE group_id = ?");
            $stmt->execute([$groupId]);
            $allowed = array_flip(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
            $devs = array_values(array_filter($devs, fn($d) => isset($allowed[(int)$d['id']])));
        }
        echo json_encode($devs);
        exit;
    }

    // ── User Groups ─────────────────────────────────────────────
    if($action === 'list_user_groups') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $ug = new UserGroup($db);
        echo json_encode($ug->listAll());
        exit;
    }
    if($action === 'list_users_for_group') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lead') { echo json_encode(['error'=>'unauth']); exit; }
        $ug = new UserGroup($db);
        $gid = intval($_GET['group_id'] ?? 0);
        $role = $_GET['role'] ?? null;
        echo json_encode($ug->listUsersForGroup($gid, $role));
        exit;
    }


    if($action === 'get_modules') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $stmt = $db->query("SELECT id, name FROM modules WHERE is_active = 1 ORDER BY name ASC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if($action === 'get_form_log') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $stmt = $db->query("
            SELECT t.id, t.ma_yc, t.created_at, t.requester_name, t.requester_dept,
                   t.system_name, t.task_type, t.priority_requester, t.status,
                   t.expected_end_date, t.attachment_url,
                   u.full_name AS assignee_name
            FROM tasks t
            LEFT JOIN users u ON t.assignee_id = u.id
            ORDER BY t.created_at DESC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if($action === 'get_notifications') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $notif = new Notification($db);
        echo json_encode([
            'unread_count' => $notif->countUnread($_SESSION['user_id']),
            'items'        => $notif->getForUser($_SESSION['user_id'], 30),
        ]);
        exit;
    }

    // ─── Workflow Builder (Lead only) ──────────────────────────────
    if($action === 'get_workflows') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lead') { echo json_encode(['error'=>'unauth']); exit; }
        $wf = new Workflow($db);
        echo json_encode($wf->getAll());
        exit;
    }
    if($action === 'get_workflow') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lead') { echo json_encode(['error'=>'unauth']); exit; }
        $wf = new Workflow($db);
        $row = $wf->getById(intval($_GET['id']));
        echo json_encode($row ?: ['error' => 'not_found']);
        exit;
    }

    // ─── Systems (Lead xem hết, BA xem hệ thống được gán) ───────────
    if($action === 'get_systems') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $sr = new SystemRegistry($db);
        echo json_encode($sr->listSystems($_SESSION['user_id'], $_SESSION['role']));
        exit;
    }
    // Lấy nodes theo system_id + filter type — dùng cho dropdown trong start-coding modal
    if($action === 'get_system_nodes_for_task') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $sysId = intval($_GET['system_id'] ?? 0);
        if(!$sysId) { echo json_encode([]); exit; }
        $stmt = $db->prepare("SELECT id, parent_id, node_type, name FROM system_nodes WHERE system_id = ? ORDER BY display_order, id");
        $stmt->execute([$sysId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if($action === 'get_system_detail') {
        if(!isset($_SESSION['user_id'])) { echo json_encode(['error'=>'unauth']); exit; }
        $sr = new SystemRegistry($db);
        $sysId = intval($_GET['id']);
        $sys = $sr->getSystem($sysId);
        if(!$sys) { echo json_encode(['error'=>'not_found']); exit; }
        $canEdit = $sr->userCanEdit($sysId, $_SESSION['user_id'], $_SESSION['role']);
        echo json_encode([
            'system'    => $sys,
            'assignees' => $sr->getAssignees($sysId),
            'nodes'     => $sr->getTree($sysId),
            'can_edit'  => $canEdit,
            'is_lead'   => $_SESSION['role'] === 'lead'
        ]);
        exit;
    }

    // ─── Bot Sync settings (Lead only) ───────────────────────────────
    if($action === 'get_bot_settings') {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'lead') { echo json_encode(['error'=>'unauth']); exit; }
        $bs = new BotSettings($db);
        $cfg = $bs->get();
        // Đính kèm thông tin file credentials có tồn tại không
        $envCred = getenv('GOOGLE_CREDENTIALS_JSON');
        $credPath = __DIR__ . '/../' . ($cfg['credentials_path'] ?: 'config/google-credentials.json');
        $cfg['credentials_exists'] = ($envCred || file_exists($credPath)) ? 1 : 0;
        if($cfg['credentials_exists']) {
            $info = $envCred ? json_decode($envCred, true) : json_decode(@file_get_contents($credPath), true);
            $cfg['credentials_email'] = $info['client_email'] ?? null;
            $cfg['credentials_project'] = $info['project_id'] ?? null;
        }
        echo json_encode($cfg);
        exit;
    }

    // ─── Form Config (public read; lead full edit) ───────────────────
    if($action === 'get_form_config') {
        $fc = new FormConfig($db);
        $visibleOnly = isset($_GET['visible_only']) && $_GET['visible_only'] === '1';
        echo json_encode([
            'settings' => $fc->getSettings(),
            'fields'   => $fc->getAllFields($visibleOnly)
        ]);
        exit;
    }
}

// ══════════════════════════════════════════════════════════════════
// POST
// ══════════════════════════════════════════════════════════════════
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(!isset($_SESSION['user_id'])) {
        echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit;
    }

    // ── TASK: Assign BA ──────────────────────────────────────────
    // ── TASK: Delete (Lead only) ─────────────────────────────────
    if($action === 'delete_task') {
        if($_SESSION['role'] !== 'lead') {
            echo json_encode(['success' => false, 'message' => 'Chỉ Lead có quyền xoá YC']); exit;
        }
        $id = intval($_POST['task_id'] ?? 0);
        if($id <= 0) { echo json_encode(['success' => false, 'message' => 'task_id không hợp lệ']); exit; }
        $task = new Task($db);
        $row  = $task->getById($id);
        if(!$row) { echo json_encode(['success' => false, 'message' => 'Không tìm thấy YC']); exit; }
        $ok = $task->delete($id);
        echo json_encode([
            'success' => $ok,
            'message' => $ok ? "Đã xoá {$row['ma_yc']}" : 'Lỗi khi xoá',
            'ma_yc'   => $row['ma_yc'],
        ]);
        exit;
    }

    if($action === 'assign_task' && $_SESSION['role'] === 'lead') {
        $task  = new Task($db);
        $id    = intval($_POST['task_id']);
        $assign= !empty($_POST['assignee_id']) ? intval($_POST['assignee_id']) : null;
        $autoProgress = !empty($assign) && !empty($_POST['auto_progress']) && $_POST['auto_progress'] == '1';

        $data = [
            'assignee_id' => $assign,
            'priority_ba' => $_POST['priority_ba'] ?? '1. Không gấp - Không quan trọng',
            'status'      => $autoProgress ? 'Dion - đang xử lý' : 'Todo - chờ xác nhận với Sếp',
        ];
        if(!empty($_POST['implementing_unit'])) $data['implementing_unit'] = $_POST['implementing_unit'];
        if(isset($_POST['classification']))    $data['classification']    = $_POST['classification'];
        if(!empty($_POST['module_id']))        $data['module_id']         = intval($_POST['module_id']);
        if(isset($_POST['feature']))           $data['feature']           = $_POST['feature'];
        if(isset($_POST['ba_description']))    $data['ba_description']    = $_POST['ba_description'];
        if(!empty($assign))                    $data['ba_submission_date']= date('Y-m-d');
        if($autoProgress)                      $data['actual_start_datetime'] = date('Y-m-d H:i:s');
        // BA ước tính (v17) — Lead/BA nội bộ, không hiện với BA nhân viên
        if(!empty($_POST['ba_start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['ba_start_date']))
            $data['ba_start_date'] = $_POST['ba_start_date'];
        if(!empty($_POST['ba_end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['ba_end_date']))
            $data['ba_end_date'] = $_POST['ba_end_date'];
        if(isset($_POST['assignee_note'])) $data['assignee_note'] = trim($_POST['assignee_note']);

        $ok = $task->update($id, $data, 'lead');
        if($ok) webhookSyncTask($db, $id);
        echo json_encode(['success' => $ok]);
        exit;
    }

    // ── TASK: Assign Dev ─────────────────────────────────────────
    // Đổi hệ thống của task (khi BA phát hiện form yêu cầu chọn sai hệ thống)
    if($action === 'update_task_system' && in_array($_SESSION['role'], ['lead','ba'])) {
        $task = new Task($db);
        $id = intval($_POST['task_id']);
        $sysId = !empty($_POST['system_id']) ? intval($_POST['system_id']) : null;
        $ok = $task->update($id, [
            'system_id'       => $sysId,
            'module_node_id'  => null,
            'feature_node_id' => null,
            'logic_node_id'   => null,
            'hidden_node_id'  => null,
        ], $_SESSION['role']);
        if($ok) webhookSyncTask($db, $id);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if($action === 'assign_dev' && in_array($_SESSION['role'], ['lead','ba'])) {
        $task  = new Task($db);
        $id    = intval($_POST['task_id']);
        $devId = !empty($_POST['dev_id']) ? intval($_POST['dev_id']) : null;

        $data = [
            'dev_id'         => $devId,
            'dev_status'     => $devId ? 'Chờ dev nhận' : null,
            'ba_description' => $_POST['ba_description'] ?? '',
        ];
        // Khoảng ngày Dev làm việc (kế hoạch)
        if(!empty($_POST['dev_planned_start']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['dev_planned_start'])) {
            $data['dev_planned_start'] = $_POST['dev_planned_start'];
        }
        if(!empty($_POST['dev_planned_end']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['dev_planned_end'])) {
            $data['dev_planned_end'] = $_POST['dev_planned_end'];
            // Đồng bộ luôn dev_deadline (cuối ngày dự kiến) cho legacy
            $data['dev_deadline'] = $_POST['dev_planned_end'] . ' 17:00:00';
        }
        // Backward-compat: nếu front-end vẫn gửi dev_deadline thẳng
        if(empty($data['dev_planned_end']) && !empty($_POST['dev_deadline'])) {
            $dd = $_POST['dev_deadline'];
            if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $dd)) $dd .= ' 17:00:00';
            elseif(preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $dd)) $dd = str_replace('T', ' ', $dd) . ':00';
            $data['dev_deadline'] = $dd;
        }

        // Module/feature/logic/hidden liên kết với system_nodes — 4 cấp
        // Hỗ trợ: gửi sẵn ID (chọn từ dropdown) HOẶC gửi tên mới + tạo node (Nâng cấp)
        $existingTask = $task->getById($id);
        $sysId = (int)($existingTask['system_id'] ?? 0);
        $sr = new SystemRegistry($db);
        $createdBy = $_SESSION['user_id'];

        // Helper: resolve 1 cấp — nếu có node_id (chọn từ dropdown) → dùng;
        // nếu có new_*_name (cấp được +) → tạo node mới làm con của parent.
        $resolveLevel = function($keyId, $keyNew, $nodeType, $parentId) use ($db, $sr, $sysId, $createdBy) {
            if(isset($_POST[$keyId]) && $_POST[$keyId] !== '') {
                return intval($_POST[$keyId]);
            }
            if(!empty($_POST[$keyNew]) && $sysId) {
                $res = $sr->createNode($sysId, $parentId, $nodeType, trim($_POST[$keyNew]), null, $createdBy);
                if(!empty($res['success'])) return (int)$res['id'];
            }
            return null;
        };

        $modId = $resolveLevel('module_node_id',  'new_module_name',  'module',  null);
        if($modId !== null) $data['module_node_id'] = $modId;

        $featId = $resolveLevel('feature_node_id', 'new_feature_name', 'feature', $data['module_node_id'] ?? null);
        if($featId !== null) $data['feature_node_id'] = $featId;

        $logicId = $resolveLevel('logic_node_id', 'new_logic_name', 'logic', $data['feature_node_id'] ?? null);
        if($logicId !== null) $data['logic_node_id'] = $logicId;

        $hiddenId = $resolveLevel('hidden_node_id', 'new_hidden_name', 'hidden', $data['logic_node_id'] ?? null);
        if($hiddenId !== null) $data['hidden_node_id'] = $hiddenId;

        // Cột legacy `feature` (plain text) cho fallback hiển thị
        if(!empty($_POST['new_feature_name'])) {
            $data['feature'] = trim($_POST['new_feature_name']);
        } elseif(!empty($data['feature_node_id'])) {
            $stmtF = $db->prepare("SELECT name FROM system_nodes WHERE id = ?");
            $stmtF->execute([(int)$data['feature_node_id']]);
            $name = $stmtF->fetchColumn();
            if($name) $data['feature'] = $name;
        }

        $ok = $task->update($id, $data, $_SESSION['role']);
        if($ok && $devId) {
            // Notify dev
            $notif    = new Notification($db);
            $t        = $task->getById($id);
            $actorName= $_SESSION['full_name'] ?? 'BA';
            $maYc     = $t['ma_yc'] ?? ('#'.$id);
            $title    = "[$maYc] Bạn được giao task mới";
            $msg      = "$actorName giao task \"{$t['system_name']}\" ($maYc) cho bạn. Vui lòng kiểm tra và nhận việc.";
            $notif->create($devId, $title, $msg, $id, $_SESSION['user_id'], 'dev_assign');
        }
        if($ok) webhookSyncTask($db, $id);
        echo json_encode(['success' => $ok]);
        exit;
    }

    // ── DEV: Update task (notes, attachment, status) ──────────────
    if($action === 'dev_update') {
        if($_SESSION['role'] !== 'dev') { echo json_encode(['success'=>false,'message'=>'Chỉ dev mới cập nhật được']); exit; }
        $task  = new Task($db);
        $id    = intval($_POST['task_id']);
        $row   = $task->getById($id);
        if(!$row || (int)$row['dev_id'] !== (int)$_SESSION['user_id']) {
            echo json_encode(['success'=>false,'message'=>'Không có quyền']); exit;
        }

        $data = [];
        $newDevStatus = $_POST['dev_status'] ?? null;

        // File upload dev attachment
        if(!empty($_FILES['dev_attachment']['name'])) {
            $dir = '../uploads/dev/';
            if(!is_dir($dir)) mkdir($dir, 0755, true);
            $fname = time() . '_' . basename($_FILES['dev_attachment']['name']);
            if(move_uploaded_file($_FILES['dev_attachment']['tmp_name'], $dir . $fname)) {
                $data['dev_attachment_url'] = 'uploads/dev/' . $fname;
            }
        }

        if(isset($_POST['dev_notes']))  $data['dev_notes'] = $_POST['dev_notes'];

        if($newDevStatus && $newDevStatus !== $row['dev_status']) {
            $data['dev_status'] = $newDevStatus;
            if($newDevStatus === 'Dev đang làm' && !$row['dev_start_at']) {
                $data['dev_start_at'] = date('Y-m-d H:i:s');
            } elseif($newDevStatus === 'Dev đã xong') {
                $data['dev_end_at'] = date('Y-m-d H:i:s');
            }
        }

        $ok = $task->update($id, $data, 'dev');
        if($ok && $newDevStatus) {
            notifyDevTransition($db, $row, $newDevStatus, $_SESSION['user_id'], $_SESSION['full_name']);
        }
        echo json_encode(['success' => $ok]);
        exit;
    }

    // ── DEV: Quay lại bước trước (lùi dev_status) ────────────────
    if($action === 'dev_back' && isset($_SESSION['user_id'])) {
        $task = new Task($db);
        $id   = intval($_POST['task_id']);
        $row  = $task->getById($id);
        if(!$row) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy task']); exit; }

        // Quyền: dev của task này, hoặc lead/ba (để hỗ trợ tình huống cần can thiệp)
        $isOwner = $_SESSION['role'] === 'dev' && (int)$row['dev_id'] === (int)$_SESSION['user_id'];
        $isMgr   = in_array($_SESSION['role'], ['lead','ba']);
        if(!$isOwner && !$isMgr) {
            echo json_encode(['success'=>false,'message'=>'Không có quyền']); exit;
        }

        $devBackMap = [
            'Dev đang làm' => 'Chờ dev nhận',
            'Dev đã xong'  => 'Dev đang làm',
            'Cần sửa'      => 'Dev đang làm',
        ];
        $cur = $row['dev_status'] ?? '';
        $newDev = $devBackMap[$cur] ?? null;
        if(!$newDev) { echo json_encode(['success'=>false,'message'=>'Không thể lùi từ bước này']); exit; }

        $data = ['dev_status' => $newDev];
        // Xoá mốc thời gian cho phù hợp
        if($newDev === 'Chờ dev nhận') { $data['dev_start_at'] = null; $data['dev_end_at'] = null; }
        elseif($newDev === 'Dev đang làm') { $data['dev_end_at'] = null; }

        $ok = $task->update($id, $data, $_SESSION['role']);

        // Gửi notification cho BA + dev liên quan
        if($ok) {
            $notif  = new Notification($db);
            $maYc   = $row['ma_yc'] ?? ('#'.$id);
            $actor  = $_SESSION['full_name'] ?? 'Ai đó';
            $title  = "[$maYc] $actor lùi trạng thái Dev";
            $msg    = "$actor đã quay lại: \"$cur\" → \"$newDev\" trên task \"{$row['system_name']}\" ($maYc).";
            $targets = $notif->getAllLeadIds();
            if(!empty($row['assignee_id'])) $targets[] = (int)$row['assignee_id'];
            if(!empty($row['dev_id']))      $targets[] = (int)$row['dev_id'];
            $notif->createBulk($targets, $title, $msg, $id, $_SESSION['user_id'], 'dev_back');
        }
        echo json_encode(['success' => $ok, 'old_status' => $cur, 'new_status' => $newDev]);
        exit;
    }

    // ── BA: Mark dev task needs rework ───────────────────────────
    if($action === 'dev_rework' && in_array($_SESSION['role'], ['lead','ba'])) {
        $task = new Task($db);
        $id   = intval($_POST['task_id']);
        $row  = $task->getById($id);
        if(!$row) { echo json_encode(['success'=>false]); exit; }

        $ok = $task->update($id, ['dev_status' => 'Cần sửa'], $_SESSION['role']);
        if($ok && $row['dev_id']) {
            $notif  = new Notification($db);
            $maYc   = $row['ma_yc'] ?? ('#'.$id);
            $actor  = $_SESSION['full_name'];
            $title  = "[$maYc] Cần sửa lại";
            $msg    = "$actor yêu cầu sửa lại task \"{$row['system_name']}\" ($maYc). Vui lòng kiểm tra ghi chú.";
            $notif->create($row['dev_id'], $title, $msg, $id, $_SESSION['user_id'], 'dev_rework');
        }
        echo json_encode(['success' => $ok]);
        exit;
    }

    // ── TASK: Update status (BA/Lead) ────────────────────────────
    if($action === 'update_status' && isset($_SESSION['user_id'])) {
        $task   = new Task($db);
        $id     = intval($_POST['task_id']);
        $status = $_POST['status'];
        $data   = ['status' => $status, 'office_link' => $_POST['office_link'] ?? ''];

        if($status === 'Dion - đang xử lý') {
            $data['actual_start_datetime'] = date('Y-m-d H:i:s');
        } elseif(in_array($status, ['Dion - Chờ nghiệm thu', 'Kinkin nghiệm thu'])) {
            $data['actual_end_date'] = date('Y-m-d H:i:s');
            if($status === 'Kinkin nghiệm thu') $data['acceptance_date'] = date('Y-m-d');
        } else {
            $data['actual_end_date'] = null;
        }

        $ok = $task->update($id, $data, $_SESSION['role']);
        if($ok) webhookSyncTask($db, $id);
        echo json_encode(['success' => $ok]);
        exit;
    }

    // ── TASK: Next step ──────────────────────────────────────────
    // Lưu metadata khi BA/Lead tiếp nhận YC: đơn vị, priority BA, phân loại
    if($action === 'claim_task_meta' && in_array($_SESSION['role'], ['lead','ba'])) {
        $task = new Task($db);
        $id   = intval($_POST['task_id']);
        $row  = $task->getById($id);
        if(!$row) { echo json_encode(['success'=>false,'message'=>'Task không tồn tại']); exit; }

        $data = [];
        if(!empty($_POST['implementing_unit'])) $data['implementing_unit'] = $_POST['implementing_unit'];
        if(!empty($_POST['priority_ba']))       $data['priority_ba']       = $_POST['priority_ba'];
        if(isset($_POST['classification']))     $data['classification']    = $_POST['classification'];
        if(empty($data)) { echo json_encode(['success'=>true]); exit; }

        $ok = $task->update($id, $data, $_SESSION['role']);
        if($ok) webhookSyncTask($db, $id);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if($action === 'next_step') {
        $task      = new Task($db);
        $id        = intval($_POST['task_id']);
        $direction = $_POST['direction'] ?? 'next';
        if(!in_array($direction, ['next','cancel','reopen','back'])) {
            echo json_encode(['success'=>false,'message'=>'Hành động không hợp lệ']); exit;
        }
        $current = $task->getById($id);
        if(!$current) { echo json_encode(['success'=>false,'message'=>'Task không tồn tại']); exit; }

        // BA hoặc Lead tự nhận task "Chờ tiếp nhận" (chưa có ai) → auto-assign cho mình
        $isClaiming = (
            in_array($_SESSION['role'], ['ba','lead'])
            && $direction === 'next'
            && empty($current['assignee_id'])
            && $current['status'] === 'Chờ tiếp nhận'
        );
        if($isClaiming) {
            $task->update($id, ['assignee_id' => (int)$_SESSION['user_id']], $_SESSION['role']);
            $current = $task->getById($id);
        }

        if($_SESSION['role'] === 'ba' && (int)$current['assignee_id'] !== (int)$_SESSION['user_id']) {
            echo json_encode(['success'=>false,'message'=>'Bạn không phụ trách task này']); exit;
        }
        $result = $task->nextStep($id, $direction);
        if($result['success']) {
            notifyOnTransition($db, $result['task'], $result['old_status'], $result['new_status'], $direction);

            // Khi advance từ "Todo - chờ xác nhận với Sếp" → "Dion - đang xử lý"
            // (= Bắt đầu code), ghi task vào dev sheet (current week tab).
            if($result['old_status'] === 'Todo - chờ xác nhận với Sếp'
               && $result['new_status'] === 'Dion - đang xử lý') {
                try {
                    require_once '../services/DevSheetService.php';
                    $dss = new DevSheetService($db);
                    $dss->writeTaskToSheet($id);
                    $result['sheet_synced'] = true;
                } catch(Exception $e) {
                    $result['sheet_synced'] = false;
                    $result['sheet_error']  = $e->getMessage();
                    error_log("[DevSheet] writeTaskToSheet failed for task #$id: " . $e->getMessage());
                }
            }
            webhookSyncTask($db, $id);
        }
        echo json_encode($result);
        exit;
    }

    // ── TEST WORKFLOW (Lead/BA bấm trên dashboard) ───────────────
    if(in_array($action, ['test_start','test_done_pending_acceptance','test_accepted','test_report_bug'])) {
        if(!in_array($_SESSION['role'], ['ba','lead'])) {
            echo json_encode(['success'=>false,'message'=>'Chỉ BA/Lead có quyền']); exit;
        }
        $taskId = intval($_POST['task_id'] ?? 0);
        if($taskId <= 0) { echo json_encode(['success'=>false,'message'=>'task_id không hợp lệ']); exit; }
        $task = new Task($db);
        $row  = $task->getById($taskId);
        if(!$row) { echo json_encode(['success'=>false,'message'=>'Task không tồn tại']); exit; }

        // Lấy nickname người đang thao tác
        $stmt = $db->prepare("SELECT id, nickname FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $me = $stmt->fetch(PDO::FETCH_ASSOC);
        $myNick = $me['nickname'] ?? '';

        require_once '../services/DevSheetService.php';
        $dss = new DevSheetService($db);

        try {
            if($action === 'test_start') {
                if($row['status'] !== 'Dion - Chờ nghiệm thu') {
                    echo json_encode(['success'=>false,'message'=>'Task không ở giai đoạn chờ nghiệm thu']); exit;
                }
                $task->update($taskId, [
                    'tester_id'   => (int)$_SESSION['user_id'],
                    'test_date'   => date('Y-m-d'),
                    'test_status' => 'Đang test',
                ]);
                $dss->updateTestStatus($taskId, 'Đang test', $myNick, date('d/m/Y'));
                echo json_encode(['success'=>true,'new_test_status'=>'Đang test']); exit;
            }

            if($action === 'test_done_pending_acceptance') {
                $task->update($taskId, ['test_status' => 'hoàn thành test chờ nghiệm thu']);
                $dss->updateTestStatus($taskId, 'hoàn thành test chờ nghiệm thu', $myNick, null);
                echo json_encode(['success'=>true,'new_test_status'=>'hoàn thành test chờ nghiệm thu']); exit;
            }

            if($action === 'test_accepted') {
                // Người dùng nghiệm thu OK → main status = Kinkin nghiệm thu
                $task->update($taskId, [
                    'test_status'     => 'Đã nghiệm thu từ NV',
                    'status'          => 'Kinkin nghiệm thu',
                    'acceptance_date' => date('Y-m-d'),
                    'actual_end_date' => date('Y-m-d H:i:s'),
                ]);
                $dss->updateTestStatus($taskId, 'Đã nghiệm thu từ NV', $myNick, null);
                echo json_encode(['success'=>true,'new_status'=>'Kinkin nghiệm thu']); exit;
            }

            if($action === 'test_report_bug') {
                $bug = trim($_POST['bug_description'] ?? '');
                if($bug === '') { echo json_encode(['success'=>false,'message'=>'Vui lòng mô tả lỗi']); exit; }

                // Append ghi chú vào dev_notes của DB
                $oldNotes = $row['dev_notes'] ?? '';
                $stamp = date('d/m/Y H:i');
                $newNotes = trim($oldNotes . "\n[$stamp – $myNick báo lỗi] " . $bug);

                $task->update($taskId, [
                    'status'      => 'Dion - đang xử lý',
                    'dev_status'  => 'Cần sửa',
                    'dev_notes'   => $newNotes,
                    'test_status' => null,  // reset
                ]);
                $dss->logBugAndReset($taskId, $bug, $myNick);
                echo json_encode(['success'=>true,'new_status'=>'Dion - đang xử lý','new_dev_status'=>'Cần sửa']); exit;
            }
        } catch(Exception $e) {
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]); exit;
        }
        exit;
    }

    // ── DEV SHEET: poll changes (auto trigger từ frontend hoặc background poller) ──
    if($action === 'dev_sheet_poll') {
        if(!in_array($_SESSION['role'], ['ba','lead'])) { echo json_encode(['success'=>false,'message'=>'Cần BA/Lead']); exit; }

        // Throttle: max 1 poll mỗi 8s/role để tránh spam Google API quota
        // (frontend nhiều tab có thể trigger đồng thời)
        $lockFile = sys_get_temp_dir() . '/ba_tool_dev_sheet_poll.lock';
        if(file_exists($lockFile)) {
            $age = time() - filemtime($lockFile);
            if($age < 8) {
                echo json_encode(['success'=>true, 'throttled'=>true, 'age_sec'=>$age]);
                exit;
            }
        }
        @touch($lockFile);

        try {
            require_once '../services/DevSheetService.php';
            $dss = new DevSheetService($db);
            $stats = $dss->pollChanges();
            echo json_encode(['success'=>true, 'stats'=>$stats]);
        } catch(Exception $e) {
            echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
        }
        exit;
    }

    // ── DEV SHEET: ghi lại task hiện tại vào sheet (manual re-sync 1 task) ──
    if($action === 'dev_sheet_write') {
        if(!in_array($_SESSION['role'], ['ba','lead'])) { echo json_encode(['success'=>false]); exit; }
        try {
            require_once '../services/DevSheetService.php';
            $dss = new DevSheetService($db);
            $res = $dss->writeTaskToSheet(intval($_POST['task_id']));
            echo json_encode(['success'=>true] + $res);
        } catch(Exception $e) {
            echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
        }
        exit;
    }


    // ── USER GROUPS: Lead-only CRUD + setMembers ────────────────
    if(in_array($action, ['create_user_group','update_user_group','delete_user_group','set_user_group_members'])) {
        if($_SESSION['role'] !== 'lead') { echo json_encode(['success'=>false,'message'=>'Chỉ Lead có quyền']); exit; }
        $ug = new UserGroup($db);

        if($action === 'create_user_group') {
            echo json_encode($ug->create(
                $_POST['name'] ?? '', $_POST['description'] ?? '', $_POST['color'] ?? '#0d6efd'
            ));
            exit;
        }
        if($action === 'update_user_group') {
            echo json_encode($ug->update(
                intval($_POST['id'] ?? 0), $_POST['name'] ?? '',
                $_POST['description'] ?? '', $_POST['color'] ?? '#0d6efd'
            ));
            exit;
        }
        if($action === 'delete_user_group') {
            echo json_encode($ug->delete(intval($_POST['id'] ?? 0)));
            exit;
        }
        if($action === 'set_user_group_members') {
            $ids = $_POST['user_ids'] ?? '[]';
            $arr = is_array($ids) ? $ids : (json_decode($ids, true) ?: []);
            echo json_encode($ug->setMembers(intval($_POST['group_id'] ?? 0), $arr));
            exit;
        }
    }

    // ── USER: Create (Lead only — BA không CRUD nhân sự nữa) ────
    if($action === 'create_user' && $_SESSION['role'] === 'lead') {
        $role     = $_POST['role'] ?? '';
        $nickname = !empty($_POST['nickname']) ? trim($_POST['nickname']) : null;
        $user     = new User($db);
        $result = $user->register($_POST['username'], $_POST['password'], $_POST['full_name'], $role, $nickname);
        echo json_encode($result === true ? ['success'=>true] : ['success'=>false,'message'=>$result]);
        exit;
    }

    // ── USER: Edit (Lead only) ────────────────────────────────────
    if($action === 'edit_user' && $_SESSION['role'] === 'lead') {
        $user     = new User($db);
        $userId   = intval($_POST['user_id']);
        $newRole  = $_POST['role'] ?? '';
        $nickname = isset($_POST['nickname']) ? trim($_POST['nickname']) : null;
        $ok = $user->updateUser($userId, $_POST['full_name'], $newRole, $_POST['password'] ?? '', $nickname);
        echo json_encode(['success' => $ok]);
        exit;
    }

    // ── USER: Delete ─────────────────────────────────────────────
    if($action === 'delete_user' && $_SESSION['role'] === 'lead') {
        $id = intval($_POST['user_id']);
        if($id === (int)$_SESSION['user_id']) {
            echo json_encode(['success'=>false,'message'=>'Không thể tự xoá chính mình!']); exit;
        }
        $user = new User($db);
        echo json_encode(['success' => $user->deleteUser($id)]);
        exit;
    }

    // ── Notifications: mark read ─────────────────────────────────
    if($action === 'mark_notification_read') {
        $notif = new Notification($db);
        $nid   = !empty($_POST['notification_id']) ? intval($_POST['notification_id']) : null;
        echo json_encode(['success' => $notif->markRead($_SESSION['user_id'], $nid)]);
        exit;
    }

    // ── Workflow Builder (Lead only) ─────────────────────────────
    if(in_array($action, ['save_workflow','delete_workflow','set_workflow_status'])) {
        if($_SESSION['role'] !== 'lead') { echo json_encode(['success'=>false,'message'=>'Chỉ Lead có quyền']); exit; }
        $wf = new Workflow($db);

        if($action === 'save_workflow') {
            $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
            $data = [
                'code'        => trim($_POST['code'] ?? ''),
                'name'        => trim($_POST['name'] ?? ''),
                'group_name'  => trim($_POST['group_name'] ?? '') ?: null,
                'description' => trim($_POST['description'] ?? '') ?: null,
                'status'      => in_array(($_POST['status'] ?? ''), ['active','inactive','draft']) ? $_POST['status'] : 'draft',
                'definition'  => $_POST['definition'] ?? '{"nodes":[],"edges":[]}',
            ];
            // Validate definition là JSON hợp lệ
            if(json_decode($data['definition']) === null && $data['definition'] !== 'null') {
                echo json_encode(['success'=>false,'message'=>'Định nghĩa quy trình không hợp lệ']); exit;
            }
            $res = $id ? $wf->update($id, $data) : $wf->create($data, $_SESSION['user_id']);
            echo json_encode($res); exit;
        }
        if($action === 'delete_workflow') {
            echo json_encode($wf->delete(intval($_POST['id']))); exit;
        }
        if($action === 'set_workflow_status') {
            echo json_encode($wf->setStatus(intval($_POST['id']), $_POST['status'])); exit;
        }
    }

    // ─── Form Config (Lead only) ─────────────────────────────────
    if(in_array($action, ['save_form_settings','save_form_field','delete_form_field','reorder_form_fields'])) {
        if($_SESSION['role'] !== 'lead') { echo json_encode(['success'=>false,'message'=>'Chỉ Lead có quyền cấu hình form']); exit; }
        $fc = new FormConfig($db);

        if($action === 'save_form_settings') {
            echo json_encode($fc->saveSettings([
                'title'       => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'success_msg' => trim($_POST['success_msg'] ?? '')
            ]));
            exit;
        }
        if($action === 'save_form_field') {
            $id = !empty($_POST['id']) ? intval($_POST['id']) : null;
            $data = [
                'field_key'     => trim($_POST['field_key'] ?? ''),
                'label'         => trim($_POST['label'] ?? ''),
                'field_type'    => $_POST['field_type'] ?? 'text',
                'required'      => !empty($_POST['required']) ? 1 : 0,
                'placeholder'   => trim($_POST['placeholder'] ?? '') ?: null,
                'options_json'  => trim($_POST['options_json'] ?? '') ?: null,
                'display_order' => intval($_POST['display_order'] ?? 100),
                'is_visible'    => isset($_POST['is_visible']) ? (!empty($_POST['is_visible']) ? 1 : 0) : 1,
            ];
            echo json_encode($id ? $fc->updateField($id, $data) : $fc->createField($data));
            exit;
        }
        if($action === 'delete_form_field') {
            echo json_encode($fc->deleteField(intval($_POST['id'])));
            exit;
        }
        if($action === 'reorder_form_fields') {
            $orderJson = $_POST['order_map'] ?? '[]';
            $arr = json_decode($orderJson, true) ?: [];
            $map = [];
            foreach($arr as $i => $id) $map[(int)$id] = ($i + 1) * 10;
            echo json_encode($fc->reorderFields($map));
            exit;
        }
    }

    // ─── Bot Sync (Lead only) ────────────────────────────────────
    if(in_array($action, ['save_bot_settings','upload_bot_credentials','trigger_bot_sync','import_from_sheet'])) {
        if($_SESSION['role'] !== 'lead') { echo json_encode(['success'=>false,'message'=>'Chỉ Lead có quyền cấu hình bot']); exit; }
        $bs = new BotSettings($db);

        if($action === 'save_bot_settings') {
            $sheetUrl    = trim($_POST['sheet_url'] ?? '');
            $devSheetUrl = trim($_POST['dev_sheet_url'] ?? '');
            $data = [
                'sheet_url'       => $sheetUrl,
                'sheet_id'        => BotSettings::extractSheetId($sheetUrl),
                'bot_email'       => trim($_POST['bot_email'] ?? ''),
                'schedule_hour'   => max(0, min(23, intval($_POST['schedule_hour'] ?? 23))),
                'schedule_minute' => max(0, min(59, intval($_POST['schedule_minute'] ?? 0))),
                'enabled'         => !empty($_POST['enabled']) ? 1 : 0,
                'ba_webhook_url'  => trim($_POST['ba_webhook_url'] ?? ''),
                'dev_sheet_url'   => $devSheetUrl,
                'dev_sheet_id'    => BotSettings::extractSheetId($devSheetUrl),
                'poller_enabled'  => !empty($_POST['poller_enabled']) ? 1 : 0,
                'poller_interval' => max(10, min(300, intval($_POST['poller_interval'] ?? 15))),
            ];
            echo json_encode($bs->save($data));
            exit;
        }

        if($action === 'upload_bot_credentials') {
            if(empty($_FILES['credentials_file']['tmp_name'])) {
                echo json_encode(['success'=>false,'message'=>'Chưa chọn file JSON']); exit;
            }
            $raw = file_get_contents($_FILES['credentials_file']['tmp_name']);
            $info = json_decode($raw, true);
            if(!$info || empty($info['client_email']) || empty($info['private_key']) || ($info['type'] ?? '') !== 'service_account') {
                echo json_encode(['success'=>false,'message'=>'File JSON không phải service account hợp lệ']); exit;
            }
            $destDir = __DIR__ . '/../config';
            if(!is_dir($destDir)) mkdir($destDir, 0755, true);
            $dest = $destDir . '/google-credentials.json';
            if(file_put_contents($dest, $raw) === false) {
                echo json_encode(['success'=>false,'message'=>'Không ghi được file credentials']); exit;
            }
            $bs->save([
                'credentials_path' => 'config/google-credentials.json',
                'bot_email'        => $info['client_email']
            ]);
            echo json_encode([
                'success'   => true,
                'bot_email' => $info['client_email'],
                'project'   => $info['project_id'] ?? null
            ]);
            exit;
        }

        if($action === 'trigger_bot_sync') {
            $svc = new TaskSyncService($db);
            $res = $svc->runSync();
            echo json_encode($res);
            exit;
        }

        if($action === 'import_from_sheet') {
            require_once '../services/TaskImportService.php';
            $svc = new TaskImportService($db);
            $opts = [
                'tab'            => $_POST['tab'] ?? 'Tổng quan',
                'dry_run'        => !empty($_POST['dry_run']),
                'auto_create_ba' => !isset($_POST['auto_create_ba']) || !empty($_POST['auto_create_ba']),
            ];
            $res = $svc->runImport($opts);
            echo json_encode($res);
            exit;
        }
    }

    // ─── API Keys Management (Lead only) ──────────────────────────
    if(in_array($action, ['get_api_keys','create_api_key','toggle_api_key','delete_api_key','regenerate_api_key'])) {
        if($_SESSION['role'] !== 'lead') { echo json_encode(['success'=>false,'message'=>'Lead only']); exit; }
        $ak = new ApiKey($db);

        if($action === 'get_api_keys') {
            echo json_encode($ak->getAll());
            exit;
        }
        if($action === 'create_api_key') {
            $name = trim($_POST['name'] ?? '');
            $methods = trim($_POST['methods'] ?? 'GET');
            if(!$name) { echo json_encode(['success'=>false,'message'=>'Tên API key không được trống']); exit; }
            $result = $ak->create($name, $methods, $_SESSION['user_id']);
            echo json_encode(['success'=>true, 'data'=>$result]);
            exit;
        }
        if($action === 'toggle_api_key') {
            $id = intval($_POST['id'] ?? 0);
            $active = intval($_POST['active'] ?? 0);
            echo json_encode(['success'=>$ak->toggleActive($id, $active)]);
            exit;
        }
        if($action === 'delete_api_key') {
            $id = intval($_POST['id'] ?? 0);
            echo json_encode(['success'=>$ak->delete($id)]);
            exit;
        }
        if($action === 'regenerate_api_key') {
            $id = intval($_POST['id'] ?? 0);
            $newToken = $ak->regenerateToken($id);
            echo json_encode(['success'=>true, 'token'=>$newToken]);
            exit;
        }
    }

    // ─── Systems CRUD ────────────────────────────────────────────
    if(in_array($action, ['create_system','update_system','delete_system','set_system_assignees',
                          'create_system_node','update_system_node','delete_system_node','reparent_system_node'])) {
        $sr = new SystemRegistry($db);

        // Lead-only operations
        if(in_array($action, ['create_system','delete_system','set_system_assignees'])) {
            if($_SESSION['role'] !== 'lead') { echo json_encode(['success'=>false,'message'=>'Chỉ Lead có quyền']); exit; }
        }

        if($action === 'create_system') {
            echo json_encode($sr->createSystem(
                $_POST['name'] ?? '', $_POST['code'] ?? '',
                $_POST['description'] ?? '', $_POST['color'] ?? '#0d6efd',
                $_SESSION['user_id']
            ));
            exit;
        }
        if($action === 'update_system') {
            $id = intval($_POST['id']);
            if(!$sr->userCanEdit($id, $_SESSION['user_id'], $_SESSION['role'])) {
                echo json_encode(['success'=>false,'message'=>'Bạn không có quyền sửa hệ thống này']); exit;
            }
            echo json_encode($sr->updateSystem($id, $_POST));
            exit;
        }
        if($action === 'delete_system') {
            echo json_encode($sr->deleteSystem(intval($_POST['id'])));
            exit;
        }
        if($action === 'set_system_assignees') {
            $ids = json_decode($_POST['user_ids'] ?? '[]', true) ?: [];
            echo json_encode($sr->setAssignees(intval($_POST['system_id']), $ids));
            exit;
        }

        // Tree node ops — yêu cầu canEdit
        if(in_array($action, ['create_system_node','update_system_node','delete_system_node','reparent_system_node'])) {
            $sysId = intval($_POST['system_id'] ?? 0);
            if(!$sysId && $action !== 'create_system_node') {
                $node = $sr->getNode(intval($_POST['id']));
                if(!$node) { echo json_encode(['success'=>false,'message'=>'Không tìm thấy node']); exit; }
                $sysId = (int)$node['system_id'];
            }
            if(!$sr->userCanEdit($sysId, $_SESSION['user_id'], $_SESSION['role'])) {
                echo json_encode(['success'=>false,'message'=>'Bạn không có quyền chỉnh sửa hệ thống này']); exit;
            }

            if($action === 'create_system_node') {
                echo json_encode($sr->createNode(
                    $sysId,
                    !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null,
                    $_POST['node_type'] ?? 'module',
                    $_POST['name'] ?? '',
                    $_POST['description'] ?? '',
                    $_SESSION['user_id']
                ));
                exit;
            }
            if($action === 'update_system_node') {
                echo json_encode($sr->updateNode(intval($_POST['id']), $_POST));
                exit;
            }
            if($action === 'delete_system_node') {
                echo json_encode($sr->deleteNode(intval($_POST['id'])));
                exit;
            }
            if($action === 'reparent_system_node') {
                $newParent = isset($_POST['parent_id']) && $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null;
                echo json_encode($sr->reparentNode(intval($_POST['id']), $newParent));
                exit;
            }
        }
    }
}

echo json_encode(['error' => 'unknown_action']);
?>
