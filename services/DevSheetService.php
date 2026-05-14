<?php
/**
 * DevSheetService — đồng bộ 2 chiều với Google Sheet riêng cho Dev workflow.
 *
 * Sheet structure (HEADER_DEV — 19 cột):
 *   0  Loại yêu cầu                    | task_type
 *   1  Mã Yêu Cầu                      | ma_yc           ← KEY để match row
 *   2  Nội dung yêu cầu                | ba_description ?: description
 *   3  Người yêu cầu                   | BA nickname
 *   4  Người thực hiện                 | Dev nickname
 *   5  Ngày thực hiện code             | DATE(dev_start_at)
 *   6  Ngày hoàn thành code            | DATE(dev_end_at)
 *   7  Ngày dự kiến hoàn thành         | dev_planned_end ?: dev_deadline
 *   8  Trạng thái task của dev         | dev_status      ← Dev tự sửa, bot poll về
 *   9  Mức độ                          | priority_ba ?: priority_requester
 *   10 Người thực hiện test            | tester_nickname
 *   11 Ngày test                       | test_date
 *   12 Trạng thái test                 | test_status     ← BA sửa qua hệ thống, bot ghi vào sheet
 *   13 Ghi chú                         | dev_notes
 *   14 Thời gian bắt đầu code (datetime)
 *   15 Thời gian kết thúc code (datetime)
 *   16 Thời gian BA yêu cầu (datetime)
 *   17 Thời gian nghiệm thu từ nhân viên (datetime)
 *   18 Ngày Dev thực hiện yêu cầu      | DATE(ba_submission_date hoặc dev_start_at)
 *
 * Dev status (sheet) → DB mapping:
 *   todo                 → Chờ dev nhận
 *   đang làm             → Dev đang làm
 *   hoàn thành           → Dev đã xong
 *   hủy                  → Huỷ (bao trùm task chính)
 *   cần sửa lại          → Cần sửa
 *   (giá trị khác — giữ nguyên text)
 *
 * Tab tuần regex chỉ xử lý: ^\s*\d{1,2}/\d{1,2}\s*[-–—]\s*\d{1,2}/\d{1,2}\s*$
 */
require_once __DIR__ . '/GoogleSheetsBot.php';
require_once __DIR__ . '/../models/BotSettings.php';

class DevSheetService {
    private $db;
    private $bot = null;

    /** Header sheet 20 cột. Phải khớp đúng thứ tự với sheet. */
    const HEADER = [
        'Loại yêu cầu',
        'Mã Yêu Cầu',
        'Nội dung yêu cầu',
        'Người yêu cầu',
        'Người thực hiện',
        'Ngày thực hiện code',
        'Ngày hoàn thành code',
        'Ngày dự kiến hoàn thành',
        'Trạng thái task của dev',
        'Mức độ',
        'Người thực hiện test',
        'Ngày test',
        'Trạng thái test',
        'Ghi chú',
        'Thời gian bắt đầu code',
        'Thời gian kết thúc code',
        'Thời gian BA yêu cầu',
        'Thời gian nghiệm thu từ nhân viên',
        'Ngày Dev thực hiện yêu cầu',
        'BA ước tính',
    ];

    const COL_TASK_TYPE       = 0;
    const COL_MA_YC           = 1;
    const COL_CONTENT         = 2;
    const COL_REQUESTER       = 3;
    const COL_PERFORMER       = 4;
    const COL_DATE_START_CODE = 5;
    const COL_DATE_END_CODE   = 6;
    const COL_DATE_PLANNED    = 7;
    const COL_DEV_STATUS      = 8;
    const COL_PRIORITY        = 9;
    const COL_TESTER          = 10;
    const COL_TEST_DATE       = 11;
    const COL_TEST_STATUS     = 12;
    const COL_NOTES           = 13;
    const COL_TIME_START_CODE = 14;
    const COL_TIME_END_CODE   = 15;
    const COL_TIME_BA_REQUEST = 16;
    const COL_TIME_NV_ACCEPT  = 17;
    const COL_DEV_DAY         = 18;
    const COL_BA_ESTIMATE     = 19;

