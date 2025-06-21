document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const messageDiv = document.getElementById('message');
    
    // Handle input focus effects
    const inputs = document.querySelectorAll('.register__input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.closest('.register__field').classList.add('register__field--focused');
        });
        
        input.addEventListener('blur', function() {
            this.closest('.register__field').classList.remove('register__field--focused');
        });
    });
    
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(registerForm);
        const name = formData.get('name');
        const email = formData.get('email');
        const password = formData.get('password');
        const confirmPassword = formData.get('confirmPassword');
        
        if (!name || !email || !password || !confirmPassword) {
            showMessage('Please fill in all fields', 'error');
            return;
        }
        
        if (!isValidEmail(email)) {
            showMessage('Please enter a valid email address', 'error');
            return;
        }
        
        if (password.length < 6) {
            showMessage('Password must be at least 6 characters long', 'error');
            return;
        }
        
        if (password !== confirmPassword) {
            showMessage('Passwords do not match', 'error');
            return;
        }
        
        // Show loading state
        const submitButton = registerForm.querySelector('.register__button');
        const originalText = submitButton.textContent;
        submitButton.textContent = 'CREATING ACCOUNT...';
        submitButton.disabled = true;
        
        // Clear any previous messages
        hideMessage();
        
        fetch(registerForm.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('Account created successfully! Redirecting to login...', 'success');
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 2000);
            } else {
                showMessage(data.message || 'Registration failed. Please try again.', 'error');
            }
        })
        .catch(error => {
            console.error('Registration error:', error);
            showMessage('An error occurred. Please try again.', 'error');
        })
        .finally(() => {
            // Reset button state
            submitButton.textContent = originalText;
            submitButton.disabled = false;
        });
    });
    
    function showMessage(message, type) {
        messageDiv.textContent = message;
        messageDiv.className = `register__message register__message--${type} register__message--show`;
    }
    
    function hideMessage() {
        messageDiv.className = 'register__message';
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Auto-hide messages after 5 seconds
    let messageTimeout;
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList.contains('register__message--show')) {
                clearTimeout(messageTimeout);
                messageTimeout = setTimeout(() => {
                    hideMessage();
                }, 5000);
            }
        });
    });
    
    observer.observe(messageDiv, {
        attributes: true,
        attributeFilter: ['class']
    });
}); 