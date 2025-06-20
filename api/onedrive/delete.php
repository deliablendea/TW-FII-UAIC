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
    
    // Check if this is a DELETE request or POST request  
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Get file ID from request
    $fileId = null;
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // For DELETE requests, check query parameters first, then JSON body
        if (isset($_GET['fileId'])) {
            $fileId = $_GET['fileId'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['fileId'])) {
                $fileId = $input['fileId'];
            }
        }
    } else {
        // For POST requests, check POST data or JSON body
        if (isset($_POST['fileId'])) {
            $fileId = $_POST['fileId'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['fileId'])) {
                $fileId = $input['fileId'];
            }
        }
    }
    
    if (!$fileId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing file ID']);
        exit;
    }
    
    // Initialize OneDrive service
    $db = Database::getInstance();
    $oneDriveService = new OneDriveService($db->getConnection());
    
    // Delete file from OneDrive
    $result = $oneDriveService->deleteFile($_SESSION['user_id'], $fileId);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("OneDrive delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 