<?php
class Task {
    private $conn;
    private $table_name = "tasks";

    private $allowed_columns = [
        'requester_name', 'requester_dept', 'system_name', 'description',
        'task_type', 'priority_requester', 'start_date', 'expected_end_date',
        'attachment_url', 'priority_ba', 'office_link', 'status', 'actual_end_date',
        'assignee_id',
        'ma_yc', 'module_id', 'feature', 'ba_description', 'classification',
        'ba_submission_date', 'actual_start_datetime', 'acceptance_date',
        'implementing_unit', 'dev_actual_day',
        // Linking to systems registry
        'system_id', 'module_node_id', 'feature_node_id', 'logic_node_id', 'hidden_node_id',
        // Dev fields
        'dev_id', 'dev_status', 'dev_notes', 'dev_attachment_url',
        'dev_start_at', 'dev_end_at', 'dev_deadline',
        'dev_planned_start', 'dev_planned_end',
        // Test fields (v14)
        'tester_id', 'test_date', 'test_status',
        // BA estimate + assignee note (v17)
        'ba_start_date', 'ba_end_date', 'assignee_note',
    ];

    public function __construct($db) {
        $this->conn = $db;
    }

    private function generateMaYc() {
        // Dùng MAX số trong ma_yc thay vì MAX(id) để handle gaps (xóa, import từ sheet)
        $stmt = $this->conn->query(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(ma_yc, 3) AS UNSIGNED)), 0) + 1 AS next_num FROM " . $this->table_name .
            " WHERE ma_yc REGEXP '^YC[0-9]+$'"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return 'YC' . str_pad($row['next_num'], 3, '0', STR_PAD_LEFT);
    }

    /** Kiểm tra mã YC đã tồn tại chưa */
    private function maYcExists($maYc) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE ma_yc = ?");
        $stmt->execute([$maYc]);
        return $stmt->fetchColumn() > 0;
    }

    public function create($data) {
        // Sinh mã YC không trùng — retry tối đa 5 lần nếu bị race condition
        $maxRetry = 5;
        for ($i = 0; $i < $maxRetry; $i++) {
            $data['ma_yc'] = $this->generateMaYc();
            if (!$this->maYcExists($data['ma_yc'])) break;
        }

        $query = "INSERT INTO " . $this->table_name . "
                  (ma_yc, requester_name, requester_dept, system_name, description, task_type,
                   priority_requester, start_date, expected_end_date, attachment_url)
                  VALUES (:ma_yc, :requester_name, :requester_dept, :system_name, :description, :task_type,
                          :priority_requester, :start_date, :expected_end_date, :attachment_url)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ma_yc',              $data['ma_yc']);
        $stmt->bindParam(':requester_name',     $data['requester_name']);
        $stmt->bindParam(':requester_dept',     $data['requester_dept']);
        $stmt->bindParam(':system_name',        $data['system_name']);
        $stmt->bindParam(':description',        $data['description']);
        $stmt->bindParam(':task_type',          $data['task_type']);
        $stmt->bindParam(':priority_requester', $data['priority_requester']);
        $stmt->bindParam(':start_date',         $data['start_date']);
        $stmt->bindParam(':expected_end_date',  $data['expected_end_date']);
        $stmt->bindParam(':attachment_url',     $data['attachment_url']);

        return $stmt->execute() ? $data['ma_yc'] : false;
    }

    private function selectFields() {
        return "t.*,
                u.full_name  AS assignee_name,
                d.full_name  AS dev_name,
                m.name       AS module_name,
                sys.name     AS sys_name,
                mn.name      AS module_node_name,
                fn.name      AS feature_node_name,
                ln.name      AS logic_node_name,
                hn.name      AS hidden_node_name,
                CASE
                    WHEN t.actual_end_date IS NULL OR t.expected_end_date IS NULL THEN NULL
                    WHEN t.actual_end_date <= t.expected_end_date THEN 0
                    ELSE ROUND(TIMESTAMPDIFF(MINUTE, t.expected_end_date, t.actual_end_date) / 60, 2)
                END AS delay_hours,
                CASE
                    WHEN t.actual_end_date IS NULL THEN NULL
                    WHEN t.actual_end_date <= t.expected_end_date THEN 'Đúng hạn'
                    ELSE 'Quá hạn'
                END AS delay_status,
                CASE
                    WHEN t.dev_end_at IS NULL THEN NULL
                    ELSE ROUND(TIMESTAMPDIFF(MINUTE, t.dev_start_at, t.dev_end_at) / 60, 2)
                END AS dev_hours,
                DATE_FORMAT(t.expected_end_date, '%m/%Y') AS expected_month,
                WEEK(t.created_at, 1) AS req_week,
                DATE_FORMAT(t.created_at, '%m/%Y') AS req_month,
                t.ba_start_date,
                t.ba_end_date,
                t.assignee_note";
    }

    public function getAll() {
        $query = "SELECT " . $this->selectFields() . "
                  FROM " . $this->table_name . " t
                  LEFT JOIN users u ON t.assignee_id = u.id
                  LEFT JOIN users d ON t.dev_id = d.id
                  LEFT JOIN modules m ON t.module_id = m.id
                  LEFT JOIN systems sys ON t.system_id = sys.id
                  LEFT JOIN system_nodes mn ON t.module_node_id = mn.id
                  LEFT JOIN system_nodes fn ON t.feature_node_id = fn.id
                  LEFT JOIN system_nodes ln ON t.logic_node_id = ln.id
                  LEFT JOIN system_nodes hn ON t.hidden_node_id = hn.id
                  ORDER BY t.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function getByAssignee($user_id) {
        // BA chỉ thấy task được Lead phân công cho mình
        $query = "SELECT " . $this->selectFields() . "
                  FROM " . $this->table_name . " t
                  LEFT JOIN users u ON t.assignee_id = u.id
                  LEFT JOIN users d ON t.dev_id = d.id
                  LEFT JOIN modules m ON t.module_id = m.id
                  LEFT JOIN systems sys ON t.system_id = sys.id
                  LEFT JOIN system_nodes mn ON t.module_node_id = mn.id
                  LEFT JOIN system_nodes fn ON t.feature_node_id = fn.id
                  LEFT JOIN system_nodes ln ON t.logic_node_id = ln.id
                  LEFT JOIN system_nodes hn ON t.hidden_node_id = hn.id
                  WHERE t.assignee_id = :uid
                  ORDER BY t.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':uid', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    public function getById($id) {
        $sql = "SELECT " . $this->selectFields() . "
                FROM " . $this->table_name . " t
                LEFT JOIN users u ON t.assignee_id = u.id
                LEFT JOIN users d ON t.dev_id = d.id
                LEFT JOIN modules m ON t.module_id = m.id
                LEFT JOIN systems sys ON t.system_id = sys.id
                LEFT JOIN system_nodes mn ON t.module_node_id = mn.id
                LEFT JOIN system_nodes fn ON t.feature_node_id = fn.id
                LEFT JOIN system_nodes ln ON t.logic_node_id = ln.id
                LEFT JOIN system_nodes hn ON t.hidden_node_id = hn.id
                WHERE t.id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getNextStatus($current) {
        $map = [
            'Chờ tiếp nhận'         => 'Todo - chờ xác nhận với Sếp',
            'Todo - chờ xác nhận với Sếp' => 'Dion - đang xử lý',
            'Dion - đang xử lý'           => 'Dion - Chờ nghiệm thu',
            'Dion - Chờ nghiệm thu'       => 'Kinkin nghiệm thu',
            'Kinkin nghiệm thu'           => null,
            'Huỷ'                         => null,
        ];
        return $map[$current] ?? null;
    }

    public function nextStep($id, $direction = 'next') {
        $row = $this->getById($id);
        if(!$row) return ['success' => false, 'message' => 'Không tìm thấy task'];

        $oldStatus = $row['status'];
        $newStatus = null;
        $data = [];

        if($direction === 'cancel') {
            if($oldStatus === 'Kinkin nghiệm thu') {
                return ['success' => false, 'message' => 'Task đã nghiệm thu, không thể huỷ'];
            }
            $newStatus = 'Huỷ';
        } elseif($direction === 'reopen') {
            if($oldStatus !== 'Huỷ') return ['success' => false, 'message' => 'Chỉ task đã huỷ mới có thể mở lại'];
            $newStatus = 'Todo - chờ xác nhận với Sếp';
        } elseif($direction === 'back') {
            $backMap = [
                'Todo - chờ xác nhận với Sếp' => 'Chờ tiếp nhận',
                'Dion - đang xử lý'           => 'Todo - chờ xác nhận với Sếp',
                'Dion - Chờ nghiệm thu'       => 'Dion - đang xử lý',
                'Kinkin nghiệm thu'           => 'Dion - Chờ nghiệm thu',
            ];
            $newStatus = $backMap[$oldStatus] ?? null;
            if(!$newStatus) return ['success' => false, 'message' => 'Không thể quay lại từ bước này'];
        } else {
            $newStatus = self::getNextStatus($oldStatus);
            if(!$newStatus) return ['success' => false, 'message' => 'Đã ở bước cuối cùng'];
        }

        $data['status'] = $newStatus;

        if($newStatus === 'Dion - đang xử lý') {
            if($direction !== 'back') $data['actual_start_datetime'] = date('Y-m-d H:i:s');
            if($direction === 'back') $data['actual_end_date'] = null;
        } elseif(in_array($newStatus, ['Dion - Chờ nghiệm thu', 'Kinkin nghiệm thu'])) {
            if($direction !== 'back') $data['actual_end_date'] = date('Y-m-d H:i:s');
            if($newStatus === 'Kinkin nghiệm thu' && $direction !== 'back') $data['acceptance_date'] = date('Y-m-d');
            if($newStatus === 'Dion - Chờ nghiệm thu' && $direction === 'back') $data['acceptance_date'] = null;
        } elseif($newStatus === 'Todo - chờ xác nhận với Sếp') {
            $data['actual_start_datetime'] = null;
            $data['actual_end_date'] = null;
        } elseif($newStatus === 'Chờ tiếp nhận') {
            // Back về trạng thái chưa phân công: xoá assignee để Lead phân lại
            $data['assignee_id']           = null;
            $data['priority_ba']           = null;
            $data['actual_start_datetime'] = null;
            $data['actual_end_date']       = null;
        }

        $ok = $this->update($id, $data, null);
        return [
            'success'    => $ok,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'task'       => $this->getById($id),
        ];
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function update($id, $data, $role = null) {
        $filtered = array_intersect_key($data, array_flip($this->allowed_columns));
        if(empty($filtered)) return false;

        $fields = [];
        foreach($filtered as $key => $value) {
            $fields[] = "$key = :$key";
        }
        $query = "UPDATE " . $this->table_name . " SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        foreach($filtered as $key => &$value) {
            $stmt->bindParam(':' . $key, $value);
        }
        return $stmt->execute();
    }
}
?>
