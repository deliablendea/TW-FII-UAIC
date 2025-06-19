<?php
require_once '../config/Database.php';
require_once '../controllers/AuthController.php';

try {
    $db = Database::getInstance();
    $controller = new AuthController($db->getConnection());
    
    // Check if user is logged in
    session_start();
    if (isset($_SESSION['user_id'])) {
        // User is logged in, redirect to dashboard
        header('Location: dashboard.html');
    } else {
        // User is not logged in, redirect to login
        header('Location: login.html');
    }
} catch (Exception $e) {
    // Fallback to login page on error
    header('Location: login.html');
}
exit;
?> 