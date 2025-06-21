<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud9 - Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/reset_password.css">
</head>
<body class="reset-page">
    <div class="reset">
        <!-- Left Side - Reset Password Form -->
        <div class="reset__form-section">
            <div class="reset__form-container">
                <!-- Logo with Image and Text -->
                <div class="reset__brand">
                    <div class="reset__logo">
                        <img src="../assets/Logo.png" alt="Cloud9 Logo" class="reset__logo-image">
                    </div>
                    <p class="reset__subtitle">Put multiple clouds into one</p>
                </div>
                
                <!-- Loading Message -->
                <div id="loadingMessage" class="reset__loading">
                    <h2 class="reset__title">Validating Reset Link...</h2>
                    <p class="reset__description">Please wait while we verify your reset token</p>
                </div>
                
                <!-- Reset Password Form -->
                <div id="resetForm" class="reset__form-wrapper" style="display: none;">
                    <h2 class="reset__title">Set New Password</h2>
                    <p class="reset__description">Enter your new password below</p>
                    
                    <form id="resetPasswordForm" class="reset__form">
                        <input type="hidden" id="token" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                        
                        <div class="reset__field">
                            <div class="reset__input-wrapper">
                                <svg class="reset__input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <circle cx="12" cy="16" r="1"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    class="reset__input" 
                                    placeholder="New Password"
                                    required
                                    minlength="6"
                                >
                            </div>
                        </div>
                        
                        <div class="reset__field">
                            <div class="reset__input-wrapper">
                                <svg class="reset__input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <circle cx="12" cy="16" r="1"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    class="reset__input" 
                                    placeholder="Confirm New Password"
                                    required
                                    minlength="6"
                                >
                            </div>
                        </div>
                        
                        <button type="submit" class="reset__button">RESET PASSWORD</button>
                    </form>
                </div>
                
                <!-- Error Message -->
                <div id="errorMessage" class="reset__error" style="display: none;">
                    <h2 class="reset__title reset__title--error">❌ Invalid Reset Link</h2>
                    <p class="reset__description">This reset link is invalid or has expired</p>
                    <a href="forgot_password.html" class="reset__button">REQUEST NEW RESET LINK</a>
                </div>
                
                <div class="reset__links">
                    <div class="reset__link-item">
                        <span class="reset__link-text">Remember your password? </span>
                        <a href="login.html" class="reset__link">Sign in</a>
                    </div>
                </div>
                
                <div id="message" class="reset__message"></div>
            </div>
        </div>
        
        <!-- Right Side - Background with Clouds -->
        <div class="reset__background-section">
            <div class="reset__background">
                <div class="reset__clouds">
                    <!-- Cloud decorations will be added via CSS -->
                </div>
            </div>
        </div>
    </div>
    
    <script src="js/reset_password.js"></script>
</body>
</html> 