    const DEV_STATUS_DB_TO_SHEET = [
        'Chờ dev nhận' => 'todo',
        'Dev đang làm' => 'đang làm',
        'Dev đã xong'  => 'hoàn thành',
        'Cần sửa'      => 'cần sửa lại',
    ];
    const DEV_STATUS_SHEET_TO_DB = [
        'todo'           => 'Chờ dev nhận',
        'đang làm'       => 'Dev đang làm',
        'hoàn thành'     => 'Dev đã xong',
        'cần sửa lại'    => 'Cần sửa',
        'fix review'     => 'Cần sửa',
        'pending'        => 'Chờ dev nhận',
    ];

    public function __construct($db) { $this->db = $db; }

    private function getBot() {
        if($this->bot) return $this->bot;
        $bs  = new BotSettings($this->db);
        $cfg = $bs->get();
        $sheetId = $cfg['dev_sheet_id'] ?? null;
        if(!$sheetId) throw new Exception('Chưa cấu hình dev_sheet_id trong bot_settings');
        $credPath = $cfg['credentials_path'] ?: 'config/google-credentials.json';
        $absCred  = __DIR__ . '/../' . $credPath;
        if(!getenv('GOOGLE_CREDENTIALS_JSON') && !file_exists($absCred)) throw new Exception("Không tìm thấy credentials: $credPath");
        $this->bot = new GoogleSheetsBot($absCred, $sheetId);
        return $this->bot;
    }

    // ============================================================
    // TAB NAMING / WEEK LOGIC
    // ============================================================

    /**
     * Tính tên tab cho tuần chứa $date (T2-T6, format "DD/MM - DD/MM").
     * Ví dụ ngày 12/05/2026 (T3) → "11/05 - 15/05".
     */
    public static function weekTabName($date = null) {
        $ts = $date ? strtotime($date) : time();
        $dow = (int)date('N', $ts);          // 1=Mon, 7=Sun
        $monTs = strtotime("-" . ($dow - 1) . " days", $ts);
        $friTs = strtotime("+4 days", $monTs);
        return sprintf('%02d/%02d - %02d/%02d',
            (int)date('d', $monTs), (int)date('m', $monTs),
            (int)date('d', $friTs), (int)date('m', $friTs)
        );
    }

    /** Regex tab tuần để filter — match 04/05 - 08/05, 1/12 - 5/12, etc. */
    public static function isWeekTab($tabName) {
        return (bool)preg_match('#^\s*\d{1,2}/\d{1,2}\s*[\-–—]\s*\d{1,2}/\d{1,2}\s*$#u', $tabName);
    }

    /**
     * Tìm tabName matching cho một date arbitrary trong list tabs hiện có.
     * Match nếu date nằm trong khoảng [start, end] của tab.
     * Trả về tabName hoặc null.
     */
    public function findTabForDate($date, $availableTabs) {
        $ts = strtotime($date);
        if(!$ts) return null;
        $year = (int)date('Y', $ts);
        foreach($availableTabs as $name) {
            if(!self::isWeekTab($name)) continue;
            if(!preg_match('#(\d{1,2})/(\d{1,2})\s*[\-–—]\s*(\d{1,2})/(\d{1,2})#u', $name, $m)) continue;
            $start = strtotime(sprintf('%04d-%02d-%02d', $year, (int)$m[2], (int)$m[1]));
            $end   = strtotime(sprintf('%04d-%02d-%02d 23:59:59', $year, (int)$m[4], (int)$m[3]));
            // Edge case: tuần vắt sang năm (29/12 - 09/01) → end < start, fix
            if($end < $start) $end = strtotime('+1 year', $end);
            if($ts >= $start && $ts <= $end) return $name;
        }
        return null;
    }

