// Dashboard JavaScript for Cloud Storage Management

document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard loading...');
    checkUserSession();
    checkGoogleDriveStatus();
    checkDropboxStatus();
    checkOneDriveStatus();
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

// Dropbox Functions
function checkDropboxStatus() {
    console.log('Checking Dropbox status...');
    fetch('../api/oauth/dropbox/status.php')
        .then(response => {
            console.log('Dropbox status response:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Dropbox status data:', data);
            if (data.success && data.connected) {
                updateDropboxUI(true);
            } else {
                updateDropboxUI(false);
            }
        })
        .catch(error => {
            console.error('Dropbox status check error:', error);
            updateDropboxUI(false);
        });
}

function updateDropboxUI(connected) {
    const statusBadge = document.getElementById('dropboxStatus');
    const connectBtn = document.getElementById('dropboxConnectBtn');
    const disconnectBtn = document.getElementById('dropboxDisconnectBtn');
    const fileOps = document.getElementById('dropboxFileOps');
    
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

function connectDropbox() {
    console.log('Connecting to Dropbox...');
    window.location.href = '../api/oauth/dropbox/authorize.php';
}

function disconnectDropbox() {
    if (confirm('Are you sure you want to disconnect Dropbox?')) {
        fetch('../api/oauth/dropbox/disconnect.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Dropbox disconnected successfully', 'success');
                updateDropboxUI(false);
                document.getElementById('dropboxFileList').style.display = 'none';
            } else {
                showAlert('Failed to disconnect Dropbox: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Dropbox disconnect error:', error);
            showAlert('Error disconnecting Dropbox', 'error');
        });
    }
}

