<?php
class SessionConfig {
    public static function init() {
        // Configure session settings before starting
        if (session_status() === PHP_SESSION_NONE) {
            // Session security settings
            ini_set('session.cookie_httponly', 1);  // Prevent XSS attacks
            ini_set('session.cookie_secure', 0);    // Set to 1 for HTTPS
            ini_set('session.use_strict_mode', 1);  // Prevent session fixation
            ini_set('session.cookie_samesite', 'Lax'); // CSRF protection
            
            // Session lifetime (24 hours)
            ini_set('session.gc_maxlifetime', 86400);
            ini_set('session.cookie_lifetime', 86400);
            
            // Session name
            session_name('CLOUD9_SESSION');
            
            // Start session
            session_start();
            
            // Regenerate session ID periodically for security
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) { // 30 minutes
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    public static function isLoggedIn() {
        self::init();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    public static function getUserId() {
        self::init();
        return $_SESSION['user_id'] ?? null;
    }
    
    public static function getUserData() {
        self::init();
        if (self::isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email']
            ];
        }
        return null;
    }
    
    public static function destroy() {
        self::init();
        
        // Destroy session data
        $_SESSION = array();
        
        // Delete session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    public static function setUserSession($user) {
        self::init();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['login_time'] = time();
    }
}
?> 