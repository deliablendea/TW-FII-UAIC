<?php
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../controllers/DropboxOAuthController.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $controller = new DropboxOAuthController($pdo);
    $controller->disconnect();
} catch (Exception $e) {
    error_log("Dropbox OAuth disconnect error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 