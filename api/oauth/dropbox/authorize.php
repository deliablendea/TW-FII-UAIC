<?php
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../controllers/DropboxOAuthController.php';

try {
    $db = Database::getInstance();
    $controller = new DropboxOAuthController($db->getConnection());
    $controller->authorize();
} catch (Exception $e) {
    error_log("Dropbox OAuth authorize error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 