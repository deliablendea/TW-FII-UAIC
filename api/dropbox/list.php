<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/DropboxService.php';
require_once __DIR__ . '/../../views/JsonView.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

try {
    $db = Database::getInstance();
    $dropboxService = new DropboxService($db->getConnection());
    $userId = $_SESSION['user_id'];
    $path = $_GET['path'] ?? '';
    
    $result = $dropboxService->listFiles($userId, $path);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Dropbox list files error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 