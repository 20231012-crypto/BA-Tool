<?php
/**
 * TaskSyncService — đẩy danh sách task vào Google Sheet.
 * Mỗi nhân viên = 1 tab riêng. Có thêm tab "Tổng quan" tổng hợp tất cả.
 */
require_once __DIR__ . '/GoogleSheetsBot.php';
require_once __DIR__ . '/../models/BotSettings.php';

class TaskSyncService {
    private $db;

    /**
     * Map status nội bộ → giá trị ngắn gọn match với dropdown trong sheet đích.
     * Nếu sheet có data-validation dropdown thì giá trị này sẽ rơi đúng vào option.
     */
    const STATUS_MAP = [
        'Chờ tiếp nhận'         => 'Chờ tiếp nhận',
        'Todo - chờ xác nhận với Sếp' => 'Chờ duyệt',
        'Dion - đang xử lý'           => 'Đang xử lý',
        'Dion - Chờ nghiệm thu'       => 'Chờ nghiệm thu',
        'Kinkin nghiệm thu'           => 'Hoàn thành',
        'Huỷ'                          => 'Hủy',
    ];

    const DEV_STATUS_MAP = [
        'Chờ dev nhận' => 'Chờ nhận',
        'Dev đang làm' => 'Đang làm',
        'Dev đã xong'  => 'Đã xong',
        'Cần sửa'      => 'Cần sửa',
    ];

    public static function shortStatus($s) { return self::STATUS_MAP[$s] ?? ($s ?? ''); }
    public static function shortDevStatus($s) { return self::DEV_STATUS_MAP[$s] ?? ($s ?? ''); }

    const DAY_OF_WEEK_VI = [
        'Monday' => 'Thứ Hai', 'Tuesday' => 'Thứ Ba', 'Wednesday' => 'Thứ Tư',
        'Thursday' => 'Thứ Năm', 'Friday' => 'Thứ Sáu', 'Saturday' => 'Thứ Bảy',
        'Sunday' => 'Chủ Nhật'
    ];

    /** 19 header dành riêng cho tab của Dev (đồng bộ + xuất CSV) */
    const HEADER_DEV = [
        'Loại yêu cầu',                          //  1. task_type
        'Mã Yêu Cầu',                            //  2. ma_yc
        'Nội dung yêu cầu',                      //  3. ba_description ?: description
        'Người yêu cầu',                         //  4. requester_name
        'Người thực hiện',                       //  5. dev_name
        'Ngày thực hiện code',                   //  6. DATE(dev_start_at)
        'Ngày hoàn thành code',                  //  7. DATE(dev_end_at)
        'Ngày dự kiến hoàn thành',               //  8. dev_planned_end ?: dev_deadline
        'Trạng thái task của dev',               //  9. dev_status
        'Mức độ',                                // 10. priority_ba ?: priority_requester
        'Người thực hiện test',                  // 11. tester_name
        'Ngày test',                             // 12. test_date
        'Trạng thái test',                       // 13. test_status
        'Ghi chú',                               // 14. dev_notes
        'Thời gian bắt đầu code',                // 15. dev_start_at full
        'Thời gian kết thúc code',               // 16. dev_end_at full
        'Thời gian BA yêu cầu',                  // 17. ba_submission_date
        'Thời gian nghiệm thu từ nhân viên',     // 18. acceptance_date
        'Ngày Dev thực hiện yêu cầu',            // 19. dev_actual_day
    ];

