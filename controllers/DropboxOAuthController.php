<?php
require_once __DIR__ . '/../config/DropboxConfig.php';
require_once __DIR__ . '/../models/OAuthToken.php';
require_once __DIR__ . '/../views/JsonView.php';

class DropboxOAuthController {
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
        $_SESSION['dropbox_oauth_state'] = $state;
        
        // Get Dropbox authorization URL
        $authUrl = DropboxConfig::getAuthUrl($state);
        
        // Redirect to Dropbox OAuth
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
        if (!isset($_SESSION['dropbox_oauth_state']) || $state !== $_SESSION['dropbox_oauth_state']) {
            unset($_SESSION['dropbox_oauth_state']);
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=error&provider=dropbox&reason=invalid_state');
            exit;
        }
        
        unset($_SESSION['dropbox_oauth_state']);
        
        // Check for authorization code
        $code = $_GET['code'] ?? '';
        if (empty($code)) {
            $error = $_GET['error'] ?? 'Authorization failed';
            error_log("Dropbox OAuth error: " . $error);
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=error&provider=dropbox&reason=no_code');
            exit;
        }
        
        // Exchange code for access token
        $tokenData = $this->exchangeCodeForToken($code);
        if (!$tokenData['success']) {
            error_log("Dropbox token exchange failed: " . json_encode($tokenData));
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=error&provider=dropbox&reason=token_exchange');
            exit;
        }
        
        // Save token to database
        $userId = $_SESSION['user_id'];
        $result = $this->oauthTokenModel->saveToken(
            $userId,
            'dropbox',
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $tokenData['expires_at'] ?? null
        );
        
        if ($result['success']) {
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=success&provider=dropbox');
        } else {
            error_log("Dropbox token save failed: " . json_encode($result));
            header('Location: ' . $baseUrl . '/TW-FII-UAIC/public/dashboard.html?oauth=error&provider=dropbox&reason=token_save');
        }
        exit;
    }
    
    public function getStatus() {
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonView->render(['success' => false, 'message' => 'User not logged in'], 401);
        }
        
        $userId = $_SESSION['user_id'];
        $isConnected = $this->oauthTokenModel->isTokenValid($userId, 'dropbox');
        
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
        $result = $this->oauthTokenModel->deleteToken($userId, 'dropbox');
        
        return $this->jsonView->render($result);
    }
    
    private function exchangeCodeForToken($code) {
        $postData = [
            'client_id' => DropboxConfig::CLIENT_ID,
            'client_secret' => DropboxConfig::CLIENT_SECRET,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => DropboxConfig::REDIRECT_URI,
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, DropboxConfig::TOKEN_URL);
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
            error_log("Dropbox token exchange failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to exchange code for token'];
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['access_token'])) {
            error_log("Invalid Dropbox token response: $response");
            return ['success' => false, 'message' => 'Invalid token response'];
        }
        
        // Dropbox tokens don't expire by default, but we'll set a far future date
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