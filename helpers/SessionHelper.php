<?php
require_once __DIR__ . '/../config/SessionConfig.php';

class SessionHelper {
    
    /**
     * Check if user is authenticated and redirect if not
     */
    public static function requireAuth($redirectTo = '/TW-FII-UAIC/public/login.html') {
        if (!SessionConfig::isLoggedIn()) {
            if (self::isAjaxRequest()) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Authentication required']);
                exit;
            } else {
                header('Location: ' . $redirectTo);
                exit;
            }
        }
    }
    
    /**
     * Get current user data
     */
    public static function getUser() {
        return SessionConfig::getUserData();
    }
    
    /**
     * Get current user ID
     */
    public static function getUserId() {
        return SessionConfig::getUserId();
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return SessionConfig::isLoggedIn();
    }
    
    /**
     * Login user with user data
     */
    public static function login($user) {
        SessionConfig::setUserSession($user);
    }
    
    /**
     * Logout user
     */
    public static function logout() {
        SessionConfig::destroy();
    }
    
    /**
     * Set a flash message (temporary message)
     */
    public static function setFlash($type, $message) {
        SessionConfig::init();
        $_SESSION['flash'][$type] = $message;
    }
    
    /**
     * Get and clear flash messages
     */
    public static function getFlash($type = null) {
        SessionConfig::init();
        
        if ($type) {
            $message = $_SESSION['flash'][$type] ?? null;
            unset($_SESSION['flash'][$type]);
            return $message;
        }
        
        $messages = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $messages;
    }
    
    /**
     * Check if request is AJAX
     */
    private static function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    }
    
    /**
     * Set a custom session value
     */
    public static function set($key, $value) {
        SessionConfig::init();
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get a custom session value
     */
    public static function get($key, $default = null) {
        SessionConfig::init();
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Remove a custom session value
     */
    public static function remove($key) {
        SessionConfig::init();
        unset($_SESSION[$key]);
    }
}
?> 