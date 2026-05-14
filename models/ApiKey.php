<?php
class ApiKey {
    private $conn;

    public function __construct($db) { $this->conn = $db; }

    public function generateToken() {
        return bin2hex(random_bytes(32)); // 64 chars hex
    }

    public function create($name, $methods, $createdBy) {
        $token = $this->generateToken();
        $stmt = $this->conn->prepare(
            "INSERT INTO api_keys (name, token, methods, created_by) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$name, $token, $methods, $createdBy]);
        return ['id' => $this->conn->lastInsertId(), 'token' => $token, 'name' => $name, 'methods' => $methods];
    }

    public function getAll() {
        return $this->conn->query(
            "SELECT ak.*, u.full_name AS creator_name FROM api_keys ak LEFT JOIN users u ON ak.created_by = u.id ORDER BY ak.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $stmt = $this->conn->prepare("SELECT * FROM api_keys WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /** Validate token + method, return key row or false */
    public function authenticate($token, $method) {
        $stmt = $this->conn->prepare("SELECT * FROM api_keys WHERE token = ? AND is_active = 1");
        $stmt->execute([$token]);
        $key = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$key) return false;

        $allowed = strtoupper($key['methods']);
        if ($allowed !== 'ALL' && strpos($allowed, strtoupper($method)) === false) return false;

        // Update usage stats
        $this->conn->prepare("UPDATE api_keys SET last_used_at = NOW(), request_count = request_count + 1 WHERE id = ?")
            ->execute([$key['id']]);
        return $key;
    }

    public function toggleActive($id, $active) {
        $stmt = $this->conn->prepare("UPDATE api_keys SET is_active = ? WHERE id = ?");
        return $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM api_keys WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function regenerateToken($id) {
        $token = $this->generateToken();
        $stmt = $this->conn->prepare("UPDATE api_keys SET token = ? WHERE id = ?");
        $stmt->execute([$token, $id]);
        return $token;
    }
}
