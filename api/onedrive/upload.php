<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/OneDriveService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

try {
    $db = Database::getInstance();
    $onedriveService = new OneDriveService($db->getConnection());
    $userId = $_SESSION['user_id'];
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $filePath = $file['tmp_name'];
    $targetPath = $_POST['path'] ?? '/';
    
    // Validate file size (4MB limit for simple upload)
    $maxSize = 4 * 1024 * 1024; // 4MB
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 4MB']);
        exit;
    }
    
    $result = $onedriveService->uploadFile($userId, $filePath, $fileName, $targetPath);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'file' => $result
        ]);
    } else {
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("OneDrive upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 