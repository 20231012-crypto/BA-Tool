<?php
require_once 'models/User.php';

class AuthController {
    
    public function login() {
        // If already logged in
        if(isset($_SESSION['user_id'])) {
            header("Location: ?page=dashboard");
            exit;
        }

        // Handle POST form submission
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $database = new Database();
            $db = $database->getConnection();
            $user = new User($db);

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';

            $logged_in = $user->login($username, $password);

            if($logged_in) {
                // Block dev role login — Dev quản lý công việc qua Google Sheet, không có dashboard riêng
                if($logged_in['role'] === 'dev') {
                    $error = "Tài khoản Dev không được phép đăng nhập hệ thống. Dev quản lý công việc trên Google Sheet.";
                } else {
                    $_SESSION['user_id'] = $logged_in['id'];
                    $_SESSION['username'] = $logged_in['username'];
                    $_SESSION['role'] = $logged_in['role'];
                    $_SESSION['full_name'] = $logged_in['full_name'];

                    header("Location: ?page=dashboard");
                    exit;
                }
            } else {
                $error = "Sai tên đăng nhập hoặc mật khẩu.";
            }
        }

        // Show login view
        include 'views/admin/login.php';
    }

    public function register() {
        if(isset($_SESSION['user_id'])) {
            header("Location: ?page=dashboard");
            exit;
        }

        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $database = new Database();
            $db = $database->getConnection();
            $user = new User($db);

            $username  = $_POST['username'] ?? '';
            $password  = $_POST['password'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $nickname  = !empty($_POST['nickname']) ? trim($_POST['nickname']) : null;
            $role = 'ba'; // Mặc định là nhân viên, không lấy qua POST nữa

            $result = $user->register($username, $password, $full_name, $role, $nickname);
            if($result === true) {
                echo "<script>alert('Đăng ký tài khoản thành công! Vui lòng đăng nhập.'); window.location.href='?page=login';</script>";
                exit;
            } else {
                $error = $result;
            }
        }

        // Show register view
        include 'views/admin/register.php';
    }

    public function logout() {
        session_destroy();
        header("Location: ?page=login");
        exit;
    }
}
?>
