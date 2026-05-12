<?php
/**
 * TaskImportService — đẩy dữ liệu từ Google Sheet (tab "Tổng quan") vào DB.
 *
 * Quy tắc:
 *  - Idempotent theo `ma_yc`: nếu task đã có thì UPDATE, chưa có thì INSERT.
 *  - BA name (cột 28) chưa có trong users → tự tạo user role='ba' (username không dấu).
 *  - Chuẩn hoá: status / priority / classification / dev_status (xem map ở dưới).
 *  - implementing_unit: giữ nguyên text (DION / Kinkin / FFM).
 *  - Date format sheet: "d/m/Y" hoặc "d/m/Y H:i" → "Y-m-d" / "Y-m-d H:i:s".
 *
 * Cột (0-based) trong sheet "Tổng quan", trùng với HEADER_32 (30 cột) trong TaskSyncService:
 *   0=created, 1=system, 2=description, 3=requester_name, 4=prio_req,
 *   5=start_date, 6=expected_end, 7=attachment, 8=office_link, 9=dept,
 *  10=task_type, 11=ma_yc, 12=module_name, 13=feature,
 *  14=ba_description, 15=prio_ba, 16=classification, 17=status,
 *  18=ba_start, 19=ba_end, 20=ba_submission_date, 21=actual_start, 22=actual_end,
 *  23=acceptance_date, 24=delay_h, 25=delay_status, 26=BA_name, 27=unit,
 *  28=dev_actual_day, 29=dev_status
 */
require_once __DIR__ . '/GoogleSheetsBot.php';
require_once __DIR__ . '/../models/BotSettings.php';

class TaskImportService {
    private $db;

    /** Map status thô (sheet) → status chuẩn DB. Giá trị không match sẽ giữ nguyên text. */
    const STATUS_NORMALIZE = [
        'pending'                       => 'Chờ tiếp nhận',
        'chờ tiếp nhận'                 => 'Chờ tiếp nhận',
        'todo - chờ xác nhận với sếp'   => 'Todo - chờ xác nhận với Sếp',
        'dion - đang xử lý'             => 'Dion - đang xử lý',
        'dion- chờ nghiệm thu'          => 'Dion - Chờ nghiệm thu',
        'dion - chờ nghiệm thu'         => 'Dion - Chờ nghiệm thu',
        'kinkin nghiệm thu'             => 'Kinkin nghiệm thu',
        'huỷ'                           => 'Huỷ',
        'hủy'                           => 'Huỷ',
        // FFM giữ nguyên text — sẽ được pass-through bên dưới
    ];

    /** Dev status: lowercase → standard. */
    const DEV_STATUS_NORMALIZE = [
        'todo'        => 'Chờ dev nhận',
        'pending'     => 'Chờ dev nhận',
        'chờ dev nhận'=> 'Chờ dev nhận',
        'đang làm'    => 'Dev đang làm',
        'dev đang làm'=> 'Dev đang làm',
        'hoàn thành'  => 'Dev đã xong',
        'dev đã xong' => 'Dev đã xong',
        'cần sửa'     => 'Cần sửa',
        'hủy'         => '',  // dev hủy → để trống dev_status
        'huỷ'         => '',
    ];

    /** Priority requester: thêm prefix số nếu chưa có. */
    const PRIO_REQ_NORMALIZE = [
        'gấp - quan trọng'                  => '4. Gấp - Quan trọng',
        'không gấp - quan trọng'            => '3. Không gấp - Quan trọng',
        'gấp - không quan trọng'            => '2. Gấp - Không quan trọng',
        'không gấp - không quan trọng'      => '1. Không gấp - Không quan trọng',
    ];

    /** Priority BA: bỏ double space. */
    const PRIO_BA_NORMALIZE = [
        '4. gấp - quan trọng'           => '4. Gấp - Quan trọng',
        '3. không gấp - quan trọng'     => '3. Không gấp - Quan trọng',
        '2. gấp - không quan trọng'     => '2. Gấp - Không quan trọng',
        '1. không gấp - không quan trọng'=> '1. Không gấp - Không quan trọng',
    ];

