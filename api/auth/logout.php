<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

try {
    $db = Database::getInstance();
    $controller = new AuthController($db->getConnection());
    $controller->logout();
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
    error_log("Logout API error: " . $e->getMessage());
}
?> 