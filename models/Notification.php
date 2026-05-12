<?php
class Notification {
    private $conn;
    private $table = "notifications";

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Tạo 1 thông báo
     */
    public function create($user_id, $title, $message, $task_id = null, $from_user_id = null, $type = 'next_step') {
        $sql = "INSERT INTO {$this->table} (user_id, from_user_id, task_id, type, title, message)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$user_id, $from_user_id, $task_id, $type, $title, $message]);
    }

    /**
     * Tạo cho NHIỀU user cùng lúc (vd: gửi cho tất cả Lead)
     */
    public function createBulk(array $user_ids, $title, $message, $task_id = null, $from_user_id = null, $type = 'next_step') {
        if(empty($user_ids)) return false;
        foreach($user_ids as $uid) {
            if((int)$uid === (int)$from_user_id) continue; // Không tự thông báo cho mình
            $this->create($uid, $title, $message, $task_id, $from_user_id, $type);
        }
        return true;
    }

    /**
     * Lấy danh sách thông báo của 1 user (mặc định: 30 cái mới nhất)
     */
    public function getForUser($user_id, $limit = 30) {
        $sql = "SELECT n.*, u.full_name AS from_name, t.ma_yc AS task_code, t.system_name
                FROM {$this->table} n
                LEFT JOIN users u ON n.from_user_id = u.id
                LEFT JOIN tasks t ON n.task_id = t.id
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT " . intval($limit);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function countUnread($user_id) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    }

    public function markRead($user_id, $notification_id = null) {
        if($notification_id) {
            $stmt = $this->conn->prepare("UPDATE {$this->table} SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
            return $stmt->execute([$notification_id, $user_id]);
        }
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$user_id]);
    }

    /**
     * Lấy danh sách user_id của tất cả Lead (để gửi notification khi BA chuyển bước)
     */
    public function getAllLeadIds() {
        $stmt = $this->conn->query("SELECT id FROM users WHERE role = 'lead'");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }
}