    /** 30 header chuẩn theo template Google Sheet user yêu cầu (đã bỏ Tuần/Tháng) */
    const HEADER_32 = [
        'Thời gian nhân viên đưa yêu cầu',
        'Tên hệ thống',
        'Mô tả yêu cầu nhân viên',
        'Tên người yêu cầu',
        'Mức độ ưu tiên ( Nhân viên tự đánh giá)',
        'Thời gian bắt đầu ( Nhân viên tự ước tính )',
        'Thời gian kết thúc ( Nhân viên tự ước tính )',
        'File upload đính kèm (nếu có)',
        'Link công việc 1Office',
        'Phòng ban',
        'Loại yêu cầu',
        'Mã Yêu Cầu',
        'Tên Module',
        'Tính năng',
        'Mô tả yêu cầu (BA)',
        'Mức độ ưu tiên ( BA đánh giá )',
        'Phân loại yêu cầu',
        'Trạng thái hoàn thành',
        'Thời gian bắt đầu ( BA ước tính )',
        'Thời gian kết thúc ( BA ước tính )',
        'Ngày BA đưa YC',
        'Thời gian bắt đầu (thực tế code)',
        'Thời gian kết thúc (thực tế code)',
        'Ngày nghiệm thu',
        'Kết quả delay (h)',
        'Trạng thái delay',
        'BA thực hiện YC',
        'Đơn vị thực hiện',
        'Ngày trong tuần Dev thực hiện thực tế',
        'Trạng thái Dev hoàn thành',
    ];

    public function __construct($db) { $this->db = $db; }

