<?php
class PasswordReset {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
   
    public function createToken($userId) {
        try {
            // Clean up old tokens for this user
            $this->cleanupOldTokens($userId);
            
            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $this->pdo->prepare(
                "INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)"
            );
            $stmt->execute([$userId, $token, $expiresAt]);
            
            return ['success' => true, 'token' => $token];
            
        } catch (PDOException $e) {
            error_log("Password reset token creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create reset token'];
        }
    }
    
   
    public function validateToken($token) {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT pr.*, u.email, u.name 
                 FROM password_resets pr 
                 JOIN users u ON pr.user_id = u.id 
                 WHERE pr.token = ? AND pr.expires_at > CURRENT_TIMESTAMP"
            );
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return ['valid' => true, 'data' => $result];
            } else {
                return ['valid' => false, 'message' => 'Invalid or expired token'];
            }
            
        } catch (PDOException $e) {
            error_log("Password reset token validation error: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Token validation failed'];
        }
    }
    
   
    public function useToken($token) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Password reset token deletion error: " . $e->getMessage());
            return false;
        }
    }
    
   
    private function cleanupOldTokens($userId) {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM password_resets WHERE user_id = ? OR expires_at < CURRENT_TIMESTAMP"
            );
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Password reset cleanup error: " . $e->getMessage());
        }
    }
}
?> 