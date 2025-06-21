<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

try {
    $db = Database::getInstance();
    $controller = new AuthController($db->getConnection());
    $controller->deleteAccount();
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error occurred: ' . $e->getMessage()]);
    error_log("Delete account API error: " . $e->getMessage());
}
?> 