    /**
     * Đảm bảo tab tuần hiện tại tồn tại. Nếu chưa, duplicate từ tab tuần gần nhất
     * (giữ nguyên format/dropdown), clear data rows. Trả về tabName.
     */
    public function ensureCurrentWeekTab() {
        $bot = $this->getBot();
        $target = self::weekTabName();
        $tabs = $bot->listTabs(); // [name => sheetId]
        if(isset($tabs[$target])) return $target;

        // Tìm tab tuần GẦN NHẤT làm template
        $weekTabs = [];
        foreach($tabs as $n => $sid) if(self::isWeekTab($n)) $weekTabs[$n] = $sid;
        if(!$weekTabs) {
            // Fallback: tạo tab trống với header
            $bot->ensureTab($target);
            $bot->updateValues($target . '!A1', [self::HEADER]);
            return $target;
        }

        // Chọn tab tuần GẦN NHẤT (tab đầu trong danh sách, thường là tuần trước)
        // Google Sheets list tabs theo thứ tự vị trí, tab mới nhất thường ở đầu
        $sourceTabName = null;
        $sourceSheetId = null;
        foreach ($weekTabs as $n => $sid) {
            $sourceTabName = $n;
            $sourceSheetId = $sid;
            break;
        }
        $bot->duplicateTab($sourceSheetId, $target);
        // Clear data rows (giữ header)
        try { $bot->clearDataRows($target); } catch(Exception $e) { /* ignore */ }
        return $target;
    }

    // ============================================================
    // WRITE OUT — Khi BA bấm "Bắt đầu code"
    // ============================================================

    /**
     * Append task vào tab tuần hiện tại của dev sheet.
     * Trả về [tab => string, row => int (1-based, dòng vừa append)].
     */
    public function writeTaskToSheet($taskId) {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $task = $this->loadTaskWithJoins($taskId);
        if(!$task) throw new Exception("Không tìm thấy task #$taskId");

        $bot = $this->getBot();
        $tabName = $this->ensureCurrentWeekTab();

        // Kiểm tra mã YC đã tồn tại trên sheet chưa
        $maYc = $task['ma_yc'] ?? ('#' . $task['id']);
        $existingRows = $bot->getValues("'" . $tabName . "'!B2:B5000");
        foreach ($existingRows as $r) {
            if (isset($r[0]) && trim($r[0]) === $maYc) {
                throw new Exception("Mã YC $maYc đã tồn tại trên sheet tab '$tabName'");
            }
        }

        // Build row — chỉ ghi thông tin cơ bản + ngày dự kiến, KHÔNG ghi các cột ngày khác
        $row = $this->buildRow($task, [
            'force_dev_status' => 'todo',
            'time_ba_request'  => date('d/m/Y H:i'),
            'dev_day'          => date('d/m/Y H:i'),
        ]);

        // Append
        $resp = $bot->appendValues("'" . $tabName . "'!A1", [$row]);
        // Parse updatedRange "Tổng quan!A123:S123" → row 123
        $updRange = $resp['updates']['updatedRange'] ?? '';
        $rowNum = null;
        if(preg_match('#![A-Z]+(\d+):#', $updRange, $m)) $rowNum = (int)$m[1];

        // Cache lại vị trí trong DB để poll/update sau không cần search
        if($rowNum) {
            $stmt = $this->db->prepare("UPDATE tasks SET sheet_tab = ?, sheet_row = ? WHERE id = ?");
            $stmt->execute([$tabName, $rowNum, $taskId]);
        }
        return ['tab' => $tabName, 'row' => $rowNum];
    }

    /** Cập nhật cột Trạng thái test (col 13 = M) hoặc Người thực hiện test (col 11 = K), ngày test (col 12 = L). */
    public function updateTestStatus($taskId, $testStatus, $testerNickname = null, $testDate = null) {
        $task = $this->loadTaskWithJoins($taskId);
        if(!$task || empty($task['sheet_tab']) || empty($task['sheet_row'])) {
            // Task chưa từng được ghi vào sheet → ghi mới
            return $this->writeTaskToSheet($taskId);
        }
        $bot = $this->getBot();
        $row = (int)$task['sheet_row'];
        $tab = $task['sheet_tab'];

        // Col K (11) = tester, Col L (12) = test_date, Col M (13) = test_status
        $values = [[
            $testerNickname !== null ? $testerNickname : ($task['tester_nickname'] ?? ''),
            $testDate ?? ($task['test_date'] ? date('d/m/Y', strtotime($task['test_date'])) : ''),
            $testStatus ?? '',
        ]];
        $bot->updateValues("'$tab'!K$row:M$row", $values);
        return ['tab' => $tab, 'row' => $row];
    }

