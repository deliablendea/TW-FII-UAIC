<?php
require_once __DIR__ . '/../config/MegaConfig.php';
require_once __DIR__ . '/../models/OAuthToken.php';
require_once __DIR__ . '/../views/JsonView.php';

class MegaAuthController {
    private $oauthTokenModel;
    private $jsonView;
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->oauthTokenModel = new OAuthToken($pdo);
        $this->jsonView = new JsonView();
    }
    
    public function login() {
        session_start();
        
        // Check if user is logged in to our system
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonView->render(['success' => false, 'message' => 'User not logged in'], 401);
        }
        
        // Get MEGA credentials from POST
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            return $this->jsonView->render(['success' => false, 'message' => 'Email and password are required'], 400);
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonView->render(['success' => false, 'message' => 'Invalid email format'], 400);
        }
        
        try {
            // Attempt to login to MEGA
            $loginResult = MegaConfig::login($email, $password);
            
            if (!$loginResult['success']) {
                return $this->jsonView->render(['success' => false, 'message' => 'MEGA login failed: ' . ($loginResult['error'] ?? 'Unknown error')]);
            }
            
            // Encrypt and store credentials
            $encryptedCredentials = MegaConfig::encryptCredentials($email, $password);
            
            $userId = $_SESSION['user_id'];
            $result = $this->oauthTokenModel->saveToken(
                $userId,
                'mega',
                $encryptedCredentials, // Store encrypted credentials as "access_token"
                $loginResult['session_id'], // Store session ID as "refresh_token"
                null // MEGA sessions don't have explicit expiry
            );
            
            if ($result['success']) {
                return $this->jsonView->render(['success' => true, 'message' => 'MEGA connected successfully']);
            } else {
                return $this->jsonView->render(['success' => false, 'message' => 'Failed to save MEGA credentials']);
            }
            
        } catch (Exception $e) {
            error_log("MEGA login error: " . $e->getMessage());
            return $this->jsonView->render(['success' => false, 'message' => 'Internal server error']);
        }
    }
    
    public function getStatus() {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonView->render(['success' => false, 'message' => 'User not logged in'], 401);
        }
        
        $userId = $_SESSION['user_id'];
        $tokenData = $this->oauthTokenModel->getToken($userId, 'mega');
        
        $isConnected = false;
        if ($tokenData && !empty($tokenData['access_token'])) {
            // Try to validate the stored credentials
            try {
                $credentials = MegaConfig::decryptCredentials($tokenData['access_token']);
                if ($credentials && isset($credentials['email']) && isset($credentials['password'])) {
                    // Test if credentials still work
                    $testLogin = MegaConfig::login($credentials['email'], $credentials['password']);
                    $isConnected = $testLogin['success'];
                }
            } catch (Exception $e) {
                error_log("MEGA status check error: " . $e->getMessage());
            }
        }
        
        return $this->jsonView->render([
            'success' => true,
            'connected' => $isConnected
        ]);
    }
    
    public function disconnect() {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonView->render(['success' => false, 'message' => 'User not logged in'], 401);
        }
        
        $userId = $_SESSION['user_id'];
        $result = $this->oauthTokenModel->deleteToken($userId, 'mega');
        
        return $this->jsonView->render($result);
    }
    
    public function showLoginForm() {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            header('Location: /TW-FII-UAIC/public/login.html');
            exit;
        }
        
        // Return a simple login form
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Connect to MEGA</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 400px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="email"], input[type="password"] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #e53e3e; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; width: 100%; }
        button:hover { background: #c53030; }
        .alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .alert-error { background: #fed7d7; color: #742a2a; border: 1px solid #fc8181; }
        .alert-success { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
    </style>
</head>
<body>
    <h1>Connect to MEGA</h1>
    <div id="message"></div>
    <form id="megaLoginForm">
        <div class="form-group">
            <label for="email">MEGA Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">MEGA Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Connect to MEGA</button>
    </form>
    <p><a href="/TW-FII-UAIC/public/dashboard.html">← Back to Dashboard</a></p>
    
    <script>
        document.getElementById("megaLoginForm").addEventListener("submit", function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const messageDiv = document.getElementById("message");
            
            fetch("../mega/login.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageDiv.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                    setTimeout(() => {
                        window.location.href = "/TW-FII-UAIC/public/dashboard.html?mega=success";
                    }, 1500);
                } else {
                    messageDiv.innerHTML = `<div class="alert alert-error">${data.message}</div>`;
                }
            })
            .catch(error => {
                messageDiv.innerHTML = `<div class="alert alert-error">Connection error: ${error.message}</div>`;
            });
        });
    </script>
</body>
</html>';
    }
}
?> 