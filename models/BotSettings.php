<?php
class BotSettings {
    private $conn;
    private $table = "bot_settings";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function get() {
        $row = $this->conn->query("SELECT * FROM {$this->table} WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        if(!$row) {
            $this->conn->exec("INSERT INTO {$this->table} (id) VALUES (1)");
            $row = $this->conn->query("SELECT * FROM {$this->table} WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
        }
        return $row;
    }

    /**
     * Trích sheet ID từ URL google sheets
     */
    public static function extractSheetId($url) {
        if(!$url) return null;
        if(preg_match('#/spreadsheets/d/([a-zA-Z0-9-_]+)#', $url, $m)) return $m[1];
        return $url; // Cho phép truyền thẳng ID
    }

    public function save($data) {
        $allowed = array_intersect_key($data, array_flip([
            'sheet_url','sheet_id','bot_email','credentials_path',
            'schedule_hour','schedule_minute','enabled',
            'dev_sheet_url','dev_sheet_id',
            'poller_enabled','poller_interval',
            'ba_webhook_url'
        ]));
        if(empty($allowed)) return ['success' => false, 'message' => 'Không có dữ liệu'];

        // Tự extract sheet_id từ URL nếu chưa có
        if(!empty($allowed['sheet_url']) && empty($allowed['sheet_id'])) {
            $allowed['sheet_id'] = self::extractSheetId($allowed['sheet_url']);
        }

        $set = [];
        foreach($allowed as $k => $v) $set[] = "$k = :$k";
        $sql = "UPDATE {$this->table} SET " . implode(',', $set) . " WHERE id = 1";
        $stmt = $this->conn->prepare($sql);
        foreach($allowed as $k => $v) $stmt->bindValue(':' . $k, $v);
        return ['success' => $stmt->execute()];
    }

    public function recordSyncResult($status, $error = null) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET last_sync_at = NOW(), last_sync_status = ?, last_sync_error = ? WHERE id = 1");
        return $stmt->execute([$status, $error]);
    }
}
?>
