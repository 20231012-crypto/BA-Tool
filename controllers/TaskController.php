<?php
require_once 'models/Task.php';
require_once 'models/User.php';

class TaskController {

    public function dashboard() {
        if(!isset($_SESSION['user_id'])) {
            header("Location: ?page=login"); exit;
        }

        $database = new Database();
        $db       = $database->getConnection();
        $task     = new Task($db);
        $userModel= new User($db);
        $role     = $_SESSION['role'];
        $user_id  = $_SESSION['user_id'];

        if($role === 'lead') {
            $tasks = $task->getAll()->fetchAll(PDO::FETCH_ASSOC);
            $bas   = $userModel->getAllBA()->fetchAll(PDO::FETCH_ASSOC);
            include 'views/admin/lead.php';

        } elseif($role === 'dev') {
            // Dev không có dashboard riêng — quản lý công việc qua Google Sheet
            session_destroy();
            header("Location: ?page=login");
            exit;

        } else { // ba
            $tasks = $task->getByAssignee($user_id)->fetchAll(PDO::FETCH_ASSOC);
            include 'views/admin/ba.php';
        }
    }

    public function updateTask() {
        if(!isset($_SESSION['user_id'])) { die("Unauthorized"); }

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $database = new Database();
            $db       = $database->getConnection();
            $task     = new Task($db);
            $id       = $_POST['task_id'];
            $role     = $_SESSION['role'];
            $action   = $_POST['action'] ?? '';

            if($role === 'lead' && $action === 'assign') {
                $data = [
                    'assignee_id'    => !empty($_POST['assignee_id']) ? $_POST['assignee_id'] : null,
                    'priority_ba'    => $_POST['priority_ba'],
                    'status'         => 'Todo - chờ xác nhận với Sếp',
                ];
                if(!empty($_POST['implementing_unit'])) $data['implementing_unit'] = $_POST['implementing_unit'];
                if(!empty($_POST['classification']))    $data['classification']    = $_POST['classification'];
                if(!empty($_POST['assignee_id']))       $data['ba_submission_date']= date('Y-m-d');
                if(!empty($_POST['assignee_id']) && ($_POST['status_override'] ?? '') === 'yes') {
                    $data['status']                = 'Dion - đang xử lý';
                    $data['actual_start_datetime'] = date('Y-m-d H:i:s');
                }
                $task->update($id, $data, $role);

            } elseif($role === 'ba' && $action === 'update_status') {
                $status = $_POST['status'];
                $data   = ['status' => $status, 'office_link' => $_POST['office_link'] ?? ''];
                if($status === 'Dion - đang xử lý') {
                    $data['actual_start_datetime'] = date('Y-m-d H:i:s');
                } elseif(in_array($status, ['Dion - Chờ nghiệm thu','Kinkin nghiệm thu'])) {
                    $data['actual_end_date'] = date('Y-m-d H:i:s');
                    if($status === 'Kinkin nghiệm thu') $data['acceptance_date'] = date('Y-m-d');
                } else {
                    $data['actual_end_date'] = null;
                }
                $task->update($id, $data, $role);
            }

            header("Location: ?page=dashboard"); exit;
        }
    }
}
?>
