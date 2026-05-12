<?php
class UserGroup {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    /** Trả về danh sách nhóm + member_count + members (id,full_name,role) */
    public function listAll() {
        $rows = $this->conn->query("
            SELECT g.id, g.name, g.description, g.color,
                   (SELECT COUNT(*) FROM user_group_members WHERE group_id = g.id) AS member_count
            FROM user_groups g
            ORDER BY g.name
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Bulk-load members
        $stmt = $this->conn->query("
            SELECT m.group_id, u.id, u.full_name, u.username, u.role
            FROM user_group_members m
            JOIN users u ON u.id = m.user_id
            ORDER BY u.full_name
        ");
        $byGroup = [];
        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $gid = (int)$r['group_id'];
            unset($r['group_id']);
            $byGroup[$gid][] = $r;
        }
        foreach($rows as &$g) {
            $g['members'] = $byGroup[(int)$g['id']] ?? [];
        }
        return $rows;
    }

    public function get($id) {
        $stmt = $this->conn->prepare("SELECT * FROM user_groups WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($name, $description, $color) {
        $name = trim($name);
        if($name === '') return ['success' => false, 'message' => 'Tên nhóm không được rỗng'];
        try {
            $stmt = $this->conn->prepare("INSERT INTO user_groups (name, description, color) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $color ?: '#0d6efd']);
            return ['success' => true, 'id' => (int)$this->conn->lastInsertId()];
        } catch(PDOException $e) {
            if(strpos($e->getMessage(), 'Duplicate') !== false) {
                return ['success' => false, 'message' => "Nhóm '$name' đã tồn tại"];
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update($id, $name, $description, $color) {
        $name = trim($name);
        if($name === '') return ['success' => false, 'message' => 'Tên nhóm không được rỗng'];
        try {
            $stmt = $this->conn->prepare("UPDATE user_groups SET name = ?, description = ?, color = ? WHERE id = ?");
            $stmt->execute([$name, $description, $color ?: '#0d6efd', $id]);
            return ['success' => true];
        } catch(PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM user_groups WHERE id = ?");
        return ['success' => $stmt->execute([$id])];
    }

    /** Thay thế toàn bộ thành viên của nhóm bằng $userIds (mảng int). */
    public function setMembers($groupId, array $userIds) {
        $this->conn->beginTransaction();
        try {
            $del = $this->conn->prepare("DELETE FROM user_group_members WHERE group_id = ?");
            $del->execute([$groupId]);
            if(!empty($userIds)) {
                $ins = $this->conn->prepare("INSERT INTO user_group_members (group_id, user_id) VALUES (?, ?)");
                foreach($userIds as $uid) {
                    $uid = intval($uid);
                    if($uid > 0) $ins->execute([$groupId, $uid]);
                }
            }
            $this->conn->commit();
            return ['success' => true];
        } catch(Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Trả về danh sách user (lọc theo role nếu có) kèm cờ in_group cho 1 group cụ thể */
    public function listUsersForGroup($groupId, $roleFilter = null) {
        $sql = "
            SELECT u.id, u.full_name, u.username, u.role,
                   CASE WHEN m.user_id IS NULL THEN 0 ELSE 1 END AS in_group
            FROM users u
            LEFT JOIN user_group_members m ON m.user_id = u.id AND m.group_id = ?
        ";
        $params = [$groupId];
        if($roleFilter) {
            $sql .= " WHERE u.role = ? ";
            $params[] = $roleFilter;
        }
        $sql .= " ORDER BY u.role, u.full_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
