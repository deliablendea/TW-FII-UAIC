<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/DropboxService.php';
require_once __DIR__ . '/../../views/JsonView.php';

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
    $dropboxService = new DropboxService($pdo);
    $userId = $_SESSION['user_id'];
    
    $file = $_FILES['file'];
    $fileName = $file['name'];
    $filePath = $file['tmp_name'];
    $targetPath = $_POST['path'] ?? '/';
    
    // Validate file size (50MB limit)
    $maxSize = 50 * 1024 * 1024; // 50MB
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 50MB']);
        exit;
    }
    
    $result = $dropboxService->uploadFile($userId, $filePath, $fileName, $targetPath);
    
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
    error_log("Dropbox upload error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 