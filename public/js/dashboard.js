// Dashboard JavaScript for Cloud Storage Management

document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard loading...');
    checkUserSession();
    checkGoogleDriveStatus();
    handleOAuthCallback();
});

function checkUserSession() {
    console.log('Checking user session...');
    fetch('../api/auth/session.php')
        .then(response => {
            console.log('Session response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Session data:', data);
            if (data.success) {
                document.getElementById('userName').textContent = data.user.name;
                document.getElementById('userEmail').textContent = data.user.email;
            } else {
                console.log('Session invalid, redirecting to login...');
                // Add a small delay to prevent infinite redirects
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 100);
            }
        })
        .catch(error => {
            console.error('Session check error:', error);
            // Add a small delay to prevent infinite redirects
            setTimeout(() => {
                window.location.href = 'login.html';
            }, 100);
        });
}

function logout() {
    fetch('../api/auth/logout.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'login.html';
        }
    })
    .catch(error => {
        console.error('Logout error:', error);
        window.location.href = 'login.html';
    });
}

// Google Drive Functions
function checkGoogleDriveStatus() {
    console.log('Checking Google Drive status...');
    fetch('../api/oauth/google/status.php')
        .then(response => {
            console.log('Google Drive status response:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Google Drive status data:', data);
            if (data.success && data.connected) {
                updateGoogleUI(true);
            } else {
                updateGoogleUI(false);
            }
        })
        .catch(error => {
            console.error('Google Drive status check error:', error);
            updateGoogleUI(false);
        });
}

function updateGoogleUI(connected) {
    const statusBadge = document.getElementById('googleStatus');
    const connectBtn = document.getElementById('googleConnectBtn');
    const disconnectBtn = document.getElementById('googleDisconnectBtn');
    const fileOps = document.getElementById('googleFileOps');
    
    if (connected) {
        statusBadge.textContent = 'Connected';
        statusBadge.className = 'status-badge status-connected';
        connectBtn.style.display = 'none';
        disconnectBtn.style.display = 'inline-block';
        fileOps.style.display = 'block';
    } else {
        statusBadge.textContent = 'Disconnected';
        statusBadge.className = 'status-badge status-disconnected';
        connectBtn.style.display = 'inline-block';
        disconnectBtn.style.display = 'none';
        fileOps.style.display = 'none';
    }
}

function connectGoogle() {
    console.log('Connecting to Google Drive...');
    window.location.href = '../api/oauth/google/authorize.php';
}

function disconnectGoogle() {
    if (confirm('Are you sure you want to disconnect Google Drive?')) {
        fetch('../api/oauth/google/disconnect.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Google Drive disconnected successfully', 'success');
                updateGoogleUI(false);
                document.getElementById('googleFileList').style.display = 'none';
            } else {
                showAlert('Failed to disconnect Google Drive: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Google Drive disconnect error:', error);
            showAlert('Error disconnecting Google Drive', 'error');
        });
    }
}

function uploadToGoogle() {
    const fileInput = document.getElementById('googleFileInput');
    const file = fileInput.files[0];
    
    if (!file) {
        showAlert('Please select a file to upload', 'error');
        return;
    }
    
    if (file.size > 100 * 1024 * 1024) { // 100MB limit
        showAlert('File is too large (max 100MB)', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    showAlert('Uploading file to Google Drive...', 'info');
    
    fetch('../api/drive/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`File "${data.name}" uploaded successfully to Google Drive`, 'success');
            fileInput.value = ''; // Clear the input
            // Refresh file list if it's currently displayed
            const fileList = document.getElementById('googleFileList');
            if (fileList.style.display !== 'none') {
                listGoogleFiles();
            }
        } else {
            showAlert('Upload failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showAlert('Upload failed: Network error', 'error');
    });
}

function listGoogleFiles() {
    const fileList = document.getElementById('googleFileList');
    fileList.innerHTML = '<div class="loading">Loading files...</div>';
    fileList.style.display = 'block';
    
    fetch('../api/drive/list.php?pageSize=20')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayGoogleFiles(data.files);
            } else {
                fileList.innerHTML = `<div class="loading">Error: ${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('List files error:', error);
            fileList.innerHTML = '<div class="loading">Error loading files</div>';
        });
}

function displayGoogleFiles(files) {
    const fileList = document.getElementById('googleFileList');
    
    if (!files || files.length === 0) {
        fileList.innerHTML = '<div class="loading">No files found</div>';
        return;
    }
    
    let html = '';
    files.forEach(file => {
        const size = file.size ? formatFileSize(parseInt(file.size)) : 'Unknown size';
        const modifiedDate = file.modifiedTime ? new Date(file.modifiedTime).toLocaleDateString() : 'Unknown date';
        
        html += `
            <div class="file-item">
                <div class="file-info">
                    <div class="file-name">${escapeHtml(file.name)}</div>
                    <div class="file-details">
                        Size: ${size} | Modified: ${modifiedDate}
                        ${file.webViewLink ? ` | <a href="${file.webViewLink}" target="_blank">View in Drive</a>` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    fileList.innerHTML = html;
}

function handleOAuthCallback() {
    const urlParams = new URLSearchParams(window.location.search);
    const oauthStatus = urlParams.get('oauth');
    const reason = urlParams.get('reason');
    
    console.log('OAuth callback status:', oauthStatus, 'reason:', reason);
    
    if (oauthStatus === 'success') {
        showAlert('Google Drive connected successfully!', 'success');
        checkGoogleDriveStatus();
        // Remove the query parameter from URL
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (oauthStatus === 'error') {
        let errorMessage = 'Failed to connect to Google Drive';
        if (reason) {
            switch(reason) {
                case 'invalid_state':
                    errorMessage += ': Invalid security token';
                    break;
                case 'no_code':
                    errorMessage += ': Authorization was denied';
                    break;
                case 'token_exchange':
                    errorMessage += ': Failed to get access token';
                    break;
                case 'token_save':
                    errorMessage += ': Failed to save token';
                    break;
                case 'exception':
                    errorMessage += ': Internal error occurred';
                    break;
                default:
                    errorMessage += ': ' + reason;
            }
        }
        showAlert(errorMessage, 'error');
        // Remove the query parameter from URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}

// Utility Functions
function showAlert(message, type) {
    const alertsContainer = document.getElementById('alerts');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    
    alertsContainer.appendChild(alert);
    
    // Auto-remove alert after 5 seconds
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 5000);
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}