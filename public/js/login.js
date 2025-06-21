document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const messageDiv = document.getElementById('message');
    
    // Handle input focus effects
    const inputs = document.querySelectorAll('.login__input');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.closest('.login__field').classList.add('login__field--focused');
        });
        
        input.addEventListener('blur', function() {
            this.closest('.login__field').classList.remove('login__field--focused');
        });
    });
    
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(loginForm);
        const email = formData.get('email');
        const password = formData.get('password');
        
        if (!email || !password) {
            showMessage('Please fill in all fields', 'error');
            return;
        }
        
        if (!isValidEmail(email)) {
            showMessage('Please enter a valid email address', 'error');
            return;
        }
        
        // Show loading state
        const submitButton = loginForm.querySelector('.login__button');
        const originalText = submitButton.textContent;
        submitButton.textContent = 'LOGGING IN...';
        submitButton.disabled = true;
        
        // Clear any previous messages
        hideMessage();
        
        fetch(loginForm.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage('Login successful! Redirecting...', 'success');
                setTimeout(() => {
                    window.location.href = 'dashboard.html';
                }, 1500);
            } else {
                showMessage(data.message || 'Login failed. Please try again.', 'error');
            }
        })
        .catch(error => {
            console.error('Login error:', error);
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
        messageDiv.className = `login__message login__message--${type} login__message--show`;
    }
    
    function hideMessage() {
        messageDiv.className = 'login__message';
    }
    
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Auto-hide messages after 5 seconds
    let messageTimeout;
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList.contains('login__message--show')) {
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
    
    // Handle Google OAuth callback if present
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('oauth_success')) {
        showMessage('Login successful! Redirecting...', 'success');
        setTimeout(() => {
            window.location.href = 'dashboard.html';
        }, 1500);
    } else if (urlParams.has('oauth_error')) {
        const error = urlParams.get('oauth_error');
        showMessage(decodeURIComponent(error), 'error');
    }
}); 