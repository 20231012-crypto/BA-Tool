<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
header('Content-Type: text/html; charset=utf-8');
require_once 'config/db.php';

$page = $_GET['page'] ?? 'public_form';

switch($page) {
    case 'public_form':
        require_once 'controllers/FormController.php';
        $controller = new FormController();
        $controller->index();
        break;
    case 'submit_form':
        require_once 'controllers/FormController.php';
        $controller = new FormController();
        $controller->submit();
        break;
    case 'login':
        require_once 'controllers/AuthController.php';
        $controller = new AuthController();
        $controller->login();
        break;
    case 'register':
        require_once 'controllers/AuthController.php';
        $controller = new AuthController();
        $controller->register();
        break;
    case 'logout':
        require_once 'controllers/AuthController.php';
        $controller = new AuthController();
        $controller->logout();
        break;
    case 'dashboard':
        require_once 'controllers/TaskController.php';
        $controller = new TaskController();
        $controller->dashboard();
        break;
    case 'update_task':
        require_once 'controllers/TaskController.php';
        $controller = new TaskController();
        $controller->updateTask();
        break;
    case 'users':
        require_once 'controllers/UserController.php';
        $controller = new UserController();
        $controller->index();
        break;
    case 'user_action':
        require_once 'controllers/UserController.php';
        $controller = new UserController();
        $controller->action();
        break;
    default:
        echo "404 Not Found";
        break;
}
?>
