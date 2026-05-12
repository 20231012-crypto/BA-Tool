<?php
require_once 'models/Task.php';
require_once 'models/FormConfig.php';

class FormController {

    public function index() {
        // Show the public form
        include 'views/public/form.php';
    }

    public function submit() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

        $database = new Database();
        $db = $database->getConnection();
        $task = new Task($db);
        $fc = new FormConfig($db);

        $fields = $fc->getAllFields(true);

        // Built-in fields được map trực tiếp sang cột tasks
        $builtinKeys = [
            'requester_name','requester_dept','system_name','description',
            'task_type','priority_requester','start_date','expected_end_date'
        ];

        $data = [];
        $custom = [];
        $attachment_url = null;

        foreach($fields as $f) {
            $key = $f['field_key'];
            // File upload
            if($f['field_type'] === 'file') {
                if(!empty($_FILES[$key]['name'])) {
                    $target_dir = "uploads/";
                    if(!is_dir($target_dir)) mkdir($target_dir);
                    $file_name = time() . '_' . basename($_FILES[$key]["name"]);
                    $target_file = $target_dir . $file_name;
                    if(move_uploaded_file($_FILES[$key]["tmp_name"], $target_file)) {
                        if($key === 'attachment') $attachment_url = $target_file;
                        else $custom[$key] = $target_file;
                    }
                }
                continue;
            }
            $val = $_POST[$key] ?? '';
            if(in_array($key, $builtinKeys)) {
                $data[$key] = $val;
            } else {
                $custom[$key] = $val;
            }
        }

        // Convert DATE -> DATETIME (nếu có start_date / expected_end_date)
        if(!empty($data['start_date']) && strlen($data['start_date']) === 10) $data['start_date'] .= ' 08:00:00';
        if(!empty($data['expected_end_date']) && strlen($data['expected_end_date']) === 10) $data['expected_end_date'] .= ' 17:00:00';

        // Defaults phòng case field bị ẩn nhưng schema không nullable
        $data['requester_name']     = $data['requester_name']     ?? '';
        $data['requester_dept']     = $data['requester_dept']     ?? '';
        $data['system_name']        = $data['system_name']        ?? '';
        $data['description']        = $data['description']        ?? '';
        $data['task_type']          = $data['task_type']          ?? '';
        $data['priority_requester'] = $data['priority_requester'] ?? '1. Không gấp - Không quan trọng';
        $data['start_date']         = $data['start_date']         ?? date('Y-m-d 08:00:00');
        $data['expected_end_date']  = $data['expected_end_date']  ?? date('Y-m-d 17:00:00');
        $data['attachment_url']     = $attachment_url;

        $maYc = $task->create($data);

        if($maYc) {
            // Auto-link system_id từ system_name
            if(!empty($data['system_name'])) {
                $stmt = $db->prepare("UPDATE tasks t
                                      JOIN systems s ON s.name = t.system_name
                                      SET t.system_id = s.id
                                      WHERE t.ma_yc = ?");
                $stmt->execute([$maYc]);
            }
            // Lưu custom fields vào tasks.custom_data nếu có
            if(!empty($custom)) {
                $stmt = $db->prepare("UPDATE tasks SET custom_data = ? WHERE ma_yc = ?");
                $stmt->execute([json_encode($custom, JSON_UNESCAPED_UNICODE), $maYc]);
            }
        }

        if($maYc) {
            echo "<script>alert('Gửi yêu cầu thành công! Mã YC: " . htmlspecialchars($maYc) . "'); window.location.href='?page=public_form';</script>";
        } else {
            echo "<script>alert('Có lỗi xảy ra, vui lòng thử lại.'); window.history.back();</script>";
        }
    }
}
?>