    /** Khi phát hiện lỗi → ghi sheet: dev_status=cần sửa lại, append ghi chú, reset test_status. */
    public function logBugAndReset($taskId, $bugDescription, $reporterNickname) {
        $task = $this->loadTaskWithJoins($taskId);
        if(!$task || empty($task['sheet_tab']) || empty($task['sheet_row'])) return null;
        $bot = $this->getBot();
        $row = (int)$task['sheet_row'];
        $tab = $task['sheet_tab'];

        // Lấy ghi chú hiện tại để append
        $existing = $bot->getValues("'$tab'!N$row:N$row");
        $oldNote = isset($existing[0][0]) ? (string)$existing[0][0] : '';
        $stamp = date('d/m/Y H:i');
        $newNote = trim($oldNote . "\n[$stamp – $reporterNickname báo lỗi] " . $bugDescription);

        // Col I (9) = dev_status, Col M (13) = test_status, Col N (14) = ghi chú
        // Ghi 3 cells riêng biệt
        $bot->updateValues("'$tab'!I$row:I$row", [['cần sửa lại']]);
        $bot->updateValues("'$tab'!M$row:M$row", [['']]);  // reset test status về trống
        $bot->updateValues("'$tab'!N$row:N$row", [[$newNote]]);
        return ['tab' => $tab, 'row' => $row];
    }

    // ============================================================
    // POLL IN — Đọc tab tuần hiện tại, cập nhật DB
    // ============================================================

