<?php
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../controllers/GoogleOAuthController.php';

try {
    $db = Database::getInstance();
    $controller = new GoogleOAuthController($db->getConnection());
    $controller->disconnect();
} catch (Exception $e) {
    error_log("Google OAuth disconnect error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 