    const CLASSIFICATION_NORMALIZE = [
        'hệ thống -thực hiện'    => 'Hệ thống - Thực hiện',
        'hệ thống - thực hiện'   => 'Hệ thống - Thực hiện',
        'hệ thống -tham khảo'    => 'Hệ thống - Tham khảo',
        'hệ thống - tham khảo'   => 'Hệ thống - Tham khảo',
        'người dùng - lỗi'       => 'Người dùng - Lỗi',
        'hỗ trợ user'            => 'Hỗ trợ user',
        'huỷ'                    => 'Huỷ',
        'hủy'                    => 'Huỷ',
        'khác'                   => 'Khác',
    ];

    public function __construct($db) { $this->db = $db; }

    /** Entry point — chạy import. Trả về stats. */
    public function runImport($options = []) {
        $tab          = $options['tab'] ?? 'Tổng quan';
        $dryRun       = !empty($options['dry_run']);
        $autoCreateBa = !isset($options['auto_create_ba']) ? true : !empty($options['auto_create_ba']);

        $bs  = new BotSettings($this->db);
        $cfg = $bs->get();
        if(empty($cfg['sheet_id'])) {
            return ['success' => false, 'message' => 'Chưa cấu hình Google Sheet'];
        }
        $credPath = $cfg['credentials_path'] ?: 'config/google-credentials.json';
        $absCred  = __DIR__ . '/../' . $credPath;
        if(!file_exists($absCred)) {
            return ['success' => false, 'message' => "Không tìm thấy credentials: $credPath"];
        }

        try {
            $bot  = new GoogleSheetsBot($absCred, $cfg['sheet_id']);
            $rows = $bot->getValues($tab . '!A1:AF10000');
            if(count($rows) < 2) {
                return ['success' => false, 'message' => "Tab '$tab' không có dữ liệu"];
            }
            // Skip header (row 0)
            $dataRows = array_slice($rows, 1);

            // Pre-load existing tasks by ma_yc + users by full_name
            $existing = $this->fetchExistingTaskMap();
            $userMap  = $this->fetchUserMap();
            $sysMap   = $this->fetchSystemMap();

            $stats = [
                'total_rows' => count($dataRows),
                'inserted'   => 0,
                'updated'    => 0,
                'skipped'    => 0,
                'created_ba' => [],
                'errors'     => [],
            ];

            foreach($dataRows as $idx => $r) {
                try {
                    $rowNum = $idx + 2; // +1 cho header, +1 cho 1-based
                    $maYc   = $this->cell($r, 11);
                    if($maYc === '') {
                        $stats['skipped']++;
                        continue;
                    }
                    $taskData = $this->mapRowToTask($r, $userMap, $sysMap, $autoCreateBa, $stats);
                    if($dryRun) continue;

                    if(isset($existing[$maYc])) {
                        $this->updateTask($existing[$maYc], $taskData);
                        $stats['updated']++;
                    } else {
                        $this->insertTask($maYc, $taskData);
                        $stats['inserted']++;
                    }
                } catch(Exception $e) {
                    $stats['errors'][] = "Dòng $rowNum (" . ($maYc ?? '?') . "): " . $e->getMessage();
                }
            }

            return ['success' => true, 'message' => 'Import hoàn tất', 'stats' => $stats];
        } catch(Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function fetchExistingTaskMap() {
        $rows = $this->db->query("SELECT id, ma_yc FROM tasks WHERE ma_yc IS NOT NULL")->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach($rows as $r) $map[$r['ma_yc']] = (int)$r['id'];
        return $map;
    }

    private function fetchUserMap() {
        // Map full_name (lowercase, normalized) → user_id
        $rows = $this->db->query("SELECT id, full_name, username FROM users")->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach($rows as $r) {
            $key = self::norm($r['full_name']);
            $map[$key] = (int)$r['id'];
        }
        return $map;
    }

    private function fetchSystemMap() {
        $rows = $this->db->query("SELECT id, name FROM systems")->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach($rows as $r) $map[self::norm($r['name'])] = (int)$r['id'];
        return $map;
    }

    /** Đưa string về dạng so sánh (lowercase + collapse whitespace). */
    private static function norm($s) {
        $s = (string)$s;
        $s = preg_replace('/\s+/u', ' ', $s);
        return mb_strtolower(trim($s), 'UTF-8');
    }

    private function cell($r, $i) {
        if(!isset($r[$i])) return '';
        // Sheet một số ô trả về ký tự U+200B / NBSP / "​" (zero-width)
        $v = (string)$r[$i];
        $v = str_replace(["\xE2\x80\x8B", "\xC2\xA0"], ['', ' '], $v);
        return trim($v);
    }

    /** Parse "d/m/Y H:i" hoặc "d/m/Y" → "Y-m-d H:i:s" hoặc null. */
    private function parseDateTime($s, $dateOnly = false) {
        $s = trim((string)$s);
        if($s === '' || $s === '0' || $s === '00:00:00') return null;
        $formats = $dateOnly
            ? ['d/m/Y', 'Y-m-d', 'd-m-Y']
            : ['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'];
        foreach($formats as $f) {
            $dt = DateTime::createFromFormat($f, $s);
            if($dt !== false) {
                $errs = DateTime::getLastErrors();
                if(empty($errs['warning_count']) && empty($errs['error_count'])) {
                    return $dateOnly ? $dt->format('Y-m-d') : $dt->format('Y-m-d H:i:s');
                }
            }
        }
        // Fallback: strtotime
        $ts = strtotime($s);
        if($ts !== false) {
            return $dateOnly ? date('Y-m-d', $ts) : date('Y-m-d H:i:s', $ts);
        }
        return null;
    }

    private function normStatus($raw) {
        if($raw === '') return 'Chờ tiếp nhận';
        $key = self::norm($raw);
        return self::STATUS_NORMALIZE[$key] ?? $raw; // pass-through (giữ "FFM - đang xử lý" v.v.)
    }

    private function normDevStatus($raw) {
        if($raw === '') return null;
        $key = self::norm($raw);
        if(array_key_exists($key, self::DEV_STATUS_NORMALIZE)) {
            $v = self::DEV_STATUS_NORMALIZE[$key];
            return $v === '' ? null : $v;
        }
        return $raw;
    }

    private function normPriorityRequester($raw) {
        if($raw === '') return null;
        $key = self::norm($raw);
        return self::PRIO_REQ_NORMALIZE[$key] ?? $raw;
    }

    private function normPriorityBa($raw) {
        if($raw === '') return null;
        $key = self::norm($raw);
        return self::PRIO_BA_NORMALIZE[$key] ?? $raw;
    }

    private function normClassification($raw) {
        if($raw === '') return null;
        $key = self::norm($raw);
        return self::CLASSIFICATION_NORMALIZE[$key] ?? $raw;
    }

    /** Bỏ dấu tiếng Việt + viết liền (cho username). */
    public static function slugifyName($name) {
        $name = trim($name);
        if($name === '') return '';
        $map = [
            'à','á','ả','ã','ạ','ă','ằ','ắ','ẳ','ẵ','ặ','â','ầ','ấ','ẩ','ẫ','ậ',
            'è','é','ẻ','ẽ','ẹ','ê','ề','ế','ể','ễ','ệ',
            'ì','í','ỉ','ĩ','ị',
            'ò','ó','ỏ','õ','ọ','ô','ồ','ố','ổ','ỗ','ộ','ơ','ờ','ớ','ở','ỡ','ợ',
            'ù','ú','ủ','ũ','ụ','ư','ừ','ứ','ử','ữ','ự',
            'ỳ','ý','ỷ','ỹ','ỵ','đ',
            'À','Á','Ả','Ã','Ạ','Ă','Ằ','Ắ','Ẳ','Ẵ','Ặ','Â','Ầ','Ấ','Ẩ','Ẫ','Ậ',
            'È','É','Ẻ','Ẽ','Ẹ','Ê','Ề','Ế','Ể','Ễ','Ệ',
            'Ì','Í','Ỉ','Ĩ','Ị',
            'Ò','Ó','Ỏ','Õ','Ọ','Ô','Ồ','Ố','Ổ','Ỗ','Ộ','Ơ','Ờ','Ớ','Ở','Ỡ','Ợ',
            'Ù','Ú','Ủ','Ũ','Ụ','Ư','Ừ','Ứ','Ử','Ữ','Ự',
            'Ỳ','Ý','Ỷ','Ỹ','Ỵ','Đ',
        ];
        $rep = [
            'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
            'e','e','e','e','e','e','e','e','e','e','e',
            'i','i','i','i','i',
            'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
            'u','u','u','u','u','u','u','u','u','u','u',
            'y','y','y','y','y','d',
            'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
            'e','e','e','e','e','e','e','e','e','e','e',
            'i','i','i','i','i',
            'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
            'u','u','u','u','u','u','u','u','u','u','u',
            'y','y','y','y','y','d',
        ];
        $s = str_replace($map, $rep, $name);
        $s = preg_replace('/[^A-Za-z0-9]+/', '', $s);
        return strtolower($s);
    }

    /** Tạo user BA mới khi chưa có. Trả về user_id. */
    private function ensureBaUser($fullName, &$stats) {
        $base = self::slugifyName($fullName);
        if($base === '') $base = 'ba_user';

        // Tìm username chưa dùng
        $username = $base;
        $i = 1;
        while(true) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if(!$stmt->fetchColumn()) break;
            $i++;
            $username = $base . $i;
            if($i > 100) throw new Exception("Không sinh được username unique cho '$fullName'");
        }
        // Default password: kinkin123
        $hashed = password_hash('kinkin123', PASSWORD_BCRYPT);
        $stmt = $this->db->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, 'ba')");
        $stmt->execute([$username, $hashed, $fullName]);
        $newId = (int)$this->db->lastInsertId();
        $stats['created_ba'][] = ['id' => $newId, 'username' => $username, 'full_name' => $fullName];
        return $newId;
    }