    /**
     * Chạy đồng bộ. Trả về mảng kết quả: ['success'=>bool, 'message'=>string, 'tabs_synced'=>int]
     */
    public function runSync() {
        $bs = new BotSettings($this->db);
        $cfg = $bs->get();
        if(empty($cfg['enabled'])) {
            return ['success' => false, 'message' => 'Bot đang bị tắt'];
        }
        if(empty($cfg['sheet_id'])) {
            return ['success' => false, 'message' => 'Chưa cấu hình link Google Sheet'];
        }
        $credPath = $cfg['credentials_path'] ?: 'config/google-credentials.json';
        $absCred  = __DIR__ . '/../' . $credPath;
        if(!getenv('GOOGLE_CREDENTIALS_JSON') && !file_exists($absCred)) {
            $bs->recordSyncResult('failed', "Credentials file not found: $credPath");
            return ['success' => false, 'message' => "Không tìm thấy file credentials: $credPath"];
        }

        try {
            $bot = new GoogleSheetsBot($absCred, $cfg['sheet_id']);

            // 1) Lấy danh sách user (lead, ba) + danh sách task
            // Lưu ý: KHÔNG sync per-dev tab vào sheet cũ — Dev quản lý qua sheet mới (DevSheetService).
            $users = $this->db->query("SELECT id, full_name, username, role FROM users WHERE role IN ('lead','ba') ORDER BY FIELD(role,'lead','ba'), full_name")
                              ->fetchAll(PDO::FETCH_ASSOC);

            $allTasks = $this->db->query("
                SELECT t.*,
                       u.full_name  AS assignee_name,
                       d.full_name  AS dev_name,
                       te.full_name AS tester_name,
                       m.name       AS module_name,
                       sys.name     AS sys_name,
                       mn.name      AS module_node_name,
                       fn.name      AS feature_node_name,
                       CASE WHEN t.actual_end_date IS NULL THEN NULL
                            ELSE ROUND(TIMESTAMPDIFF(MINUTE, t.expected_end_date, t.actual_end_date) / 60, 2)
                       END AS delay_hours,
                       CASE WHEN t.actual_end_date IS NULL THEN NULL
                            WHEN t.actual_end_date <= t.expected_end_date THEN 'Đúng hạn'
                            ELSE 'Quá hạn'
                       END AS delay_status
                FROM tasks t
                LEFT JOIN users u   ON t.assignee_id = u.id
                LEFT JOIN users d   ON t.dev_id      = d.id
                LEFT JOIN users te  ON t.tester_id   = te.id
                LEFT JOIN modules m ON t.module_id   = m.id
                LEFT JOIN systems sys      ON t.system_id       = sys.id
                LEFT JOIN system_nodes mn  ON t.module_node_id  = mn.id
                LEFT JOIN system_nodes fn  ON t.feature_node_id = fn.id
                ORDER BY t.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            $tabsSynced = 0;

            // Header chuẩn 30 cột — match đúng template Google Sheet (đã bỏ Tuần/Tháng)
            $header32 = self::HEADER_32;

            // 2) Tab "Tổng quan" — toàn bộ task
            $rowsAll = [$header32];
            foreach($allTasks as $t) {
                $rowsAll[] = $this->build32($t);
            }
            $bot->overwriteTab('Tổng quan', $rowsAll);
            $tabsSynced++;

            // 3) Tab cho từng user BA/Lead — luôn 30 cột HEADER_32 chuẩn
            foreach($users as $u) {
                $tabName = GoogleSheetsBot::safeTabName($u['full_name'] ?: $u['username'], 'User_' . $u['id']);
                $userTasks = array_filter($allTasks, function($t) use ($u) {
                    return (int)$t['assignee_id'] === (int)$u['id'];
                });

                $colCount = count($header32);
                $rows = [
                    array_merge(
                        ['Nhân viên: ' . ($u['full_name'] ?: $u['username']), 'Vai trò: ' . $u['role']],
                        array_fill(0, $colCount - 4, ''),
                        ['Cập nhật:', date('Y-m-d H:i:s')]
                    ),
                    [],
                    $header32
                ];
                foreach($userTasks as $t) {
                    $rows[] = $this->build32($t);
                }
                if(empty($userTasks)) {
                    $rows[] = array_merge(['(Chưa có task nào được giao)'], array_fill(0, $colCount - 1, ''));
                }
                $bot->overwriteTab($tabName, $rows);
                $tabsSynced++;
            }

            // 4) Dọn tab thừa: xoá các tab không còn match user lead/ba hiện tại
            $deletedTabs = [];
            try {
                $validTabs = ['Tổng quan' => true];
                foreach($users as $u) {
                    $validTabs[GoogleSheetsBot::safeTabName($u['full_name'] ?: $u['username'], 'User_' . $u['id'])] = true;
                }
                $allTabs = $bot->listTabs();
                foreach($allTabs as $tabName => $sheetId) {
                    if(isset($validTabs[$tabName])) continue;
                    if($sheetId === null) continue;
                    try {
                        $bot->deleteTab($sheetId);
                        $deletedTabs[] = $tabName;
                    } catch(Exception $e) {
                        // bỏ qua tab xoá lỗi, ghi vào message để debug
                        $deletedTabs[] = $tabName . '(FAILED: ' . $e->getMessage() . ')';
                    }
                }
            } catch(Exception $e) {
                // cleanup không thành công cũng không fail toàn bộ sync
            }

            $bs->recordSyncResult('success', null);
            $msg = "Đồng bộ xong: $tabsSynced tab";
            if(!empty($deletedTabs)) $msg .= '; đã xoá ' . count($deletedTabs) . ' tab cũ: ' . implode(', ', $deletedTabs);
            return ['success' => true, 'message' => $msg, 'tabs_synced' => $tabsSynced, 'tabs_deleted' => $deletedTabs];
        } catch(Exception $e) {
            $bs->recordSyncResult('failed', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Build 30-column row matching the Google Sheet template exactly (đã bỏ Tuần/Tháng).
     */
    private function build32($t) {
        // Tên Module: ưu tiên system_nodes (mới), fallback modules (legacy)
        $moduleName = $t['module_node_name'] ?: ($t['module_name'] ?: '');
        // Tính năng: ưu tiên system_nodes feature node, fallback feature varchar
        $featureName = $t['feature_node_name'] ?: ($t['feature'] ?: '');
        // Tên hệ thống: ưu tiên systems table (sys_name), fallback system_name từ form
        $systemName = $t['sys_name'] ?: ($t['system_name'] ?: '');

        // Day of week của Dev khi bấm bắt đầu — ưu tiên dev_actual_day nhập tay
        $devDay = '';
        if(!empty($t['dev_actual_day'])) {
            $devDay = $t['dev_actual_day'];
        } elseif(!empty($t['dev_start_at'])) {
            $en = date('l', strtotime($t['dev_start_at']));
            $devDay = self::DAY_OF_WEEK_VI[$en] ?? $en;
        }

        return [
            $this->fmtDateTime($t['created_at'] ?? null),                 //  1. Thời gian nhân viên đưa YC
            $systemName,                                                    //  2. Tên hệ thống
            $t['description'] ?? '',                                        //  3. Mô tả yêu cầu nhân viên
            $t['requester_name'] ?? '',                                     //  4. Tên người yêu cầu
            $t['priority_requester'] ?? '',                                 //  5. Ưu tiên (NV)
            $this->fmtDateTime($t['start_date'] ?? null),                   //  6. TG bắt đầu (NV)
            $this->fmtDateTime($t['expected_end_date'] ?? null),            //  7. TG kết thúc (NV)
            $t['attachment_url'] ?? '',                                     //  8. File đính kèm
            $t['office_link'] ?? '',                                        //  9. Link 1Office
            $t['requester_dept'] ?? '',                                     // 10. Phòng ban
            $t['task_type'] ?? '',                                          // 11. Loại YC
            $t['ma_yc'] ?? ('#' . $t['id']),                                // 12. Mã YC
            $moduleName,                                                    // 13. Tên Module
            $featureName,                                                   // 14. Tính năng
            $t['ba_description'] ?? '',                                     // 15. Mô tả BA
            $t['priority_ba'] ?? '',                                        // 16. Ưu tiên BA
            $t['classification'] ?? '',                                     // 17. Phân loại
            self::shortStatus($t['status'] ?? ''),                          // 18. Trạng thái hoàn thành
            $this->fmtDateTime($t['ba_start_date'] ?? null),                // 19. TG bắt đầu (BA ước tính)
            $this->fmtDateTime($t['ba_end_date'] ?? null),                  // 20. TG kết thúc (BA ước tính)
            $this->fmtDate($t['ba_submission_date'] ?? null),               // 21. Ngày BA đưa YC
            $this->fmtDateTime($t['actual_start_datetime'] ?? null),        // 22. Bắt đầu thực tế code
            $this->fmtDateTime($t['actual_end_date'] ?? null),              // 23. Kết thúc thực tế code
            $this->fmtDate($t['acceptance_date'] ?? null),                  // 24. Ngày nghiệm thu
            ($t['delay_hours'] ?? '') === '' || $t['delay_hours'] === null ? '' : (string)$t['delay_hours'], // 25. Delay (h)
            $t['delay_status'] ?? '',                                       // 26. Trạng thái delay
            $t['assignee_name'] ?? '',                                      // 27. BA thực hiện YC
            $t['implementing_unit'] ?? '',                                  // 28. Đơn vị thực hiện
            $devDay,                                                        // 29. Ngày trong tuần Dev
            self::shortDevStatus($t['dev_status'] ?? ''),                   // 30. Trạng thái Dev
        ];
    }

    /**
     * Build 19-column row dành riêng cho tab Dev — match HEADER_DEV.
     */
    private function build19Dev($t) {
        // Người yêu cầu: ưu tiên người trên form gốc
        $requester = $t['requester_name'] ?? '';
        // Nội dung yêu cầu: ưu tiên BA mô tả lại (cho Dev), fallback description gốc
        $content = $t['ba_description'] ?: ($t['description'] ?? '');
        // Mức độ: ưu tiên BA đánh giá, fallback NV
        $priority = $t['priority_ba'] ?: ($t['priority_requester'] ?? '');
        // Dev day of week: ưu tiên text nhập tay, fallback derive từ dev_start_at
        $devDay = '';
        if(!empty($t['dev_actual_day'])) {
            $devDay = $t['dev_actual_day'];
        } elseif(!empty($t['dev_start_at'])) {
            $en = date('l', strtotime($t['dev_start_at']));
            $devDay = self::DAY_OF_WEEK_VI[$en] ?? $en;
        }

        return [
            $t['task_type']           ?? '',                                  //  1. Loại yêu cầu
            $t['ma_yc']               ?? ('#' . $t['id']),                    //  2. Mã Yêu Cầu
            $content,                                                          //  3. Nội dung yêu cầu
            $requester,                                                        //  4. Người yêu cầu
            $t['dev_name']            ?? '',                                  //  5. Người thực hiện (Dev)
            $this->fmtDate($t['dev_start_at'] ?? null),                       //  6. Ngày thực hiện code
            $this->fmtDate($t['dev_end_at']   ?? null),                       //  7. Ngày hoàn thành code
            $this->fmtDate($t['dev_planned_end'] ?? ($t['dev_deadline'] ?? null)), //  8. Ngày dự kiến hoàn thành
            self::shortDevStatus($t['dev_status'] ?? ''),                     //  9. Trạng thái task của dev
            $priority,                                                         // 10. Mức độ
            $t['tester_name']         ?? '',                                  // 11. Người thực hiện test
            $this->fmtDate($t['test_date'] ?? null),                          // 12. Ngày test
            $t['test_status']         ?? '',                                  // 13. Trạng thái test
            $t['dev_notes']           ?? '',                                  // 14. Ghi chú
            $this->fmtDateTime($t['dev_start_at'] ?? null),                   // 15. Thời gian bắt đầu code
            $this->fmtDateTime($t['dev_end_at']   ?? null),                   // 16. Thời gian kết thúc code
            $this->fmtDate($t['ba_submission_date'] ?? null),                 // 17. Thời gian BA yêu cầu
            $this->fmtDate($t['acceptance_date']    ?? null),                 // 18. Thời gian nghiệm thu từ nhân viên
            $devDay,                                                           // 19. Ngày Dev thực hiện yêu cầu
        ];
    }

    private function fmtDateTime($d) {
        if(!$d) return '';
        $ts = strtotime($d);
        if(!$ts) return $d;
        return date('d/m/Y H:i', $ts);
    }

    private function buildRow($t, $forOverview = false) {
        $delay = '';
        if(!empty($t['actual_end_date']) && !empty($t['expected_end_date'])) {
            $diff = (strtotime($t['actual_end_date']) - strtotime($t['expected_end_date'])) / 3600;
            $delay = round($diff, 2) . 'h (' . ($t['delay_status'] ?? '') . ')';
        }
        return [
            $t['ma_yc'] ?? ('#'.$t['id']),
            $t['system_name'] ?? '',
            $t['module_name'] ?? '',
            $t['requester_name'] ?? '',
            $t['requester_dept'] ?? '',
            $t['task_type'] ?? '',
            $t['priority_ba'] ?? $t['priority_requester'] ?? '',
            $t['assignee_name'] ?? '',
            $t['dev_name'] ?? '',
            self::shortStatus($t['status'] ?? ''),
            self::shortDevStatus($t['dev_status'] ?? ''),
            $this->fmtDate($t['expected_end_date'] ?? null),
            $this->fmtDate($t['actual_start_datetime'] ?? null),
            $this->fmtDate($t['actual_end_date'] ?? null),
            $delay,
            $t['description'] ?? ''
        ];
    }

    private function buildUserRow($t, $role) {
        $delay = '';
        if(!empty($t['actual_end_date']) && !empty($t['expected_end_date'])) {
            $diff = (strtotime($t['actual_end_date']) - strtotime($t['expected_end_date'])) / 3600;
            $delay = round($diff, 2) . 'h (' . ($t['delay_status'] ?? '') . ')';
        }
        $partner = ($role === 'dev') ? ($t['assignee_name'] ?? '') : ($t['dev_name'] ?? '');
        return [
            $t['ma_yc'] ?? ('#'.$t['id']),
            $t['system_name'] ?? '',
            $t['module_name'] ?? '',
            $t['task_type'] ?? '',
            $t['priority_ba'] ?? $t['priority_requester'] ?? '',
            $partner,
            self::shortStatus($t['status'] ?? ''),
            self::shortDevStatus($t['dev_status'] ?? ''),
            $this->fmtDate($t['expected_end_date'] ?? null),
            $this->fmtDate($t['actual_start_datetime'] ?? null),
            $this->fmtDate($t['actual_end_date'] ?? null),
            $delay,
            $t['description'] ?? ''
        ];
    }

    private function fmtDate($d) {
        if(!$d) return '';
        $ts = strtotime($d);
        if(!$ts) return $d;
        return date('d/m/Y', $ts);
    }
}
?>
