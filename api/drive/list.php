<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/GoogleDriveService.php';
require_once __DIR__ . '/../../views/JsonView.php';

header('Content-Type: application/json');

try {
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }
    
    // Get pagination and folder parameters
    $pageSize = isset($_GET['pageSize']) ? (int)$_GET['pageSize'] : 10;
    $pageToken = $_GET['pageToken'] ?? null;
    $folderId = $_GET['folderId'] ?? null;
    
    // Validate page size
    if ($pageSize < 1 || $pageSize > 100) {
        $pageSize = 10;
    }
    
    // Initialize Google Drive service
    $db = Database::getInstance();
    $driveService = new GoogleDriveService($db->getConnection(), $_SESSION['user_id']);
    
    // List files from Google Drive with optional folder filter
    $result = $driveService->listFiles($pageSize, $pageToken, $folderId);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("Google Drive list error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 