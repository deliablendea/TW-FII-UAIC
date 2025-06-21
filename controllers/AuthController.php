<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../views/JsonView.php';

class AuthController {
    private $userModel;
    private $emailService;
    private $jsonView;
    
    public function __construct($pdo) {
        $this->userModel = new User($pdo);
        $this->emailService = new EmailService();
        $this->jsonView = new JsonView();
    }
    
    public function register() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonView->render(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        
        $validation = $this->validateRegistration($name, $email, $password, $confirmPassword);
        if (!$validation['success']) {
            return $this->jsonView->render($validation);
        }
        
        $result = $this->userModel->create($name, $email, $password);
        
        // Send welcome email if registration was successful
        if ($result['success']) {
            try {
                $this->emailService->sendWelcomeEmail($email, $name);
            } catch (Exception $e) {
                // Log error but don't fail registration if email fails
                error_log("Failed to send welcome email: " . $e->getMessage());
            }
        }
        
        return $this->jsonView->render($result);
    }
    
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonView->render(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $validation = $this->validateLogin($email, $password);
        if (!$validation['success']) {
            return $this->jsonView->render($validation);
        }
        
        $user = $this->userModel->findByEmail($email);
        if (!$user) {
            return $this->jsonView->render(['success' => false, 'message' => 'Invalid email or password']);
        }
        
        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            return $this->jsonView->render(['success' => false, 'message' => 'Invalid email or password']);
        }
        
        $this->startSession($user);
        
        return $this->jsonView->render([
            'success' => true,
            'message' => 'Login successful! Redirecting...',
            'redirect' => 'dashboard.html'
        ]);
    }
    
    public function logout() {
        session_start();
        session_destroy();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        return $this->jsonView->render(['success' => true, 'message' => 'Logged out successfully']);
    }
    
    public function checkSession() {
        session_start();
        
        if (isset($_SESSION['user_id'])) {
            return $this->jsonView->render([
                'success' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'name' => $_SESSION['user_name'],
                    'email' => $_SESSION['user_email']
                ]
            ]);
        } else {
            return $this->jsonView->render(['success' => false, 'message' => 'Not logged in']);
        }
    }
    
    public function deleteAccount() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonView->render(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return $this->jsonView->render(['success' => false, 'message' => 'User not logged in'], 401);
        }
        
        $userId = $_SESSION['user_id'];
        $password = $_POST['password'] ?? '';
        $confirmation = $_POST['confirmation'] ?? '';
        
        // Validate input
        if (empty($password)) {
            return $this->jsonView->render(['success' => false, 'message' => 'Password is required for account deletion']);
        }
        
        if ($confirmation !== 'DELETE') {
            return $this->jsonView->render(['success' => false, 'message' => 'Please type "DELETE" to confirm account deletion']);
        }
        
        // Get user data and verify password
        $user = $this->userModel->findById($userId);
        if (!$user) {
            return $this->jsonView->render(['success' => false, 'message' => 'User not found']);
        }
        
        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            return $this->jsonView->render(['success' => false, 'message' => 'Invalid password']);
        }
        
        try {
            // Delete account and all associated data
            $result = $this->userModel->deleteAccount($userId);
            
            if ($result['success']) {
                // Destroy session
                session_destroy();
                
                if (ini_get("session.use_cookies")) {
                    $params = session_get_cookie_params();
                    setcookie(session_name(), '', time() - 42000,
                        $params["path"], $params["domain"],
                        $params["secure"], $params["httponly"]
                    );
                }
                
                return $this->jsonView->render([
                    'success' => true, 
                    'message' => 'Account deleted successfully. We\'re sorry to see you go!'
                ]);
            } else {
                return $this->jsonView->render([
                    'success' => false, 
                    'message' => $result['message'] ?? 'Failed to delete account'
                ]);
            }
            
        } catch (Exception $e) {
            error_log("Account deletion error: " . $e->getMessage());
            return $this->jsonView->render([
                'success' => false, 
                'message' => 'An error occurred while deleting your account. Please try again.'
            ]);
        }
    }
    
    private function startSession($user) {
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
    }
    
    private function validateRegistration($name, $email, $password, $confirmPassword) {
        if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {
            return ['success' => false, 'message' => 'Please fill in all fields'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters long'];
        }
        
        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        
        return ['success' => true];
    }
    
    private function validateLogin($email, $password) {
        if (empty($email) || empty($password)) {
            return ['success' => false, 'message' => 'Please fill in all fields'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        return ['success' => true];
    }
}
?> 