    private function mapRowToTask($r, &$userMap, &$sysMap, $autoCreateBa, &$stats) {
        $created      = $this->parseDateTime($this->cell($r, 0));
        $systemName   = $this->cell($r, 1);
        $description  = $this->cell($r, 2);
        $reqName      = $this->cell($r, 3);
        $prioReq      = $this->normPriorityRequester($this->cell($r, 4));
        $startDate    = $this->parseDateTime($this->cell($r, 5));
        $expectedEnd  = $this->parseDateTime($this->cell($r, 6));
        $attachment   = $this->cell($r, 7);
        $officeLink   = $this->cell($r, 8);
        $reqDept      = $this->cell($r, 9);
        $taskType     = $this->cell($r, 10);
        // 11=ma_yc — caller đã handle
        $moduleName   = $this->cell($r, 12);
        $feature      = $this->cell($r, 13);
        $baDesc       = $this->cell($r, 14);
        $prioBa       = $this->normPriorityBa($this->cell($r, 15));
        $classif      = $this->normClassification($this->cell($r, 16));
        $status       = $this->normStatus($this->cell($r, 17));
        $baStart      = $this->parseDateTime($this->cell($r, 18));
        $baEnd        = $this->parseDateTime($this->cell($r, 19));
        $baSubDate    = $this->parseDateTime($this->cell($r, 20), true);
        $actualStart  = $this->parseDateTime($this->cell($r, 21));
        $actualEnd    = $this->parseDateTime($this->cell($r, 22));
        $acceptDate   = $this->parseDateTime($this->cell($r, 23), true);
        // 24=delay_h, 25=delay_status (derived — bỏ qua)
        $baName       = $this->cell($r, 26);
        $unit         = $this->cell($r, 27);
        $devDay       = $this->cell($r, 28);
        $devStatus    = $this->normDevStatus($this->cell($r, 29));

        // BA name → assignee_id
        $assigneeId = null;
        if($baName !== '') {
            $key = self::norm($baName);
            if(isset($userMap[$key])) {
                $assigneeId = $userMap[$key];
            } elseif($autoCreateBa) {
                $assigneeId = $this->ensureBaUser($baName, $stats);
                $userMap[$key] = $assigneeId; // cache
            }
        }

        // System name → system_id (best-effort, không bắt buộc)
        $systemId = null;
        if($systemName !== '') {
            $key = self::norm($systemName);
            if(isset($sysMap[$key])) $systemId = $sysMap[$key];
        }

        // Required NOT NULL columns: requester_name, requester_dept, system_name,
        // description, task_type, priority_requester, start_date, expected_end_date.
        // Sheet có dòng thiếu — fill default an toàn.
        $today = date('Y-m-d H:i:s');
        return [
            'created_at'           => $created,                                   // sẽ set qua update riêng (INSERT mặc định = NOW)
            'requester_name'       => $reqName ?: '(không rõ)',
            'requester_dept'       => $reqDept ?: 'Khác',
            'system_name'          => $systemName ?: '(không rõ)',
            'description'          => $description ?: '(không có)',
            'task_type'            => $taskType ?: 'Khác',
            'priority_requester'   => $prioReq ?: '1. Không gấp - Không quan trọng',
            'start_date'           => $startDate ?: ($created ?: $today),
            'expected_end_date'    => $expectedEnd ?: ($startDate ?: ($created ?: $today)),
            'attachment_url'       => $attachment ?: null,
            'office_link'          => $officeLink ?: null,
            'priority_ba'          => $prioBa,
            'classification'       => $classif,
            'status'               => $status,
            'ba_description'       => $baDesc ?: null,
            'feature'              => $feature ?: null,
            'ba_submission_date'   => $baSubDate,
            'actual_start_datetime'=> $actualStart,
            'actual_end_date'      => $actualEnd,
            'acceptance_date'      => $acceptDate,
            'assignee_id'          => $assigneeId,
            'implementing_unit'    => $unit ?: null,
            'dev_actual_day'       => $devDay ?: null,
            'dev_status'           => $devStatus,
            'system_id'            => $systemId,
            // BA est. (cột 20, 21) — chưa có cột riêng trong DB, dùng start_date/expected_end_date nếu cần
            // Nếu BA est. có giá trị mà form-NV không có thì dùng BA est. làm fallback
            'ba_est_start'         => $baStart,
            'ba_est_end'           => $baEnd,
        ];
    }

