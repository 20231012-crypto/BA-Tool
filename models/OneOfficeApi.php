<?php
/**
 * OneOfficeApi — Client gọi API 1Office (công việc thường)
 */
class OneOfficeApi {
    const API_URL = 'https://kinkin.1office.vn/api/work/normal/gets';
    const ACCESS_TOKEN = '38216884069fc087ec353d333939552';
    const TASK_BASE_URL = 'https://kinkin.1office.vn/work/normal/detail/';

    /**
     * Lấy danh sách công việc với filter
     * @param array $filters ['assign_ids'=>'KK0230', 'status'=>'DOING', ...]
     * @param int $page
     * @param int $limit
     * @return array ['error'=>bool, 'total_item'=>int, 'data'=>[...]]
     */
    public static function getTasks($filters = [], $page = 1, $limit = 50) {
        $params = [
            'access_token' => self::ACCESS_TOKEN,
            'limit'        => min(100, max(1, $limit)),
            'page'         => max(1, $page),
            'sort_by'      => 'date_updated',
            'sort_type'    => 'desc',
        ];

        if (!empty($filters)) {
            $params['filters'] = json_encode([$filters], JSON_UNESCAPED_UNICODE);
        }

        $url = self::API_URL . '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) return ['error' => true, 'message' => "Curl error: $err", 'data' => []];
        if ($code !== 200) return ['error' => true, 'message' => "HTTP $code", 'data' => []];

        $data = json_decode($resp, true);
        if (!is_array($data)) return ['error' => true, 'message' => 'Invalid JSON', 'data' => []];

        return $data;
    }

    /** Tạo URL dẫn thẳng đến công việc trên 1Office */
    public static function taskUrl($taskId) {
        return self::TASK_BASE_URL . $taskId;
    }
}
