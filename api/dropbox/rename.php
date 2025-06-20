<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/DropboxService.php';
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
    
    // Check if this is a POST or PATCH request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PATCH') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Get file path and new name from request
    $filePath = null;
    $newName = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // For POST requests, check POST data or JSON body
        if (isset($_POST['path']) && isset($_POST['newName'])) {
            $filePath = $_POST['path'];
            $newName = $_POST['newName'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['path']) && isset($input['newName'])) {
                $filePath = $input['path'];
                $newName = $input['newName'];
            }
        }
    } else {
        // For PATCH requests, check JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['path']) && isset($input['newName'])) {
            $filePath = $input['path'];
            $newName = $input['newName'];
        }
    }
    
    if (!$filePath || !$newName) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing file path or new name']);
        exit;
    }
    
    // Validate new name
    $newName = trim($newName);
    if (empty($newName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'New name cannot be empty']);
        exit;
    }
    
    // Initialize Dropbox service
    $db = Database::getInstance();
    $dropboxService = new DropboxService($db->getConnection());
    
    // Add debug logging
    error_log("Dropbox rename attempt - Path: $filePath, New Name: $newName");
    
    // Rename file in Dropbox
    $result = $dropboxService->renameFile($_SESSION['user_id'], $filePath, $newName);
    
    // Log the result for debugging
    error_log("Dropbox rename result: " . json_encode($result));
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("Dropbox rename error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 