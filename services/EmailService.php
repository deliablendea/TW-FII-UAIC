<?php
require_once __DIR__ . '/../config/EmailConfig.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include Composer autoloader (assuming PHPMailer is installed via Composer)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // If PHPMailer is installed manually, include the files
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
}

class EmailService {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }
    
    /**
     * Configure PHPMailer with SMTP settings
     */
    private function configureMailer() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $smtpConfig = EmailConfig::getSmtpConfig();
            $senderConfig = EmailConfig::getSenderConfig();
            
            $this->mailer->Host       = $smtpConfig['host'];
            $this->mailer->SMTPAuth   = $smtpConfig['auth'];
            $this->mailer->Username   = $smtpConfig['username'];
            $this->mailer->Password   = $smtpConfig['password'];
            $this->mailer->Port       = $smtpConfig['port'];
            
            // Set encryption
            if ($smtpConfig['secure'] === 'tls') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($smtpConfig['secure'] === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
            
            // Default sender
            $this->mailer->setFrom($senderConfig['from_email'], $senderConfig['from_name']);
            $this->mailer->addReplyTo($senderConfig['reply_to'], $senderConfig['from_name']);
            
            // Character set
            $this->mailer->CharSet = 'UTF-8';
            
            // Enable verbose debug output (disable in production)
            // $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
            
        } catch (Exception $e) {
            error_log("Mailer configuration failed: " . $e->getMessage());
            throw new Exception("Email configuration failed");
        }
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($to, $name, $token) {
        if (!EmailConfig::isConfigured()) {
            return $this->logEmailForDevelopment($to, 'Password Reset', $token);
        }
        
        try {
            // Clear any previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Recipients
            $this->mailer->addAddress($to, $name);
            
            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Password Reset Request - Cloud9';
            
            $resetUrl = $this->getResetUrl($token);
            $this->mailer->Body = $this->getPasswordResetHtmlTemplate($name, $resetUrl);
            $this->mailer->AltBody = $this->getPasswordResetTextTemplate($name, $resetUrl);
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send password reset email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send welcome email (bonus feature)
     */
    public function sendWelcomeEmail($to, $name) {
        if (!EmailConfig::isConfigured()) {
            return $this->logEmailForDevelopment($to, 'Welcome Email', 'Welcome to Cloud9!');
        }
        
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            $this->mailer->addAddress($to, $name);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Welcome to Cloud9!';
            
            $this->mailer->Body = $this->getWelcomeHtmlTemplate($name);
            $this->mailer->AltBody = $this->getWelcomeTextTemplate($name);
            
            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            error_log("Failed to send welcome email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log email for development purposes when email is not configured
     */
    private function logEmailForDevelopment($to, $subject, $content) {
        $logEntry = "=== EMAIL LOG ===" . PHP_EOL .
                   "Date: " . date('Y-m-d H:i:s') . PHP_EOL .
                   "To: " . $to . PHP_EOL .
                   "Subject: " . $subject . PHP_EOL .
                   "Content: " . $content . PHP_EOL .
                   "==================" . PHP_EOL . PHP_EOL;
        
        error_log($logEntry);
        
        // Also save to a file for easy viewing
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logDir . '/email.log', $logEntry, FILE_APPEND | LOCK_EX);
        
        return true; // Simulate successful send
    }
    
    private function getResetUrl($token) {
        $appUrl = EmailConfig::getAppUrl();
        return $appUrl . '/public/reset_password.php?token=' . urlencode($token);
    }
    
    private function getPasswordResetHtmlTemplate($name, $resetUrl) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; background: #f0f0f0; }
                .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
                .link-box { background: #f8f9fa; padding: 15px; border-radius: 5px; word-break: break-all; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($name) . ",</h2>
                    
                    <p>We received a request to reset your password for your Cloud9 account.</p>
                    
                    <p>Click the button below to reset your password:</p>
                    
                    <p style='text-align: center;'>
                        <a href='" . htmlspecialchars($resetUrl) . "' class='button'>Reset My Password</a>
                    </p>
                    
                    <div class='warning'>
                        <strong>⚠️ Important Security Information:</strong>
                        <ul>
                            <li>This link will expire in 1 hour</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                            <li>Never share this link with anyone</li>
                        </ul>
                    </div>
                    
                    <p>If the button doesn't work, copy and paste this link into your browser:</p>
                    <div class='link-box'>" . htmlspecialchars($resetUrl) . "</div>
                    
                    <p>Best regards,<br>The Cloud9 Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; 2024 Cloud9. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getPasswordResetTextTemplate($name, $resetUrl) {
        return "Password Reset Request

Hello " . $name . ",

We received a request to reset your password for your Cloud9 account.

To reset your password, visit this link:
" . $resetUrl . "

IMPORTANT:
- This link will expire in 1 hour
- If you didn't request this reset, please ignore this email
- Never share this link with anyone

Best regards,
The Cloud9 Team

This is an automated message. Please do not reply to this email.";
    }
    
    private function getWelcomeHtmlTemplate($name) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background: white; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .button { display: inline-block; padding: 15px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; background: #f0f0f0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Welcome to Cloud9!</h1>
                </div>
                <div class='content'>
                    <h2>Hello " . htmlspecialchars($name) . ",</h2>
                    
                    <p>Welcome to Cloud9! Your account has been successfully created.</p>
                    
                    <p>You can now:</p>
                    <ul>
                        <li>Connect your cloud storage accounts (Google Drive, Dropbox, OneDrive)</li>
                        <li>Upload and manage files across multiple cloud services</li>
                        <li>Use our advanced file fragmentation system for secure storage</li>
                    </ul>
                    
                    <p style='text-align: center;'>
                        <a href='" . EmailConfig::getAppUrl() . "/public/dashboard.html' class='button'>Go to Dashboard</a>
                    </p>
                    
                    <p>If you have any questions, feel free to contact our support team.</p>
                    
                    <p>Best regards,<br>The Cloud9 Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>&copy; 2024 Cloud9. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getWelcomeTextTemplate($name) {
        return "Welcome to Cloud9!

Hello " . $name . ",

Welcome to Cloud9! Your account has been successfully created.

You can now:
- Connect your cloud storage accounts (Google Drive, Dropbox, OneDrive)
- Upload and manage files across multiple cloud services
- Use our advanced file fragmentation system for secure storage

Visit your dashboard: " . EmailConfig::getAppUrl() . "/public/dashboard.html

If you have any questions, feel free to contact our support team.

Best regards,
The Cloud9 Team

This is an automated message. Please do not reply to this email.";
    }
}
?> 