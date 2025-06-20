<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../controllers/PasswordResetController.php';

try {
    $db = Database::getInstance();
    $controller = new PasswordResetController($db->getConnection());
    $controller->resetPassword();
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
    error_log("Reset password API error: " . $e->getMessage());
}
?> 