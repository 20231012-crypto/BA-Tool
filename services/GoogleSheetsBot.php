<?php
/**
 * GoogleSheetsBot — minimal Google Sheets API v4 client
 * Auth: Service Account JWT → access_token (no Composer needed)
 *
 * Yêu cầu PHP: openssl + curl extensions (mặc định có trong XAMPP).
 */
class GoogleSheetsBot {
    private $credentials;
    private $accessToken = null;
    private $accessTokenExpiry = 0;
    private $spreadsheetId;

    const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    public function __construct($credentialsPath, $spreadsheetId) {
        // Ưu tiên đọc từ biến môi trường GOOGLE_CREDENTIALS_JSON (Railway)
        $envJson = getenv('GOOGLE_CREDENTIALS_JSON');
        if ($envJson) {
            $json = $envJson;
        } elseif (file_exists($credentialsPath)) {
            $json = file_get_contents($credentialsPath);
        } else {
            throw new Exception("Không tìm thấy credentials: $credentialsPath");
        }
        $this->credentials = json_decode($json, true);
        if(!$this->credentials || empty($this->credentials['private_key']) || empty($this->credentials['client_email'])) {
            throw new Exception("File credentials không hợp lệ (thiếu private_key/client_email)");
        }
        if(!$spreadsheetId) throw new Exception("Thiếu spreadsheet ID");
        $this->spreadsheetId = $spreadsheetId;
    }

    public function getBotEmail() {
        return $this->credentials['client_email'];
    }

    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Lấy access_token mới (cache trong instance trong 50 phút).
     */
    private function getAccessToken() {
        if($this->accessToken && time() < $this->accessTokenExpiry) return $this->accessToken;

        $now = time();
        $header  = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = $this->base64UrlEncode(json_encode([
            'iss'   => $this->credentials['client_email'],
            'scope' => self::SCOPE,
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600
        ]));
        $signingInput = $header . '.' . $payload;
        $signature = '';
        if(!openssl_sign($signingInput, $signature, $this->credentials['private_key'], 'SHA256')) {
            throw new Exception("Không thể ký JWT (private key sai?)");
        }
        $jwt = $signingInput . '.' . $this->base64UrlEncode($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if($resp === false) throw new Exception("Lỗi kết nối Google: $err");

        $data = json_decode($resp, true);
        if($code !== 200 || empty($data['access_token'])) {
            $msg = $data['error_description'] ?? $data['error'] ?? $resp;
            throw new Exception("OAuth token failed (HTTP $code): $msg");
        }
        $this->accessToken = $data['access_token'];
        $this->accessTokenExpiry = $now + intval($data['expires_in'] ?? 3000) - 60;
        return $this->accessToken;
    }

    private function api($method, $url, $body = null) {
        $token = $this->getAccessToken();
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json; charset=UTF-8'
            ],
            CURLOPT_TIMEOUT        => 60,
        ];
        if($body !== null) $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if($resp === false) throw new Exception("API error: $err");

