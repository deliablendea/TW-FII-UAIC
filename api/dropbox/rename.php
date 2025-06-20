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
    
    // Check if this is a PATCH request or POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'PATCH' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Get file path and new name from request
    $filePath = null;
    $newName = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // For PATCH requests, get data from JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['path'])) {
            $filePath = $input['path'];
        }
        if (isset($input['newName'])) {
            $newName = $input['newName'];
        }
    } else {
        // For POST requests, check POST data or JSON body
        if (isset($_POST['path'])) {
            $filePath = $_POST['path'];
        }
        if (isset($_POST['newName'])) {
            $newName = $_POST['newName'];
        }
        
        if (!$filePath || !$newName) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['path'])) {
                $filePath = $input['path'];
            }
            if (isset($input['newName'])) {
                $newName = $input['newName'];
            }
        }
    }
    
    if (!$filePath) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing file path']);
        exit;
    }
    
    if (!$newName || trim($newName) === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing or empty new file name']);
        exit;
    }
    
    // Initialize Dropbox service
    $db = Database::getInstance();
    $dropboxService = new DropboxService($db->getConnection());
    
    // Rename file in Dropbox
    $result = $dropboxService->renameFile($_SESSION['user_id'], $filePath, trim($newName));
    
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