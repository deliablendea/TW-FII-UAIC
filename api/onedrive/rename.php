<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../services/OneDriveService.php';
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
    
    // Check if this is a PUT request or POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Get parameters from request
    $fileId = null;
    $newName = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        // For PUT requests, check JSON body
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['fileId'])) {
            $fileId = $input['fileId'];
        }
        if (isset($input['newName'])) {
            $newName = $input['newName'];
        }
    } else {
        // For POST requests, check POST data or JSON body
        if (isset($_POST['fileId'])) {
            $fileId = $_POST['fileId'];
        }
        if (isset($_POST['newName'])) {
            $newName = $_POST['newName'];
        }
        
        if (!$fileId || !$newName) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['fileId'])) {
                $fileId = $input['fileId'];
            }
            if (isset($input['newName'])) {
                $newName = $input['newName'];
            }
        }
    }
    
    if (!$fileId || !$newName) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing file ID or new name']);
        exit;
    }
    
    // Validate the new name
    $newName = trim($newName);
    if (empty($newName)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File name cannot be empty']);
        exit;
    }
    
    // Initialize OneDrive service
    $db = Database::getInstance();
    $oneDriveService = new OneDriveService($db->getConnection());
    
    // Rename file in OneDrive
    $result = $oneDriveService->renameFile($_SESSION['user_id'], $fileId, $newName);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("OneDrive rename error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 