        $data = json_decode($resp, true);
        if($code >= 300) {
            $msg = $data['error']['message'] ?? $resp;
            throw new Exception("Google API HTTP $code: $msg");
        }
        return $data;
    }

    /**
     * Lấy metadata spreadsheet (đặc biệt là danh sách sheet/tabs)
     */
    public function getSpreadsheet() {
        return $this->api('GET',
            "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}?fields=spreadsheetId,properties.title,sheets.properties");
    }

    /**
     * Trả về map [tab name => sheetId]
     */
    public function listTabs() {
        $data = $this->getSpreadsheet();
        $map = [];
        foreach(($data['sheets'] ?? []) as $s) {
            $p = $s['properties'] ?? [];
            if(isset($p['title'])) $map[$p['title']] = $p['sheetId'] ?? null;
        }
        return $map;
    }

    public function addTab($title) {
        return $this->api('POST',
            "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}:batchUpdate",
            ['requests' => [['addSheet' => ['properties' => ['title' => $title]]]]]
        );
    }

    public function deleteTab($sheetId) {
        return $this->api('POST',
            "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}:batchUpdate",
            ['requests' => [['deleteSheet' => ['sheetId' => $sheetId]]]]
        );
    }

    /**
     * Đảm bảo tab tồn tại; tạo nếu chưa có. Trả về true nếu OK.
     */
    public function ensureTab($title) {
        $tabs = $this->listTabs();
        if(isset($tabs[$title])) return true;
        $this->addTab($title);
        return true;
    }

    /** Encode range cho Google Sheets API — giữ nguyên dấu ' và ! */
    private function encodeRange($range) {
        return str_replace(['%27','%21'], ["'","!"], rawurlencode($range));
    }

    public function clearRange($range) {
        return $this->api('POST',
            "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/" . $this->encodeRange($range) . ":clear", new stdClass()
        );
    }

    /**
     * Đọc values từ một range. Trả về mảng 2 chiều (rows × cols).
     * Range vd: "Tổng quan!A1:AZ5000". Nếu range không có dữ liệu sẽ trả [].
     */
    public function getValues($range) {
        $res = $this->api('GET',
            "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/" . $this->encodeRange($range)
        );
        return $res['values'] ?? [];
    }

    /**
     * Ghi values vào range. valueInputOption=RAW để giữ plain text (không format auto).
     */
    public function updateValues($range, array $values) {
        return $this->api('PUT',
            "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/" . $this->encodeRange($range) . "?valueInputOption=RAW",
            ['range' => $range, 'majorDimension' => 'ROWS', 'values' => $values]
        );
    }

    /**
     * Append rows vào cuối range (sau dòng cuối có data). Trả về updated range.
     * valueInputOption=USER_ENTERED để Sheet tự parse date string thành date cell.
     */
    public function appendValues($range, array $values, $valueInputOption = 'USER_ENTERED') {
        return $this->api('POST',
            "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}/values/" . $this->encodeRange($range)
            . ":append?valueInputOption=" . urlencode($valueInputOption) . "&insertDataOption=INSERT_ROWS",
            ['range' => $range, 'majorDimension' => 'ROWS', 'values' => $values]
        );
    }

    /**
     * Duplicate 1 tab (giữ nguyên format, dropdown, conditional formatting).
     * $sourceSheetId = sheetId số của tab nguồn (lấy từ listTabs()).
     * $newTitle      = tên tab mới muốn đặt.
     * Trả về sheetId mới.
     */
    public function duplicateTab($sourceSheetId, $newTitle) {
        $res = $this->api('POST',
            "https://sheets.googleapis.com/v4/spreadsheets/{$this->spreadsheetId}:batchUpdate",
            ['requests' => [[
                'duplicateSheet' => [
                    'sourceSheetId' => (int)$sourceSheetId,
                    'newSheetName'  => $newTitle,
                    'insertSheetIndex' => 1,
                ]
            ]]]
        );
        return $res['replies'][0]['duplicateSheet']['properties']['sheetId'] ?? null;
    }

    /**
     * Xoá tất cả data rows (giữ header dòng 1) trong tab — dùng cho tab mới sau khi duplicate.
     */
    public function clearDataRows($tabName) {
        return $this->clearRange($tabName . '!A2:AZ10000');
    }

    /**
     * Workflow chuẩn: clear toàn tab rồi ghi đè values từ A1.
     */
    public function overwriteTab($tabName, array $values) {
        $this->ensureTab($tabName);
        // Clear tới AZ (52 cột) để cover các tab từng có header rộng (vd 32 cột) trước khi format mới
        $this->clearRange($tabName . '!A1:AZ10000');
        if(empty($values)) return ['cleared' => true, 'wrote' => 0];
        $range = $tabName . '!A1';
        $res = $this->updateValues($range, $values);
        return ['cleared' => true, 'wrote' => count($values), 'updated' => $res['updatedCells'] ?? 0];
    }

    /**
     * Sanitize tên tab cho phù hợp Google Sheets (giới hạn 100 ký tự, không chứa : / ? * [ ])
     */
    public static function safeTabName($name, $fallback = 'User') {
        $name = trim((string)$name);
        if($name === '') $name = $fallback;
        $name = preg_replace('#[:/?*\[\]]#u', '_', $name);
        $name = mb_substr($name, 0, 90, 'UTF-8');
        return $name;
    }
}
?>
