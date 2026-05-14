<?php
/**
 * BASheetWebhook — Gửi webhook đến Google Apps Script khi task thay đổi.
 * Apps Script nhận POST → ghi/cập nhật vào BA Sheet realtime.
 */
require_once __DIR__ . '/../models/BotSettings.php';

class BASheetWebhook {
    private $db;
    private $webhookUrl = null;

    public function __construct($db) {
        $this->db = $db;
        $bs = new BotSettings($db);
        $cfg = $bs->get();
        $this->webhookUrl = $cfg['ba_webhook_url'] ?? null;
    }

    /** Gửi task lên BA Sheet (insert hoặc update) */
    public function syncTask($taskId, $tabName = 'Tổng quan') {
        if (!$this->webhookUrl) return;

        $task = $this->loadTask($taskId);
        if (!$task) return;

        $this->send([
            'action'   => 'upsert_task',
            'tab_name' => $tabName,
            'task'     => $task,
        ]);

        // Cũng ghi vào tab user (per-user tab)
        if (!empty($task['assignee_name'])) {
            $this->send([
                'action'   => 'upsert_task',
                'tab_name' => $task['assignee_name'],
                'task'     => $task,
            ]);
        }
    }

    /** Xóa task khỏi BA Sheet */
    public function deleteTask($maYc, $tabName = 'Tổng quan') {
        if (!$this->webhookUrl || !$maYc) return;
        $this->send([
            'action'   => 'delete_task',
            'ma_yc'    => $maYc,
            'tab_name' => $tabName,
        ]);
    }

    private function send($payload) {
        if (!$this->webhookUrl) return;

        $ch = curl_init($this->webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json; charset=UTF-8'],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 400) {
            error_log("BASheetWebhook error HTTP $code: $resp");
        }
    }

    private function loadTask($taskId) {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   u.full_name AS assignee_name,
                   d.full_name AS dev_name
            FROM tasks t
            LEFT JOIN users u ON t.assignee_id = u.id
            LEFT JOIN users d ON t.dev_id = d.id
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        return [
            'ma_yc'              => $row['ma_yc'] ?? '',
            'system_name'        => $row['system_name'] ?? '',
            'requester_name'     => $row['requester_name'] ?? '',
            'requester_dept'     => $row['requester_dept'] ?? '',
            'task_type'          => $row['task_type'] ?? '',
            'description'        => $row['description'] ?? '',
            'priority_requester' => $row['priority_requester'] ?? '',
            'priority_ba'        => $row['priority_ba'] ?? '',
            'status'             => $row['status'] ?? '',
            'assignee_name'      => $row['assignee_name'] ?? '',
            'start_date'         => $row['start_date'] ?? '',
            'expected_end_date'  => $row['expected_end_date'] ?? '',
            'actual_end_date'    => $row['actual_end_date'] ?? '',
            'dev_name'           => $row['dev_name'] ?? '',
            'dev_status'         => $row['dev_status'] ?? '',
            'implementing_unit'  => $row['implementing_unit'] ?? '',
            'classification'     => $row['classification'] ?? '',
            'office_link'        => $row['office_link'] ?? '',
            'created_at'         => $row['created_at'] ?? '',
        ];
    }
}
