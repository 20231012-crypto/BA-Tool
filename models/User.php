<?php
class User {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function login($username, $password) {
        $stmt = $this->conn->prepare(
            "SELECT id, username, password, full_name, role FROM {$this->table_name} WHERE username = ? LIMIT 1"
        );
        $stmt->execute([$username]);
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['password'])) return $row;
        }
        return false;
    }

    public function register($username, $password, $full_name, $role, $nickname = null) {
        $stmt = $this->conn->prepare("SELECT id FROM {$this->table_name} WHERE username = ?");
        $stmt->execute([$username]);
        if($stmt->rowCount() > 0) return "Tên đăng nhập đã tồn tại!";

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $nickname = $nickname ?: self::deriveNickname($full_name);
        $stmt = $this->conn->prepare(
            "INSERT INTO {$this->table_name} (username, password, full_name, role, nickname) VALUES (?, ?, ?, ?, ?)"
        );
        return $stmt->execute([$username, $hash, $full_name, $role, $nickname]) ? true : "Lỗi máy chủ CSDL.";
    }

    /** Auto derive nickname từ full_name = token cuối, vd "Trần Đức Minh" → "Minh" */
    public static function deriveNickname($fullName) {
        $fullName = trim((string)$fullName);
        if($fullName === '') return '';
        $parts = preg_split('/\s+/u', $fullName);
        return end($parts);
    }

    public function getAllBA() {
        $stmt = $this->conn->prepare(
            "SELECT id, full_name, username, nickname FROM {$this->table_name} WHERE role = 'ba' ORDER BY full_name"
        );
        $stmt->execute();
        return $stmt;
    }

    public function getAllDev() {
        $stmt = $this->conn->prepare(
            "SELECT id, full_name, username, nickname FROM {$this->table_name} WHERE role = 'dev' ORDER BY full_name"
        );
        $stmt->execute();
        return $stmt;
    }

    public function getAllWithPerformance() {
        $done = "('Dion - Chờ nghiệm thu', 'Kinkin nghiệm thu')";
        // Trộn task của BA (assignee) + Dev (dev_id) để mỗi user có metric tương ứng
        $sql = "SELECT u.id, u.username, u.full_name, u.role, u.nickname,
                       COUNT(DISTINCT IF(u.role IN ('ba','lead'), tba.id, td.id)) AS total_tasks,
                       SUM(CASE
                           WHEN u.role IN ('ba','lead') AND tba.status IN $done THEN 1
                           WHEN u.role = 'dev' AND td.dev_status = 'Dev đã xong' THEN 1
                           ELSE 0 END) AS completed_tasks,
                       SUM(CASE
                           WHEN u.role IN ('ba','lead') AND tba.status IN $done AND tba.actual_end_date <= tba.expected_end_date THEN 1
                           WHEN u.role = 'dev' AND td.dev_status = 'Dev đã xong' AND td.dev_end_at IS NOT NULL AND td.dev_end_at <= td.expected_end_date THEN 1
                           ELSE 0 END) AS on_time,
                       SUM(CASE
                           WHEN u.role IN ('ba','lead') AND tba.status IN $done AND tba.actual_end_date > tba.expected_end_date THEN 1
                           WHEN u.role = 'dev' AND td.dev_status = 'Dev đã xong' AND td.dev_end_at IS NOT NULL AND td.dev_end_at > td.expected_end_date THEN 1
                           ELSE 0 END) AS late
                FROM {$this->table_name} u
                LEFT JOIN tasks tba ON tba.assignee_id = u.id AND u.role IN ('ba','lead')
                LEFT JOIN tasks td  ON td.dev_id = u.id      AND u.role = 'dev'
                WHERE u.role IN ('ba','lead','dev')
                GROUP BY u.id
                ORDER BY FIELD(u.role,'lead','ba','dev'), u.full_name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt;
    }


    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT id, username, full_name, role, nickname FROM {$this->table_name} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function deleteUser($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table_name} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function updateUser($id, $full_name, $role, $password = '', $nickname = null) {
        if($nickname === null) $nickname = self::deriveNickname($full_name);
        if(!empty($password)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->conn->prepare(
                "UPDATE {$this->table_name} SET full_name=?, role=?, nickname=?, password=? WHERE id=?"
            );
            return $stmt->execute([$full_name, $role, $nickname, $hash, $id]);
        }
        $stmt = $this->conn->prepare(
            "UPDATE {$this->table_name} SET full_name=?, role=?, nickname=? WHERE id=?"
        );
        return $stmt->execute([$full_name, $role, $nickname, $id]);
    }
}
?>
