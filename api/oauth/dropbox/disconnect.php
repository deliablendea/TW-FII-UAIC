<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }
    
    require_once __DIR__ . '/../../../config/Database.php';
    require_once __DIR__ . '/../../../controllers/DropboxOAuthController.php';
    
    $db = Database::getInstance();
    $controller = new DropboxOAuthController($db->getConnection());
    $controller->disconnect();
    
} catch (Exception $e) {
    error_log("Dropbox OAuth disconnect error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 