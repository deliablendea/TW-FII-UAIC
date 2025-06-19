<?php
// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Start session
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }
    
    // Include required files
    require_once __DIR__ . '/../../../config/Database.php';
    require_once __DIR__ . '/../../../models/OAuthToken.php';
    
    // Create OAuth model and delete token
    $db = Database::getInstance();
    $oauthTokenModel = new OAuthToken($db->getConnection());
    $userId = $_SESSION['user_id'];
    $result = $oauthTokenModel->deleteToken($userId, 'onedrive');
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("OneDrive OAuth disconnect error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'error' => $e->getMessage()
    ]);
}
?> 