<?php
// Prevent any output before binary data
ob_start();

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

// Add debugging for development
if (isset($_GET['debug'])) {
    error_log("Fragmentation API called with action: " . ($_GET['action'] ?? 'none'));
    error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Session status: " . (session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive'));
}

// Include the fragmentation controller
try {
    require_once __DIR__ . '/../controllers/FragmentationController.php';
    
    $controller = new FragmentationController();
    
    // For download requests, clean output buffer before processing
    if (isset($_GET['action']) && $_GET['action'] === 'download') {
        // Clean any existing output
        while (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    $controller->handleRequest();
} catch (Exception $e) {
    // Clean output buffer for error responses too
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage(),
        'debug_info' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ]);
    error_log('Fragmentation API error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
}
?> 