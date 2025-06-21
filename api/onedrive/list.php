<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/OneDriveService.php';

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
    $onedriveService = new OneDriveService($db->getConnection());
    $userId = $_SESSION['user_id'];
    $path = $_GET['path'] ?? '';
    $folderId = $_GET['folderId'] ?? null;
    
    $result = $onedriveService->listFiles($userId, $path, $folderId);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("OneDrive list files error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 