document.addEventListener('DOMContentLoaded', function() {
    const loadingMessage = document.getElementById('loadingMessage');
    const resetForm = document.getElementById('resetForm');
    const errorMessage = document.getElementById('errorMessage');
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    const messageDiv = document.getElementById('message');
    
<<<<<<< Updated upstream
=======
    // Handle input focus effects
    const inputs = document.querySelectorAll('.reset__input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.closest('.reset__field').classList.add('reset__field--focused');
        });
        
        input.addEventListener('blur', function() {
            this.closest('.reset__field').classList.remove('reset__field--focused');
        });
    });
>>>>>>> Stashed changes
    
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    
    if (!token) {
        showError();
        return;
    }
    
<<<<<<< Updated upstream
   
=======
    // Validate token
>>>>>>> Stashed changes
    validateToken(token);
    
    resetPasswordForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(resetPasswordForm);
        const password = formData.get('password');
        const confirmPassword = formData.get('confirm_password');
        
        if (!password || !confirmPassword) {
            showMessage('Please fill in all fields', 'error');
            return;
        }
        
        if (password !== confirmPassword) {
            showMessage('Passwords do not match', 'error');
            return;
        }
        
        if (password.length < 6) {
            showMessage('Password must be at least 6 characters long', 'error');
            return;
        }
        
<<<<<<< Updated upstream
        
        const submitBtn = resetPasswordForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Resetting...';
=======
        // Show loading state
        const submitBtn = resetPasswordForm.querySelector('.reset__button');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'RESETTING PASSWORD...';
        
        // Clear any previous messages
        hideMessage();
>>>>>>> Stashed changes
        
        fetch('../api/auth/reset_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
<<<<<<< Updated upstream
                showMessage(data.message, 'success');
=======
                showMessage('Password reset successfully! Redirecting to login...', 'success');
>>>>>>> Stashed changes
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 3000);
            } else {
<<<<<<< Updated upstream
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
=======
                showMessage(data.message || 'Password reset failed. Please try again.', 'error');
            }
        })
        .catch(error => {
            console.error('Reset password error:', error);
>>>>>>> Stashed changes
            showMessage('An error occurred. Please try again.', 'error');
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
    
    function validateToken(token) {
        fetch(`../api/auth/validate_reset_token.php?token=${encodeURIComponent(token)}`)
        .then(response => response.json())
        .then(data => {
            if (data.valid) {
                showResetForm(data.user);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
<<<<<<< Updated upstream
            console.error('Error:', error);
=======
            console.error('Token validation error:', error);
>>>>>>> Stashed changes
            showError('Failed to validate reset token');
        });
    }
    
    function showResetForm(user) {
        loadingMessage.style.display = 'none';
        resetForm.style.display = 'block';
        
<<<<<<< Updated upstream
        // Update form subtitle with user info
        const subtitle = resetForm.querySelector('.subtitle');
        if (user && user.name) {
            subtitle.textContent = `Hello ${user.name}, enter your new password below`;
=======
        // Update form description with user info
        const description = resetForm.querySelector('.reset__description');
        if (user && user.name) {
            description.textContent = `Hello ${user.name}, enter your new password below`;
>>>>>>> Stashed changes
        }
    }
    
    function showError(message = 'This reset link is invalid or has expired') {
        loadingMessage.style.display = 'none';
        errorMessage.style.display = 'block';
        
        if (message !== 'This reset link is invalid or has expired') {
<<<<<<< Updated upstream
            errorMessage.querySelector('.subtitle').textContent = message;
        }
    }
    
    function showMessage(text, type) {
        messageDiv.textContent = text;
        messageDiv.className = 'message ' + type;
        messageDiv.style.display = 'block';
        
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 8000);
    }
=======
            const description = errorMessage.querySelector('.reset__description');
            if (description) {
                description.textContent = message;
            }
        }
    }
    
    function showMessage(message, type) {
        messageDiv.textContent = message;
        messageDiv.className = `reset__message reset__message--${type} reset__message--show`;
    }
    
    function hideMessage() {
        messageDiv.className = 'reset__message';
    }
    
    // Auto-hide messages after 8 seconds
    let messageTimeout;
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList.contains('reset__message--show')) {
                clearTimeout(messageTimeout);
                messageTimeout = setTimeout(() => {
                    hideMessage();
                }, 8000);
            }
        });
    });
    
    observer.observe(messageDiv, {
        attributes: true,
        attributeFilter: ['class']
    });
>>>>>>> Stashed changes
}); 