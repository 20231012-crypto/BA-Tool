<?php
// External KPI dashboard URL — bạn cập nhật khi có link thực tế.
// Sidebar Lead sẽ dùng URL này cho menu "Phân tích KPI".
if(!defined('KPI_EXTERNAL_URL')) {
    define('KPI_EXTERNAL_URL', '#'); // TODO: thay bằng URL thật
}

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $port;
    public $conn;

    public function __construct() {
        $this->host     = getenv('MYSQLHOST') ?: 'localhost';
        $this->db_name  = getenv('MYSQLDATABASE') ?: 'ba_tool';
        $this->username = getenv('MYSQLUSER') ?: 'root';
        $this->password = getenv('MYSQLPASSWORD') ?: '';
        $this->port     = getenv('MYSQLPORT') ?: '3306';
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
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
