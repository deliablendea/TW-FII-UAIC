<?php
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../controllers/MegaAuthController.php';

try {
    $controller = new MegaAuthController($pdo);
    $controller->showLoginForm();
} catch (Exception $e) {
    error_log("MEGA connect error: " . $e->getMessage());
    echo "<h1>Error</h1><p>Failed to load MEGA connection form.</p>";
}
?> 