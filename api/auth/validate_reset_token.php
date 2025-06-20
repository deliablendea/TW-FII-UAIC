<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../controllers/PasswordResetController.php';

try {
    $db = Database::getInstance();
    $controller = new PasswordResetController($db->getConnection());
    $controller->validateToken();
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['valid' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
    error_log("Validate token API error: " . $e->getMessage());
}
?> 