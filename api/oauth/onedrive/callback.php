<?php
// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Add debug logging
error_log("OneDrive callback started with parameters: " . print_r($_GET, true));

try {
    require_once __DIR__ . '/../../../config/Database.php';
    require_once __DIR__ . '/../../../controllers/OneDriveOAuthController.php';
    
    $db = Database::getInstance();
    $controller = new OneDriveOAuthController($db->getConnection());
    $controller->callback();
    
} catch (Exception $e) {
    error_log("OneDrive OAuth callback error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Show error for debugging
    echo "<h1>OneDrive OAuth Callback Error</h1>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "<p><strong>Stack trace:</strong></p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "<p><a href='/TW-FII-UAIC/public/dashboard.html'>← Back to Dashboard</a></p>";
    
    // Also try to redirect after showing error
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host;
    
    echo "<script>setTimeout(() => { window.location.href = '" . $baseUrl . "/TW-FII-UAIC/public/dashboard.html?oauth=error&provider=onedrive&reason=server_error'; }, 5000);</script>";
    exit;
}
?> 