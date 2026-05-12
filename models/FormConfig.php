<?php
class FormConfig {
    private $conn;
    private $tbl_settings = "form_settings";
    private $tbl_fields = "form_fields";

    private $allowed_field_cols = [
        'field_key','label','field_type','required','placeholder',
        'options_json','display_order','is_visible'
    ];

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getSettings() {
        $row = $this->conn->query("SELECT * FROM {$this->tbl_settings} WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if(!$row) {
            // Tạo default
            $this->conn->exec("INSERT INTO {$this->tbl_settings} (id, title) VALUES (1, 'Yêu cầu hỗ trợ hệ thống')");
            $row = $this->conn->query("SELECT * FROM {$this->tbl_settings} WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        }
        return $row;
    }

    public function saveSettings($data) {
        $allowed = array_intersect_key($data, array_flip(['title','description','success_msg']));
        if(empty($allowed)) return ['success' => false, 'message' => 'Không có dữ liệu'];
        $set = [];
        foreach($allowed as $k => $v) $set[] = "$k = :$k";
        $sql = "UPDATE {$this->tbl_settings} SET " . implode(',', $set) . " WHERE id = 1";
        $stmt = $this->conn->prepare($sql);
        foreach($allowed as $k => $v) $stmt->bindValue(':' . $k, $v);
        return ['success' => $stmt->execute()];
    }

    public function getAllFields($visibleOnly = false) {
        $sql = "SELECT * FROM {$this->tbl_fields}";
        if($visibleOnly) $sql .= " WHERE is_visible = 1";
        $sql .= " ORDER BY display_order ASC, id ASC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getFieldById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->tbl_fields} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getFieldByKey($key) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->tbl_fields} WHERE field_key = ?");
        $stmt->execute([$key]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createField($data) {
        $allowed = array_intersect_key($data, array_flip($this->allowed_field_cols));
        if(empty($allowed['field_key']) || empty($allowed['label'])) {
            return ['success' => false, 'message' => 'Cần nhập field_key và label'];
        }
        // field_key chỉ chấp nhận chữ thường, số, gạch dưới
        if(!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $allowed['field_key'])) {
            return ['success' => false, 'message' => 'field_key chỉ được chứa chữ thường + số + gạch dưới (bắt đầu bằng chữ)'];
        }
        if($this->getFieldByKey($allowed['field_key'])) {
            return ['success' => false, 'message' => 'field_key đã tồn tại'];
        }
        // Validate options nếu là dropdown
        if(($allowed['field_type'] ?? '') === 'dropdown' && !empty($allowed['options_json'])) {
            if(json_decode($allowed['options_json']) === null) {
                return ['success' => false, 'message' => 'options_json không hợp lệ'];
            }
        }
        $cols = array_keys($allowed);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO {$this->tbl_fields} (" . implode(',', $cols) . ", is_builtin) VALUES (" . implode(',', $placeholders) . ", 0)";
        $stmt = $this->conn->prepare($sql);
        foreach($allowed as $k => $v) $stmt->bindValue(':' . $k, $v);
        if($stmt->execute()) return ['success' => true, 'id' => $this->conn->lastInsertId()];
        return ['success' => false, 'message' => 'Lỗi khi tạo field'];
    }

    public function updateField($id, $data) {
        $row = $this->getFieldById($id);
        if(!$row) return ['success' => false, 'message' => 'Field không tồn tại'];

        // Built-in: không cho đổi field_key, field_type
        $editable = $this->allowed_field_cols;
        if((int)$row['is_builtin'] === 1) {
            $editable = array_diff($editable, ['field_key','field_type']);
        }
        $allowed = array_intersect_key($data, array_flip($editable));
        if(empty($allowed)) return ['success' => false, 'message' => 'Không có dữ liệu'];

        // Validate options nếu là dropdown
        if(isset($allowed['options_json']) && $allowed['options_json'] !== null && $allowed['options_json'] !== '') {
            if(json_decode($allowed['options_json']) === null) {
                return ['success' => false, 'message' => 'options_json không hợp lệ'];
            }
        }
        $set = [];
        foreach($allowed as $k => $v) $set[] = "$k = :$k";
        $sql = "UPDATE {$this->tbl_fields} SET " . implode(',', $set) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        foreach($allowed as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return ['success' => $stmt->execute()];
    }

    public function deleteField($id) {
        $row = $this->getFieldById($id);
        if(!$row) return ['success' => false, 'message' => 'Field không tồn tại'];
        if((int)$row['is_builtin'] === 1) {
            return ['success' => false, 'message' => 'Không thể xoá field hệ thống. Bạn có thể ẩn nó.'];
        }
        $stmt = $this->conn->prepare("DELETE FROM {$this->tbl_fields} WHERE id = ?");
        return ['success' => $stmt->execute([$id])];
    }

    /**
     * Cập nhật thứ tự nhiều field cùng lúc
     * @param array $orderMap [ id => display_order, ... ]
     */
    public function reorderFields($orderMap) {
        if(empty($orderMap)) return ['success' => false];
        $stmt = $this->conn->prepare("UPDATE {$this->tbl_fields} SET display_order = ? WHERE id = ?");
        foreach($orderMap as $id => $order) {
            $stmt->execute([(int)$order, (int)$id]);
        }
        return ['success' => true];
    }
}
?>
