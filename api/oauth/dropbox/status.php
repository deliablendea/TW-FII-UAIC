<?php
// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

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
    
    // Create OAuth model
    $oauthTokenModel = new OAuthToken($pdo);
    
    // Check if connected
    $userId = $_SESSION['user_id'];
    $isConnected = $oauthTokenModel->isTokenValid($userId, 'dropbox');
    
    echo json_encode([
        'success' => true,
        'connected' => $isConnected,
        'debug' => [
            'user_id' => $userId,
            'token_exists' => $oauthTokenModel->getToken($userId, 'dropbox') !== false
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Dropbox OAuth status error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Internal server error',
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?> 