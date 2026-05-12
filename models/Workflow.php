<?php
class Workflow {
    private $conn;
    private $table = "workflows";

    private $allowed_columns = [
        'code', 'name', 'group_name', 'description', 'status', 'definition'
    ];

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAll() {
        $sql = "SELECT w.*, u.full_name AS creator_name
                FROM {$this->table} w
                LEFT JOIN users u ON w.created_by = u.id
                ORDER BY w.updated_at DESC";
        return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getByCode($code) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE code = ?");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data, $created_by = null) {
        $filtered = array_intersect_key($data, array_flip($this->allowed_columns));
        if(empty($filtered['code']) || empty($filtered['name'])) {
            return ['success' => false, 'message' => 'Mã và tên quy trình là bắt buộc'];
        }
        if($this->getByCode($filtered['code'])) {
            return ['success' => false, 'message' => 'Mã quy trình đã tồn tại'];
        }

        $cols = array_keys($filtered);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO {$this->table} (" . implode(',', $cols) . ", created_by)
                VALUES (" . implode(',', $placeholders) . ", :created_by)";
        $stmt = $this->conn->prepare($sql);
        foreach($filtered as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->bindValue(':created_by', $created_by);
        if($stmt->execute()) {
            return ['success' => true, 'id' => $this->conn->lastInsertId()];
        }
        return ['success' => false, 'message' => 'Lỗi khi tạo quy trình'];
    }

    public function update($id, $data) {
        $filtered = array_intersect_key($data, array_flip($this->allowed_columns));
        if(empty($filtered)) return ['success' => false, 'message' => 'Không có dữ liệu để cập nhật'];

        // Nếu đổi code, kiểm tra trùng
        if(isset($filtered['code'])) {
            $existing = $this->getByCode($filtered['code']);
            if($existing && $existing['id'] != $id) {
                return ['success' => false, 'message' => 'Mã quy trình đã tồn tại'];
            }
        }

        $set = [];
        foreach($filtered as $k => $v) $set[] = "$k = :$k";
        $sql = "UPDATE {$this->table} SET " . implode(',', $set) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        foreach($filtered as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->bindValue(':id', $id);
        return ['success' => $stmt->execute()];
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return ['success' => $stmt->execute([$id])];
    }

    public function setStatus($id, $status) {
        if(!in_array($status, ['active', 'inactive', 'draft'])) {
            return ['success' => false, 'message' => 'Trạng thái không hợp lệ'];
        }
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = ? WHERE id = ?");
        return ['success' => $stmt->execute([$status, $id])];
    }
}
?>
