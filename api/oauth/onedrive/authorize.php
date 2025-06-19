<?php
// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../controllers/OneDriveOAuthController.php';

try {
    $db = Database::getInstance();
    $controller = new OneDriveOAuthController($db->getConnection());
    $controller->authorize();
} catch (Exception $e) {
    error_log("OneDrive OAuth authorize error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 