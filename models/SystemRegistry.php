<?php
class SystemRegistry {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    // ───── Systems ─────────────────────────────────────────────
    public function listSystems($forUserId = null, $role = 'lead') {
        // Lead xem hết; BA chỉ xem hệ thống được gán + hệ thống mình tạo
        if($role === 'lead') {
            $sql = "SELECT s.*, u.full_name AS creator_name,
                           (SELECT COUNT(*) FROM system_assignees sa WHERE sa.system_id = s.id) AS ba_count,
                           (SELECT COUNT(*) FROM system_nodes sn WHERE sn.system_id = s.id) AS node_count
                    FROM systems s
                    LEFT JOIN users u ON s.created_by = u.id
                    ORDER BY s.updated_at DESC";
            return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        }
        $sql = "SELECT DISTINCT s.*, u.full_name AS creator_name,
                       (SELECT COUNT(*) FROM system_assignees sa WHERE sa.system_id = s.id) AS ba_count,
                       (SELECT COUNT(*) FROM system_nodes sn WHERE sn.system_id = s.id) AS node_count
                FROM systems s
                LEFT JOIN system_assignees sa ON sa.system_id = s.id
                LEFT JOIN users u ON s.created_by = u.id
                WHERE sa.user_id = :uid OR s.created_by = :uid
                ORDER BY s.updated_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':uid' => $forUserId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSystem($id) {
        $stmt = $this->conn->prepare("SELECT * FROM systems WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAssignees($systemId) {
        $stmt = $this->conn->prepare("
            SELECT u.id, u.full_name, u.username, u.role
            FROM system_assignees sa JOIN users u ON sa.user_id = u.id
            WHERE sa.system_id = ?
            ORDER BY u.full_name");
        $stmt->execute([$systemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function userCanEdit($systemId, $userId, $role) {
        if($role === 'lead') return true;
        $stmt = $this->conn->prepare("SELECT 1 FROM system_assignees WHERE system_id = ? AND user_id = ?");
        $stmt->execute([$systemId, $userId]);
        if($stmt->fetchColumn()) return true;
        // Hoặc là người tạo
        $stmt = $this->conn->prepare("SELECT 1 FROM systems WHERE id = ? AND created_by = ?");
        $stmt->execute([$systemId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function createSystem($name, $code, $description, $color, $creatorId) {
        $name = trim($name);
        if($name === '') return ['success' => false, 'message' => 'Cần nhập tên hệ thống'];
        $code = trim($code) ?: null;
        if($code) {
            $stmt = $this->conn->prepare("SELECT id FROM systems WHERE code = ?");
            $stmt->execute([$code]);
            if($stmt->fetchColumn()) return ['success' => false, 'message' => 'Code đã tồn tại'];
        }
        $stmt = $this->conn->prepare("INSERT INTO systems (name, code, description, color, created_by) VALUES (?, ?, ?, ?, ?)");
        $ok = $stmt->execute([$name, $code, $description ?: null, $color ?: '#0d6efd', $creatorId]);
        return $ok ? ['success' => true, 'id' => $this->conn->lastInsertId()] : ['success' => false, 'message' => 'Lỗi khi tạo'];
    }

    public function updateSystem($id, $data) {
        $allowed = array_intersect_key($data, array_flip(['name','code','description','color']));
        if(empty($allowed)) return ['success' => false, 'message' => 'Không có dữ liệu'];
        if(isset($allowed['code']) && $allowed['code']) {
            $stmt = $this->conn->prepare("SELECT id FROM systems WHERE code = ? AND id <> ?");
            $stmt->execute([$allowed['code'], $id]);
            if($stmt->fetchColumn()) return ['success' => false, 'message' => 'Code đã tồn tại'];
        }
        $set = [];
        foreach($allowed as $k => $v) $set[] = "$k = :$k";
        $stmt = $this->conn->prepare("UPDATE systems SET " . implode(',', $set) . " WHERE id = :id");
        foreach($allowed as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return ['success' => $stmt->execute()];
    }

    public function deleteSystem($id) {
        $stmt = $this->conn->prepare("DELETE FROM systems WHERE id = ?");
        return ['success' => $stmt->execute([$id])];
    }

    public function setAssignees($systemId, array $userIds) {
        $this->conn->beginTransaction();
        try {
            $del = $this->conn->prepare("DELETE FROM system_assignees WHERE system_id = ?");
            $del->execute([$systemId]);
            if(!empty($userIds)) {
                $ins = $this->conn->prepare("INSERT INTO system_assignees (system_id, user_id) VALUES (?, ?)");
                foreach($userIds as $uid) {
                    $uid = (int)$uid; if($uid <= 0) continue;
                    $ins->execute([$systemId, $uid]);
                }
            }
            $this->conn->commit();
            return ['success' => true];
        } catch(Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ───── Nodes (cây) ──────────────────────────────────────────
    public function getTree($systemId) {
        $stmt = $this->conn->prepare("
            SELECT id, system_id, parent_id, node_type, name, description, display_order
            FROM system_nodes WHERE system_id = ?
            ORDER BY display_order ASC, id ASC");
        $stmt->execute([$systemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createNode($systemId, $parentId, $type, $name, $description, $userId) {
        if(!in_array($type, ['module','feature','logic','hidden'])) {
            return ['success' => false, 'message' => 'Loại node không hợp lệ'];
        }
        $name = trim($name);
        if($name === '') return ['success' => false, 'message' => 'Cần nhập tên node'];

        // Tính display_order = max + 10 trong cùng parent
        $stmt = $this->conn->prepare("SELECT COALESCE(MAX(display_order), 0) + 10 FROM system_nodes WHERE system_id = ? AND " . ($parentId ? "parent_id = ?" : "parent_id IS NULL"));
        $params = [$systemId];
        if($parentId) $params[] = $parentId;
        $stmt->execute($params);
        $order = (int)$stmt->fetchColumn();

        $stmt = $this->conn->prepare("INSERT INTO system_nodes (system_id, parent_id, node_type, name, description, display_order, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ok = $stmt->execute([$systemId, $parentId ?: null, $type, $name, $description ?: null, $order, $userId]);
        return $ok ? ['success' => true, 'id' => $this->conn->lastInsertId()] : ['success' => false, 'message' => 'Lỗi khi tạo node'];
    }

    public function updateNode($nodeId, $data) {
        $allowed = array_intersect_key($data, array_flip(['name','description','node_type']));
        if(empty($allowed)) return ['success' => false, 'message' => 'Không có dữ liệu'];
        if(isset($allowed['node_type']) && !in_array($allowed['node_type'], ['module','feature','logic','hidden'])) {
            return ['success' => false, 'message' => 'Loại node không hợp lệ'];
        }
        $set = [];
        foreach($allowed as $k => $v) $set[] = "$k = :$k";
        $stmt = $this->conn->prepare("UPDATE system_nodes SET " . implode(',', $set) . " WHERE id = :id");
        foreach($allowed as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->bindValue(':id', $nodeId, PDO::PARAM_INT);
        return ['success' => $stmt->execute()];
    }

    public function deleteNode($nodeId) {
        // ON DELETE CASCADE sẽ tự xoá descendants
        $stmt = $this->conn->prepare("DELETE FROM system_nodes WHERE id = ?");
        return ['success' => $stmt->execute([$nodeId])];
    }

    /**
     * Đổi parent của 1 node — bảo đảm không tạo vòng (không thả vào descendant của chính nó).
     */
    public function reparentNode($nodeId, $newParentId) {
        $node = $this->getNode($nodeId);
        if(!$node) return ['success' => false, 'message' => 'Node không tồn tại'];

        if($newParentId !== null) {
            $newParent = $this->getNode($newParentId);
            if(!$newParent) return ['success' => false, 'message' => 'Parent mới không tồn tại'];
            if((int)$newParent['system_id'] !== (int)$node['system_id']) {
                return ['success' => false, 'message' => 'Không thể chuyển sang hệ thống khác'];
            }
            // Check cycle: newParentId không được là chính nó hoặc là descendant của nodeId
            if($this->isDescendantOf($newParentId, $nodeId)) {
                return ['success' => false, 'message' => 'Không thể đặt làm con của chính nó hoặc cháu của nó'];
            }
        }

        $stmt = $this->conn->prepare("UPDATE system_nodes SET parent_id = ? WHERE id = ?");
        return ['success' => $stmt->execute([$newParentId, $nodeId])];
    }

    private function isDescendantOf($candidateId, $ancestorId) {
        if((int)$candidateId === (int)$ancestorId) return true;
        $stmt = $this->conn->prepare("SELECT parent_id FROM system_nodes WHERE id = ?");
        $cur = $candidateId;
        $depth = 0;
        while($cur && $depth < 50) {
            $stmt->execute([$cur]);
            $pid = $stmt->fetchColumn();
            if(!$pid) return false;
            if((int)$pid === (int)$ancestorId) return true;
            $cur = $pid;
            $depth++;
        }
        return false;
    }

    public function getNode($nodeId) {
        $stmt = $this->conn->prepare("SELECT * FROM system_nodes WHERE id = ?");
        $stmt->execute([$nodeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
