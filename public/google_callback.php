<?php
require_once '../config/Database.php';
require_once '../controllers/AuthController.php';

session_start();

try {
    $db = Database::getInstance();
    $controller = new AuthController($db->getConnection());
    $controller->handleGoogleCallback();
} catch (Exception $e) {
    error_log("Google callback error: " . $e->getMessage());
    header('Location: login.html?error=google_callback_failed');
    exit;
}
?>