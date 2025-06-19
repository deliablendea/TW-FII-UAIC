<?php
require_once __DIR__ . '/../../../config/Database.php';
require_once __DIR__ . '/../../../controllers/GoogleOAuthController.php';

try {
    $db = Database::getInstance();
    $controller = new GoogleOAuthController($db->getConnection());
    $controller->callback();
} catch (Exception $e) {
    error_log("Google OAuth callback error: " . $e->getMessage());
    
    // Get the base URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $baseUrl = $protocol . '://' . $host;
    
    header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=error&reason=exception');
    exit;
}
?> 