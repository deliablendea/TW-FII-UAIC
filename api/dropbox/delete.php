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
    
    // Check if this is a DELETE request or POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    // Get file path from request
    $filePath = null;
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // For DELETE requests, check query parameters first, then JSON body
        if (isset($_GET['path'])) {
            $filePath = $_GET['path'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['path'])) {
                $filePath = $input['path'];
            }
        }
    } else {
        // For POST requests, check POST data or JSON body
        if (isset($_POST['path'])) {
            $filePath = $_POST['path'];
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['path'])) {
                $filePath = $input['path'];
            }
        }
    }
    
    if (!$filePath) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing file path']);
        exit;
    }
    
    // Initialize Dropbox service
    $db = Database::getInstance();
    $dropboxService = new DropboxService($db->getConnection());
    
    // Delete file from Dropbox
    $result = $dropboxService->deleteFile($_SESSION['user_id'], $filePath);
    
    if ($result['success']) {
        echo json_encode($result);
    } else {
        http_response_code(500);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("Dropbox delete error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?> 