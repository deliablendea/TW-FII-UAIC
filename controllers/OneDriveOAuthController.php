<?php
require_once __DIR__ . '/../config/OneDriveConfig.php';
require_once __DIR__ . '/../models/OAuthToken.php';
require_once __DIR__ . '/../views/JsonView.php';

class OneDriveOAuthController {
    private $oauthTokenModel;
    private $jsonView;
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->oauthTokenModel = new OAuthToken($pdo);
        $this->jsonView = new JsonView();
    }
    
    public function authorize() {
        session_start();
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonView->render(['success' => false, 'message' => 'User not logged in'], 401);
        }
        
        // Generate state parameter for CSRF protection
        $state = bin2hex(random_bytes(32));
        $_SESSION['onedrive_oauth_state'] = $state;
        
        // Get OneDrive authorization URL
        $authUrl = OneDriveConfig::getAuthUrl($state);
        
        // Redirect to Microsoft OAuth
        header('Location: ' . $authUrl);
        exit;
    }
    
    public function callback() {
        session_start();
        
        // Get the base URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . '://' . $host;
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/login.html');
            exit;
        }
        
        // Verify state parameter
        $state = $_GET['state'] ?? '';
        if (!isset($_SESSION['onedrive_oauth_state']) || $state !== $_SESSION['onedrive_oauth_state']) {
            unset($_SESSION['onedrive_oauth_state']);
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=error&provider=onedrive&reason=invalid_state');
            exit;
        }
        
        unset($_SESSION['onedrive_oauth_state']);
        
        // Check for authorization code
        $code = $_GET['code'] ?? '';
        if (empty($code)) {
            $error = $_GET['error'] ?? 'Authorization failed';
            error_log("OneDrive OAuth error: " . $error);
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=error&provider=onedrive&reason=no_code');
            exit;
        }
        
        // Exchange code for access token
        $tokenData = $this->exchangeCodeForToken($code);
        if (!$tokenData['success']) {
            error_log("OneDrive token exchange failed: " . json_encode($tokenData));
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=error&provider=onedrive&reason=token_exchange');
            exit;
        }
        
        // Save token to database
        $userId = $_SESSION['user_id'];
        $result = $this->oauthTokenModel->saveToken(
            $userId,
            'onedrive',
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $tokenData['expires_at'] ?? null
        );
        
        if ($result['success']) {
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=success&provider=onedrive');
        } else {
            error_log("OneDrive token save failed: " . json_encode($result));
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=error&provider=onedrive&reason=token_save');
        }
        exit;
    }
    
    public function getStatus() {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonView->render(['success' => false, 'message' => 'User not logged in'], 401);
        }
        
        $userId = $_SESSION['user_id'];
        $isConnected = $this->oauthTokenModel->isTokenValid($userId, 'onedrive');
        
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
        $result = $this->oauthTokenModel->deleteToken($userId, 'onedrive');
        
        return $this->jsonView->render($result);
    }
    
    private function exchangeCodeForToken($code) {
        $postData = [
            'client_id' => OneDriveConfig::CLIENT_ID,
            'client_secret' => OneDriveConfig::CLIENT_SECRET,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => OneDriveConfig::REDIRECT_URI,
            'scope' => OneDriveConfig::SCOPES
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, OneDriveConfig::TOKEN_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("OneDrive token exchange failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to exchange code for token'];
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['access_token'])) {
            error_log("Invalid OneDrive token response: $response");
            return ['success' => false, 'message' => 'Invalid token response'];
        }
        
        $expiresAt = null;
        if (isset($data['expires_in'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + $data['expires_in']);
        }
        
        return [
            'success' => true,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => $expiresAt
        ];
    }
}
?> 