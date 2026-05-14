<?php
// External KPI dashboard URL — bạn cập nhật khi có link thực tế.
// Sidebar Lead sẽ dùng URL này cho menu "Phân tích KPI".
if(!defined('KPI_EXTERNAL_URL')) {
    define('KPI_EXTERNAL_URL', 'https://dashboard.lxtruong03.workers.dev/');
}

// Base path: '/BA.Tool' trên XAMPP, '' trên Railway
if(!defined('BASE_PATH')) {
    $isRailway = getenv('RAILWAY_ENVIRONMENT') || getenv('MYSQLHOST');
    define('BASE_PATH', $isRailway ? '' : '/BA.Tool');
}

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        // Railway cung cấp MYSQL_URL hoặc các biến riêng lẻ
        $url = getenv('MYSQL_URL');
        if ($url && preg_match('#mysql://([^:]+):([^@]+)@([^:]+):(\d+)/(.+)#', $url, $m)) {
            $this->username = $m[1];
            $this->password = $m[2];
            $this->host     = $m[3];
            $this->port     = $m[4];
            $this->db_name  = $m[5];
        } else {
            $this->host     = trim(getenv('MYSQLHOST') ?: 'localhost');
            $this->db_name  = trim(getenv('MYSQLDATABASE') ?: 'ba_tool');
            $this->username = trim(getenv('MYSQLUSER') ?: 'root');
            $this->password = trim(getenv('MYSQLPASSWORD') ?: '');
            $this->port     = trim(getenv('MYSQLPORT') ?: '3306');
        }
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
            }
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";port=" . $this->port . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password,
                $options
            );
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