    /**
     * Poll all tabs that contain tracked tasks (sheet_tab IS NOT NULL),
     * compare dev_status với DB, apply changes vào DB.
     * Trả về stats.
     */
    public function pollChanges() {
        $bot = $this->getBot();
        $stats = ['scanned' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        // Chỉ poll các tab tuần mà DB có task tracked
        $tabsToPoll = $this->db->query("
            SELECT DISTINCT sheet_tab
            FROM tasks
            WHERE sheet_tab IS NOT NULL AND sheet_row IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN);

        // Plus current week tab (catch task vừa-được-Dev-thêm)
        $currentTab = self::weekTabName();
        if(!in_array($currentTab, $tabsToPoll)) $tabsToPoll[] = $currentTab;

        // Lấy danh sách tab thật trên sheet để skip tab chưa tồn tại
        try {
            $existingTabs = $bot->listTabs();
        } catch(Exception $e) {
            $stats['errors'][] = "Không lấy được danh sách tab: " . $e->getMessage();
            return $stats;
        }

        foreach($tabsToPoll as $tabName) {
            if(!self::isWeekTab($tabName)) continue;
            if(!isset($existingTabs[$tabName])) continue; // Tab chưa tồn tại → skip
            try {
                $rows = $bot->getValues("'$tabName'!A1:S5000");
            } catch(Exception $e) {
                $stats['errors'][] = "Tab '$tabName': " . $e->getMessage();
                continue;
            }
            // Bỏ header (row 0)
            for($i = 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                $stats['scanned']++;
                $maYc = isset($r[self::COL_MA_YC]) ? trim((string)$r[self::COL_MA_YC]) : '';
                if($maYc === '') { $stats['skipped']++; continue; }

                try {
                    $applied = $this->reconcileRow($maYc, $tabName, $i + 1, $r);
                    if($applied) $stats['updated']++; else $stats['skipped']++;
                } catch(Exception $e) {
                    $stats['errors'][] = "$maYc @ '$tabName' row " . ($i+1) . ": " . $e->getMessage();
                }
            }
        }
        // Lưu last poll time
        $this->db->exec("UPDATE bot_settings SET last_sync_at = NOW(), last_sync_status = 'success' WHERE id = 1");
        return $stats;
    }

    /**
     * Đối chiếu 1 row sheet với task DB. Apply diff vào DB.
     * Trả về true nếu có thay đổi.
     */
    private function reconcileRow($maYc, $tabName, $rowNum, $sheetRow) {
        $stmt = $this->db->prepare("SELECT * FROM tasks WHERE ma_yc = ? LIMIT 1");
        $stmt->execute([$maYc]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$task) return false; // Task chưa tồn tại trong DB → bỏ qua (không tự tạo từ poll)

        // Update sheet_tab/sheet_row nếu mismatch
        $needTabUpdate = ($task['sheet_tab'] !== $tabName) || ((int)$task['sheet_row'] !== $rowNum);

        $sheetDevStatus = isset($sheetRow[self::COL_DEV_STATUS]) ? trim((string)$sheetRow[self::COL_DEV_STATUS]) : '';
        $sheetDevStatusLc = mb_strtolower($sheetDevStatus, 'UTF-8');
        $mappedDevStatus = self::DEV_STATUS_SHEET_TO_DB[$sheetDevStatusLc] ?? null;

        $changes = [];
        $params = [];

        if($mappedDevStatus !== null && $mappedDevStatus !== $task['dev_status']) {
            $changes[] = 'dev_status = ?';
            $params[]  = $mappedDevStatus;

            $now = date('Y-m-d H:i:s');

            switch($mappedDevStatus) {
                case 'Chờ dev nhận':
                    // Dev reset về todo — clear cả start/end, main rollback nếu đã advance
                    $changes[] = 'dev_start_at = NULL';
                    $changes[] = 'dev_end_at = NULL';
                    if(in_array($task['status'], ['Dion - Chờ nghiệm thu','Kinkin nghiệm thu'], true)) {
                        $changes[] = 'status = ?'; $params[] = 'Dion - đang xử lý';
                        $changes[] = 'actual_end_date = NULL';
                    }
                    break;
                case 'Dev đang làm':
                    if(empty($task['dev_start_at'])) {
                        $changes[] = 'dev_start_at = ?';
                        $params[]  = $now;
                    }
                    // Reset end + rollback main nếu Dev quay lại làm tiếp
                    $changes[] = 'dev_end_at = NULL';
                    if(in_array($task['status'], ['Dion - Chờ nghiệm thu','Kinkin nghiệm thu'], true)) {
                        $changes[] = 'status = ?'; $params[] = 'Dion - đang xử lý';
                        $changes[] = 'actual_end_date = NULL';
                        $changes[] = 'test_status = NULL';
                    }
                    break;
                case 'Dev đã xong':
                    $changes[] = 'dev_end_at = ?';      $params[] = $now;
                    $changes[] = 'status = ?';          $params[] = 'Dion - Chờ nghiệm thu';
                    $changes[] = 'actual_end_date = ?'; $params[] = $now;
                    break;
                case 'Cần sửa':
                    $changes[] = 'status = ?';     $params[] = 'Dion - đang xử lý';
                    $changes[] = 'dev_end_at = NULL';   // literal, không placeholder
                    $changes[] = 'test_status = NULL';
                    break;
            }
        }
        // Sheet "hủy" / "huỷ" → main status = Huỷ
        if(in_array($sheetDevStatusLc, ['hủy', 'huỷ'], true) && $task['status'] !== 'Huỷ') {
            $changes[] = 'status = ?'; $params[] = 'Huỷ';
        }

        if($needTabUpdate) {
            $changes[] = 'sheet_tab = ?'; $params[] = $tabName;
            $changes[] = 'sheet_row = ?'; $params[] = $rowNum;
        }

        if(empty($changes)) return false;

        $sql = "UPDATE tasks SET " . implode(', ', $changes) . " WHERE id = ?";
        $params[] = (int)$task['id'];
        $upd = $this->db->prepare($sql);
        return $upd->execute($params);
    }

    // ============================================================
    // ROW BUILDING
    // ============================================================

    private function buildRow($t, $opts = []) {
        date_default_timezone_set('Asia/Ho_Chi_Minh');
        $devStatus = $opts['force_dev_status']
            ?? (self::DEV_STATUS_DB_TO_SHEET[$t['dev_status']] ?? ($t['dev_status'] ?? ''));

        // Nội dung yêu cầu = path 4-cấp (Module → Tính năng → Logic → Tính năng ẩn) + ba_description
        $pathParts = array_filter([
            $t['module_node_name']  ?? null,
            $t['feature_node_name'] ?? null,
            $t['logic_node_name']   ?? null,
            $t['hidden_node_name']  ?? null,
        ]);
        $path = implode(' › ', $pathParts);
        $baDesc = $t['ba_description'] ?: ($t['description'] ?? '');
        if($path !== '' && $baDesc !== '') $content = "[$path]\n$baDesc";
        elseif($path !== '')               $content = "[$path]";
        else                               $content = $baDesc;

        $priority  = $t['priority_ba']     ?: ($t['priority_requester'] ?? '');
        $devDay    = $opts['dev_day'] ?? '';
        $timeBaReq = $opts['time_ba_request']
            ?? ($t['ba_submission_date'] ? date('d/m/Y H:i', strtotime($t['ba_submission_date'])) : '');

        // Chỉ ghi: thông tin task + ngày dự kiến hoàn thành (col 7)
        // Các cột ngày khác (col 5,6,14,15,16,17) để trống — Dev tự điền trên sheet, bot poll về DB
        $plannedEnd = $t['dev_planned_end'] ? date('d/m/Y', strtotime($t['dev_planned_end']))
            : ($t['dev_deadline'] ? date('d/m/Y', strtotime($t['dev_deadline'])) : '');

        return [
            $t['task_type']                       ?? '',   // col 0: Loại yêu cầu
            $t['ma_yc']                           ?? ('#' . $t['id']),  // col 1: Mã YC
            $content,                                       // col 2: Nội dung yêu cầu
            $t['assignee_nickname']               ?? '',   // col 3: Người yêu cầu (BA)
            $t['dev_nickname']                    ?? '',   // col 4: Người thực hiện (Dev)
            '',                                             // col 5: Ngày thực hiện code — để trống
            '',                                             // col 6: Ngày hoàn thành code — để trống
            $plannedEnd,                                    // col 7: Ngày dự kiến hoàn thành — GHI
            $devStatus,                                     // col 8: Trạng thái task của dev
            $priority,                                      // col 9: Mức độ
            '',                                             // col 10: Người thực hiện test — để trống
            '',                                             // col 11: Ngày test — để trống
            '',                                             // col 12: Trạng thái test — để trống
            '',                                             // col 13: Ghi chú — để trống
            '',                                             // col 14: Thời gian bắt đầu code — để trống
            '',                                             // col 15: Thời gian kết thúc code — để trống
            $timeBaReq,                                     // col 16: Thời gian BA yêu cầu — GHI
            '',                                             // col 17: Thời gian nghiệm thu — để trống
            $devDay,                                        // col 18: Ngày Dev thực hiện YC
            '',                                             // col 19: BA ước tính — để trống
        ];
    }

    private function loadTaskWithJoins($taskId) {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   ba.nickname AS assignee_nickname,
                   d.nickname  AS dev_nickname,
                   te.nickname AS tester_nickname,
                   mn.name     AS module_node_name,
                   fn.name     AS feature_node_name,
                   ln.name     AS logic_node_name,
                   hn.name     AS hidden_node_name
            FROM tasks t
            LEFT JOIN users ba ON ba.id = t.assignee_id
            LEFT JOIN users d  ON d.id  = t.dev_id
            LEFT JOIN users te ON te.id = t.tester_id
            LEFT JOIN system_nodes mn ON t.module_node_id  = mn.id
            LEFT JOIN system_nodes fn ON t.feature_node_id = fn.id
            LEFT JOIN system_nodes ln ON t.logic_node_id   = ln.id
            LEFT JOIN system_nodes hn ON t.hidden_node_id  = hn.id
            WHERE t.id = ?
        ");
        $stmt->execute([$taskId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
