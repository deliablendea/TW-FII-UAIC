<?php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/PasswordReset.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../views/JsonView.php';

class PasswordResetController {
    private $userModel;
    private $passwordResetModel;
    private $emailService;
    private $jsonView;
    
    public function __construct($pdo) {
        $this->userModel = new User($pdo);
        $this->passwordResetModel = new PasswordReset($pdo);
        $this->emailService = new EmailService();
        $this->jsonView = new JsonView();
    }
    
   
    public function forgotPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonView->render(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        
        $email = trim($_POST['email'] ?? '');
        
        // Validate email
        if (empty($email)) {
            return $this->jsonView->render(['success' => false, 'message' => 'Email is required']);
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->jsonView->render(['success' => false, 'message' => 'Invalid email format']);
        }
        
        // Find user by email
        $user = $this->userModel->findByEmail($email);
        
        // For security, always return success message (don't reveal if email exists)
        if (!$user) {
            return $this->jsonView->render([
                'success' => true, 
                'message' => 'If this email exists in our system, you will receive a password reset link shortly.'
            ]);
        }
        
        // Create reset token
        $tokenResult = $this->passwordResetModel->createToken($user['id']);
        
        if (!$tokenResult['success']) {
            return $this->jsonView->render(['success' => false, 'message' => 'Failed to process request. Please try again.']);
        }
        
        // Send reset email
        $emailSent = $this->emailService->sendPasswordResetEmail($user['email'], $user['name'], $tokenResult['token']);
        
        if ($emailSent) {
            return $this->jsonView->render([
                'success' => true, 
                'message' => 'If this email exists in our system, you will receive a password reset link shortly.'
            ]);
        } else {
            return $this->jsonView->render(['success' => false, 'message' => 'Failed to send reset email. Please try again.']);
        }
    }
    
    /**
     * Validate reset token
     */
    public function validateToken() {
        $token = $_GET['token'] ?? '';
        
        if (empty($token)) {
            return $this->jsonView->render(['valid' => false, 'message' => 'Token is required']);
        }
        
        $validation = $this->passwordResetModel->validateToken($token);
        
        if ($validation['valid']) {
            return $this->jsonView->render([
                'valid' => true,
                'user' => [
                    'email' => $validation['data']['email'],
                    'name' => $validation['data']['name']
                ]
            ]);
        } else {
            return $this->jsonView->render(['valid' => false, 'message' => $validation['message']]);
        }
    }
    
    /**
     * Reset password
     */
    public function resetPassword() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->jsonView->render(['success' => false, 'message' => 'Method not allowed'], 405);
        }
        
        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate input
        if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
            return $this->jsonView->render(['success' => false, 'message' => 'All fields are required']);
        }
        
        if ($newPassword !== $confirmPassword) {
            return $this->jsonView->render(['success' => false, 'message' => 'Passwords do not match']);
        }
        
        if (strlen($newPassword) < 6) {
            return $this->jsonView->render(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        }
        
        // Validate token
        $tokenValidation = $this->passwordResetModel->validateToken($token);
        
        if (!$tokenValidation['valid']) {
            return $this->jsonView->render(['success' => false, 'message' => $tokenValidation['message']]);
        }
        
        $userId = $tokenValidation['data']['user_id'];
        
        // Update password
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateResult = $this->userModel->update($userId, ['password_hash' => $passwordHash]);
        
        if ($updateResult['success']) {
            // Delete the used token
            $this->passwordResetModel->useToken($token);
            
            return $this->jsonView->render([
                'success' => true, 
                'message' => 'Password has been reset successfully. You can now log in with your new password.'
            ]);
        } else {
            return $this->jsonView->render(['success' => false, 'message' => 'Failed to update password. Please try again.']);
        }
    }
}
?> 