// FAQs Page JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Check authentication status and update navbar
    checkAuthStatus();
});

async function checkAuthStatus() {
    try {
        const response = await fetch('/TW-FII-UAIC/api/auth/session.php', {
            method: 'GET',
            credentials: 'include'
        });
        
        const data = await response.json();
        const authLink = document.getElementById('authLink');
        
        if (data.authenticated) {
            // User is logged in
            authLink.textContent = 'LOGOUT';
            authLink.href = '#';
            authLink.onclick = function(e) {
                e.preventDefault();
                logout();
            };
        } else {
            // User is not logged in
            authLink.textContent = 'LOG IN';
            authLink.href = 'login.html';
            authLink.onclick = null;
        }
    } catch (error) {
        console.error('Error checking auth status:', error);
        // Default to login state if there's an error
        const authLink = document.getElementById('authLink');
        authLink.textContent = 'LOG IN';
        authLink.href = 'login.html';
        authLink.onclick = null;
    }
}

async function logout() {
    try {
        const response = await fetch('/TW-FII-UAIC/api/auth/logout.php', {
            method: 'POST',
            credentials: 'include'
        });
        
        if (response.ok) {
            // Logout successful, redirect to login page
            window.location.href = 'login.html';
        } else {
            console.error('Logout failed');
        }
    } catch (error) {
        console.error('Error during logout:', error);
    }
} 