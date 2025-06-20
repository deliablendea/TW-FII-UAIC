document.addEventListener('DOMContentLoaded', function() {
    const loadingMessage = document.getElementById('loadingMessage');
    const resetForm = document.getElementById('resetForm');
    const errorMessage = document.getElementById('errorMessage');
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    const messageDiv = document.getElementById('message');
    
    
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    
    if (!token) {
        showError();
        return;
    }
    
   
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
        
        
        const submitBtn = resetPasswordForm.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Resetting...';
        
        fetch('../api/auth/reset_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(data.message, 'success');
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 3000);
            } else {
                showMessage(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
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
            console.error('Error:', error);
            showError('Failed to validate reset token');
        });
    }
    
    function showResetForm(user) {
        loadingMessage.style.display = 'none';
        resetForm.style.display = 'block';
        
        // Update form subtitle with user info
        const subtitle = resetForm.querySelector('.subtitle');
        if (user && user.name) {
            subtitle.textContent = `Hello ${user.name}, enter your new password below`;
        }
    }
    
    function showError(message = 'This reset link is invalid or has expired') {
        loadingMessage.style.display = 'none';
        errorMessage.style.display = 'block';
        
        if (message !== 'This reset link is invalid or has expired') {
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
}); 