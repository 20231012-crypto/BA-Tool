<?php
require_once 'models/User.php';

class UserController {
    
    public function index() {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'lead') {
            die("Unauthorize access");
        }

        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);

        $stmt = $user->getAllWithPerformance();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        include 'views/admin/users.php';
    }

    public function action() {
        if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'lead') {
            die("Unauthorized");
        }

        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            $database = new Database();
            $db = $database->getConnection();
            $userModel = new User($db);

            $action = $_POST['action'] ?? '';
            
            if($action == 'create') {
                $username = $_POST['username'];
                $password = $_POST['password'];
                $full_name = $_POST['full_name'];
                $role = $_POST['role'];
                $userModel->register($username, $password, $full_name, $role);
            } 
            else if($action == 'edit') {
                $id = $_POST['user_id'];
                $full_name = $_POST['full_name'];
                $role = $_POST['role'];
                $password = $_POST['password']; // can be empty
                $userModel->updateUser($id, $full_name, $role, $password);
            } 
            else if($action == 'delete') {
                $id = $_POST['user_id'];
                // Prevent self-delete
                if($id != $_SESSION['user_id']) {
                    $userModel->deleteUser($id);
                }
            }

            header("Location: ?page=dashboard");
            exit;
        }
    }
}
?>
