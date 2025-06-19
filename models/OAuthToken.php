<?php
class OAuthToken {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function saveToken($userId, $provider, $accessToken, $refreshToken = null, $expiresAt = null) {
        try {
            // Check if token already exists for this user and provider
            $existingToken = $this->getToken($userId, $provider);
            
            if ($existingToken) {
                // Update existing token
                $stmt = $this->pdo->prepare(
                    "UPDATE oauth_tokens SET access_token = ?, refresh_token = ?, expires_at = ?, updated_at = NOW() 
                     WHERE user_id = ? AND provider = ?"
                );
                $stmt->execute([$accessToken, $refreshToken, $expiresAt, $userId, $provider]);
            } else {
                // Insert new token
                $stmt = $this->pdo->prepare(
                    "INSERT INTO oauth_tokens (user_id, provider, access_token, refresh_token, expires_at, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())"
                );
                $stmt->execute([$userId, $provider, $accessToken, $refreshToken, $expiresAt]);
            }
            
            return ['success' => true, 'message' => 'Token saved successfully'];
            
        } catch (PDOException $e) {
            error_log("OAuth token save error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to save token'];
        }
    }
    
    public function getToken($userId, $provider) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT * FROM oauth_tokens WHERE user_id = ? AND provider = ?"
            );
            $stmt->execute([$userId, $provider]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("OAuth token fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    public function refreshToken($userId, $provider, $newAccessToken, $newRefreshToken = null, $expiresAt = null) {
        return $this->saveToken($userId, $provider, $newAccessToken, $newRefreshToken, $expiresAt);
    }
    
    public function deleteToken($userId, $provider) {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM oauth_tokens WHERE user_id = ? AND provider = ?"
            );
            $stmt->execute([$userId, $provider]);
            return ['success' => true, 'message' => 'Token deleted successfully'];
        } catch (PDOException $e) {
            error_log("OAuth token delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete token'];
        }
    }
    
    public function isTokenValid($userId, $provider) {
        $token = $this->getToken($userId, $provider);
        if (!$token) {
            return false;
        }
        
        // If no expiration time is set, consider it valid
        if (!$token['expires_at']) {
            return true;
        }
        
        // Check if token is expired
        return strtotime($token['expires_at']) > time();
    }
}
?> 