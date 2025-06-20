<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - cloud9</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body class="auth-page">
    <div class="container">
        <div class="form-card">
            <div id="loadingMessage">
                <h1>Validating Reset Link...</h1>
                <p class="subtitle">Please wait while we verify your reset token</p>
            </div>
            
            <div id="resetForm" style="display: none;">
                <h1>Set New Password</h1>
                <p class="subtitle">Enter your new password below</p>
                
                <form id="resetPasswordForm">
                    <input type="hidden" id="token" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                    
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </form>
            </div>
            
            <div id="errorMessage" style="display: none;">
                <h1>❌ Invalid Reset Link</h1>
                <p class="subtitle">This reset link is invalid or has expired</p>
                <a href="forgot_password.html" class="btn btn-primary">Request New Reset Link</a>
            </div>
            
            <div class="form-footer">
                <p>Remember your password? <a href="login.html">Sign in here</a></p>
            </div>
            
            <div id="message" class="message"></div>
        </div>
    </div>
    
    <script src="js/reset_password.js"></script>
</body>
</html> 