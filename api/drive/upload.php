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
    
    // Check if this is a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }
    
    $uploadedFile = $_FILES['file'];
    $fileName = $uploadedFile['name'];
    $tempPath = $uploadedFile['tmp_name'];
    
    // Validate file size (max 100MB for this example)
    $maxSize = 100 * 1024 * 1024; // 100MB
    if ($uploadedFile['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File too large (max 100MB)']);
        exit;
    }
    
    // Initialize Google Drive service
    $db = Database::getInstance();
    $driveService = new GoogleDriveService($db->getConnection(), $_SESSION['user_id']);
    
    // Upload file to Google Drive
    $result = $driveService->uploadFile($tempPath, $fileName);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("Google Drive upload error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 