function uploadToDropbox() {
    const fileInput = document.getElementById('dropboxFileInput');
    const file = fileInput.files[0];
    
    if (!file) {
        showAlert('Please select a file to upload', 'error');
        return;
    }
    
    if (file.size > 50 * 1024 * 1024) { // 50MB limit for Dropbox
        showAlert('File is too large (max 50MB)', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    showAlert('Uploading file to Dropbox...', 'info');
    
    fetch('../api/dropbox/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`File "${data.file.name}" uploaded successfully to Dropbox`, 'success');
            fileInput.value = ''; // Clear the input
            // Refresh file list if it's currently displayed
            const fileList = document.getElementById('dropboxFileList');
            if (fileList.style.display !== 'none') {
                listDropboxFiles();
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

function listDropboxFiles() {
    const fileList = document.getElementById('dropboxFileList');
    fileList.innerHTML = '<div class="loading">Loading files...</div>';
    fileList.style.display = 'block';
    
    fetch('../api/dropbox/list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayDropboxFiles(data.files);
            } else {
                fileList.innerHTML = `<div class="loading">Error: ${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('List files error:', error);
            fileList.innerHTML = '<div class="loading">Error loading files</div>';
        });
}

function displayDropboxFiles(files) {
    const fileList = document.getElementById('dropboxFileList');
    
    if (!files || files.length === 0) {
        fileList.innerHTML = '<div class="loading">No files found</div>';
        return;
    }
    
    let html = '';
    files.forEach(file => {
        const size = file.size ? formatFileSize(parseInt(file.size)) : 'Unknown size';
        const modifiedDate = file.modified ? new Date(file.modified).toLocaleDateString() : 'Unknown date';
        const fileTypeIcon = file.type === 'folder' ? '📁' : '📄';
        
        html += `
            <div class="file-item">
                <div class="file-info">
                    <div class="file-name">${fileTypeIcon} ${escapeHtml(file.name)}</div>
                    <div class="file-details">
                        Type: ${file.type} | Size: ${size} | Modified: ${modifiedDate}
                        <br>Path: ${escapeHtml(file.path)}
                    </div>
                </div>
            </div>
        `;
    });
    
    fileList.innerHTML = html;
}

// OneDrive Functions
function checkOneDriveStatus() {
    console.log('Checking OneDrive status...');
    fetch('../api/oauth/onedrive/status.php')
        .then(response => {
            console.log('OneDrive status response:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('OneDrive status data:', data);
            if (data.success && data.connected) {
                updateOneDriveUI(true);
            } else {
                updateOneDriveUI(false);
            }
        })
        .catch(error => {
            console.error('OneDrive status check error:', error);
            updateOneDriveUI(false);
        });
}

function updateOneDriveUI(connected) {
    const statusBadge = document.getElementById('onedriveStatus');
    const connectBtn = document.getElementById('onedriveConnectBtn');
    const disconnectBtn = document.getElementById('onedriveDisconnectBtn');
    const fileOps = document.getElementById('onedriveFileOps');
    
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

function connectOneDrive() {
    console.log('Connecting to OneDrive...');
    window.location.href = '../api/oauth/onedrive/authorize.php';
}

function disconnectOneDrive() {
    if (confirm('Are you sure you want to disconnect OneDrive?')) {
        fetch('../api/oauth/onedrive/disconnect.php', {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('OneDrive disconnected successfully', 'success');
                updateOneDriveUI(false);
                document.getElementById('onedriveFileList').style.display = 'none';
            } else {
                showAlert('Failed to disconnect OneDrive: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('OneDrive disconnect error:', error);
            showAlert('Error disconnecting OneDrive', 'error');
        });
    }
}

function uploadToOneDrive() {
    const fileInput = document.getElementById('onedriveFileInput');
    const file = fileInput.files[0];
    
    if (!file) {
        showAlert('Please select a file to upload', 'error');
        return;
    }
    
    if (file.size > 4 * 1024 * 1024) { // 4MB limit for OneDrive simple upload
        showAlert('File is too large (max 4MB)', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    
    showAlert('Uploading file to OneDrive...', 'info');
    
    fetch('../api/onedrive/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`File "${data.file.name}" uploaded successfully to OneDrive`, 'success');
            fileInput.value = ''; // Clear the input
            // Refresh file list if it's currently displayed
            const fileList = document.getElementById('onedriveFileList');
            if (fileList.style.display !== 'none') {
                listOneDriveFiles();
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

function listOneDriveFiles() {
    const fileList = document.getElementById('onedriveFileList');
    fileList.innerHTML = '<div class="loading">Loading files...</div>';
    fileList.style.display = 'block';
    
    fetch('../api/onedrive/list.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayOneDriveFiles(data.files);
            } else {
                fileList.innerHTML = `<div class="loading">Error: ${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('List files error:', error);
            fileList.innerHTML = '<div class="loading">Error loading files</div>';
        });
}

function displayOneDriveFiles(files) {
    const fileList = document.getElementById('onedriveFileList');
    
    if (!files || files.length === 0) {
        fileList.innerHTML = '<div class="loading">No files found</div>';
        return;
    }
    
    let html = '';
    files.forEach(file => {
        const size = file.size ? formatFileSize(parseInt(file.size)) : 'Unknown size';
        const modifiedDate = file.modified ? new Date(file.modified).toLocaleDateString() : 'Unknown date';
        const fileTypeIcon = file.type === 'folder' ? '📁' : '📄';
        
        html += `
            <div class="file-item">
                <div class="file-info">
                    <div class="file-name">${fileTypeIcon} ${escapeHtml(file.name)}</div>
                    <div class="file-details">
                        Type: ${file.type} | Size: ${size} | Modified: ${modifiedDate}
                        <br>Path: ${escapeHtml(file.path)}
                        ${file.web_url ? ` | <a href="${file.web_url}" target="_blank">View in OneDrive</a>` : ''}
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
    const provider = urlParams.get('provider');
    const reason = urlParams.get('reason');
    
    console.log('OAuth callback status:', oauthStatus, 'provider:', provider, 'reason:', reason);
    
    if (oauthStatus === 'success') {
        if (provider === 'google') {
            showAlert('Google Drive connected successfully!', 'success');
            checkGoogleDriveStatus();
        } else if (provider === 'dropbox') {
            showAlert('Dropbox connected successfully!', 'success');
            checkDropboxStatus();
        } else if (provider === 'onedrive') {
            showAlert('OneDrive connected successfully!', 'success');
            checkOneDriveStatus();
        }
        // Remove the query parameter from URL
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (oauthStatus === 'error') {
        let providerName = provider === 'dropbox' ? 'Dropbox' : provider === 'onedrive' ? 'OneDrive' : 'Google Drive';
        let errorMessage = `Failed to connect to ${providerName}`;
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
                case 'server_error':
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