    private function insertTask($maYc, $data) {
        // BA est. lấp vào start_date/expected_end_date nếu sheet trống ở cột 5/6
        if(empty($data['start_date'])        && !empty($data['ba_est_start'])) $data['start_date']        = $data['ba_est_start'];
        if(empty($data['expected_end_date']) && !empty($data['ba_est_end']))   $data['expected_end_date'] = $data['ba_est_end'];
        unset($data['ba_est_start'], $data['ba_est_end']);

        $createdAt = $data['created_at'] ?? null;
        unset($data['created_at']);

        $cols = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $cols);
        $sql = "INSERT INTO tasks (ma_yc, " . implode(',', $cols) . ") "
             . "VALUES (:ma_yc, " . implode(',', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':ma_yc', $maYc);
        foreach($data as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->execute();

        // Set created_at riêng (sheet có timestamp gốc — quý giá cho biểu đồ time series)
        if($createdAt) {
            $newId = (int)$this->db->lastInsertId();
            $u = $this->db->prepare("UPDATE tasks SET created_at = ? WHERE id = ?");
            $u->execute([$createdAt, $newId]);
        }
    }

    private function updateTask($id, $data) {
        if(empty($data['start_date'])        && !empty($data['ba_est_start'])) $data['start_date']        = $data['ba_est_start'];
        if(empty($data['expected_end_date']) && !empty($data['ba_est_end']))   $data['expected_end_date'] = $data['ba_est_end'];
        unset($data['ba_est_start'], $data['ba_est_end']);

        $createdAt = $data['created_at'] ?? null;
        unset($data['created_at']);

        $sets = [];
        foreach($data as $k => $v) $sets[] = "$k = :$k";
        $sql = "UPDATE tasks SET " . implode(',', $sets) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        foreach($data as $k => $v) $stmt->bindValue(':' . $k, $v);
        $stmt->execute();

        if($createdAt) {
            $u = $this->db->prepare("UPDATE tasks SET created_at = ? WHERE id = ?");
            $u->execute([$createdAt, $id]);
        }
    }
}
