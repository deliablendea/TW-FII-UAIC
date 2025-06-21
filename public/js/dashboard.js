let fragmentationAuthStatus = null;
let selectedFragmentationFile = null;

// Navigation tracking variables
let currentGooglePath = 'root';
let currentDropboxPath = '';
let currentOneDrivePath = '';
let onedriveNavigationStack = []; // Stack to track OneDrive folder navigation

document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard loading...');
    checkUserSession();
    checkGoogleDriveStatus();
    checkDropboxStatus();
    checkOneDriveStatus();
    handleOAuthCallback();
    
  
    setTimeout(() => {
        checkFragmentationAuthStatus();
        setupFragmentationFileUpload();
        setupFragmentationStatusRefresh();
    }, 1000);
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

function listGoogleFiles(folderId = null) {
    const fileList = document.getElementById('googleFileList');
    fileList.innerHTML = '<div class="loading">Loading files...</div>';
    fileList.style.display = 'block';
    
    // Update current path
    if (folderId) {
        currentGooglePath = folderId;
    } else {
        currentGooglePath = 'root';
    }
    
    // Build URL with folder parameter
    let url = '../api/drive/list.php?pageSize=20';
    if (folderId && folderId !== 'root') {
        url += `&folderId=${encodeURIComponent(folderId)}`;
    }
    
    fetch(url)
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
        fileList.innerHTML = getNavigationBar('google') + '<div class="loading">No files found</div>';
        return;
    }
    
    let html = getNavigationBar('google');
    files.forEach(file => {
        const size = file.size ? formatFileSize(parseInt(file.size)) : 'Unknown size';
        const modifiedDate = file.modifiedTime ? new Date(file.modifiedTime).toLocaleDateString() : 'Unknown date';
        const isFolder = file.mimeType === 'application/vnd.google-apps.folder';
        const fileIcon = isFolder ? '📁' : '📄';
        
        html += `
            <div class="file-item">
                <div class="file-info">
                    <div class="file-name-container">
                        <span class="file-name ${isFolder ? 'folder-name' : ''}" 
                              id="google-name-${file.id}" 
                              ${isFolder ? `onclick="navigateToGoogleFolder('${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')"` : `onclick="startRename('google', '${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}', this)"`}>
                            ${fileIcon} ${escapeHtml(file.name)}
                        </span>
                        ${!isFolder ? `<input type="text" class="file-name-input" id="google-input-${file.id}" value="${escapeHtml(file.name)}" style="display: none;" onblur="cancelRename('google', '${file.id}')" onkeydown="handleRenameKeydown(event, 'google', '${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')">` : ''}
                    </div>
                    <div class="file-details">
                        Type: ${isFolder ? 'Folder' : 'File'} | Size: ${size} | Modified: ${modifiedDate}
                        ${file.webViewLink ? ` | <a href="${file.webViewLink}" target="_blank">View in Drive</a>` : ''}
                        ${!isFolder ? ` | <button class="rename-btn" onclick="startRename('google', '${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')">✏️ Rename</button>` : ''}
                        ${!isFolder ? ` | <button class="delete-btn" onclick="deleteGoogleFile('${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')">🗑️ Delete</button>` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    fileList.innerHTML = html;
}

// Google Drive rename function
function renameGoogleFile(fileId, newName, originalName) {
    if (!newName || newName.trim() === '') {
        showAlert('File name cannot be empty', 'error');
        return;
    }
    
    newName = newName.trim();
    if (newName === originalName) {
        return; // No change needed
    }
    
    fetch('../api/drive/rename.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ fileId: fileId, newName: newName })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`File renamed successfully to "${data.name}"`, 'success');
            // Refresh file list
            listGoogleFiles();
        } else {
            showAlert('Rename failed: ' + data.message, 'error');
            // Reset the input back to original name
            const nameSpan = document.getElementById(`google-name-${fileId}`);
            const nameInput = document.getElementById(`google-input-${fileId}`);
            if (nameSpan && nameInput) {
                nameSpan.textContent = originalName;
                nameInput.value = originalName;
            }
        }
    })
    .catch(error => {
        console.error('Rename error:', error);
        showAlert('Rename failed: Network error', 'error');
        // Reset the input back to original name
        const nameSpan = document.getElementById(`google-name-${fileId}`);
        const nameInput = document.getElementById(`google-input-${fileId}`);
        if (nameSpan && nameInput) {
            nameSpan.textContent = originalName;
            nameInput.value = originalName;
        }
    });
}

// Delete Google Drive file function
function deleteGoogleFile(fileId, fileName) {
    if (!confirm(`Are you sure you want to delete "${fileName}" from Google Drive? This action cannot be undone.`)) {
        return;
    }
    
    fetch('../api/drive/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ fileId: fileId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`File "${fileName}" deleted successfully from Google Drive`, 'success');
            // Refresh file list
            listGoogleFiles();
        } else {
            showAlert('Delete failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showAlert('Delete failed: Network error', 'error');
    });
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

function listDropboxFiles(path = '') {
    const fileList = document.getElementById('dropboxFileList');
    fileList.innerHTML = '<div class="loading">Loading files...</div>';
    fileList.style.display = 'block';
    
    // Update current path
    currentDropboxPath = path;
    
    // Build URL with path parameter
    let url = '../api/dropbox/list.php';
    if (path) {
        url += `?path=${encodeURIComponent(path)}`;
    }
    
    fetch(url)
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
        fileList.innerHTML = getNavigationBar('dropbox') + '<div class="loading">No files found</div>';
        return;
    }
    
    let html = getNavigationBar('dropbox');
    files.forEach((file, index) => {
        const size = file.size ? formatFileSize(parseInt(file.size)) : 'Unknown size';
        const modifiedDate = file.modified ? new Date(file.modified).toLocaleDateString() : 'Unknown date';
        const isFolder = file.type === 'folder';
        const fileTypeIcon = isFolder ? '📁' : '📄';
        const safeId = `dropbox-${index}-${Date.now()}`;
        
        html += `
            <div class="file-item">
                <div class="file-info">
                    <div class="file-name-container">
                        <span class="file-name ${isFolder ? 'folder-name' : ''}" 
                              id="dropbox-name-${safeId}" 
                              ${isFolder ? `onclick="navigateToDropboxFolder('${escapeHtml(file.path).replace(/'/g, "\\'")}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')"` : `onclick="startRename('dropbox', '${safeId}', '${escapeHtml(file.name).replace(/'/g, "\\'")}', this)"`}
                              ${!isFolder ? `data-path="${escapeHtml(file.path)}"` : ''}>
                            ${fileTypeIcon} ${escapeHtml(file.name)}
                        </span>
                        ${!isFolder ? `<input type="text" class="file-name-input" id="dropbox-input-${safeId}" value="${escapeHtml(file.name)}" style="display: none;" onblur="cancelRename('dropbox', '${safeId}')" onkeydown="handleRenameKeydown(event, 'dropbox', '${safeId}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')">` : ''}
                    </div>
                    <div class="file-details">
                        Type: ${file.type} | Size: ${size} | Modified: ${modifiedDate}
                        <br>Path: ${escapeHtml(file.path)}
                        ${!isFolder ? ` | <a href="https://www.dropbox.com/home${file.path}" target="_blank">View in Dropbox</a>` : ''}
                        ${!isFolder ? ` | <button class="rename-btn" onclick="startRename('dropbox', '${safeId}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')">✏️ Rename</button>` : ''}
                        ${!isFolder ? ` | <button class="delete-btn" onclick="deleteDropboxFile('${escapeHtml(file.path).replace(/'/g, "\\'")}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')">🗑️ Delete</button>` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    fileList.innerHTML = html;
}

// Dropbox rename function
function renameDropboxFile(filePath, newName, originalName) {
    if (!newName || newName.trim() === '') {
        showAlert('File name cannot be empty', 'error');
        return;
    }
    
    newName = newName.trim();
    if (newName === originalName) {
        return; // No change needed
    }
    
    fetch('../api/dropbox/rename.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ path: filePath, newName: newName })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`File renamed successfully to "${data.name}"`, 'success');
            // Refresh file list
            listDropboxFiles();
        } else {
            showAlert('Rename failed: ' + data.message, 'error');
            // Refresh file list to restore original state
            listDropboxFiles();
        }
    })
    .catch(error => {
        console.error('Rename error:', error);
        showAlert('Rename failed: Network error', 'error');
        // Refresh file list to restore original state
        listDropboxFiles();
    });
}

// Delete Dropbox file function
function deleteDropboxFile(filePath, fileName) {
    if (!confirm(`Are you sure you want to delete "${fileName}" from Dropbox? This action cannot be undone.`)) {
        return;
    }
    
    fetch('../api/dropbox/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ path: filePath })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`File "${fileName}" deleted successfully from Dropbox`, 'success');
            // Refresh file list
            listDropboxFiles();
        } else {
            showAlert('Delete failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showAlert('Delete failed: Network error', 'error');
    });
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

function listOneDriveFiles(pathOrFolderId = '') {
    const fileList = document.getElementById('onedriveFileList');
    fileList.innerHTML = '<div class="loading">Loading files...</div>';
    fileList.style.display = 'block';
    
    // Update current path
    currentOneDrivePath = pathOrFolderId;
    
    // Build URL with path or folderId parameter
    let url = '../api/onedrive/list.php';
    if (pathOrFolderId) {
        // Check if it's a folder ID (contains letters/numbers) or a path (contains slashes)
        if (pathOrFolderId.includes('/') || pathOrFolderId === '') {
            url += `?path=${encodeURIComponent(pathOrFolderId)}`;
        } else {
            // It's a folder ID
            url += `?folderId=${encodeURIComponent(pathOrFolderId)}`;
        }
    }
    
    fetch(url)
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
        fileList.innerHTML = getNavigationBar('onedrive') + '<div class="loading">No files found</div>';
        return;
    }
    
    let html = getNavigationBar('onedrive');
    files.forEach(file => {
        const size = file.size ? formatFileSize(parseInt(file.size)) : 'Unknown size';
        const modifiedDate = file.modified ? new Date(file.modified).toLocaleDateString() : 'Unknown date';
        const isFolder = file.type === 'folder';
        const fileTypeIcon = isFolder ? '📁' : '📄';
        
        html += `
            <div class="file-item">
                <div class="file-info">
                    <div class="file-name-container">
                        <span class="file-name ${isFolder ? 'folder-name' : ''}" 
                              id="onedrive-name-${file.id}" 
                              ${isFolder ? `onclick="navigateToOneDriveFolder('${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')" data-folder-id="${file.id}"` : `onclick="startRename('onedrive', '${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}', this)"`}>
                            ${fileTypeIcon} ${escapeHtml(file.name)}
                        </span>
                        ${!isFolder ? `<input type="text" class="file-name-input" id="onedrive-input-${file.id}" value="${escapeHtml(file.name)}" style="display: none;" onblur="cancelRename('onedrive', '${file.id}')" onkeydown="handleRenameKeydown(event, 'onedrive', '${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')">` : ''}
                    </div>
                    <div class="file-details">
                        Type: ${file.type} | Size: ${size} | Modified: ${modifiedDate}
                        <br>Path: ${escapeHtml(file.path)}
                        ${file.web_url ? ` | <a href="${file.web_url}" target="_blank">View in OneDrive</a>` : ''}
                        ${!isFolder ? ` | <button class="rename-btn" onclick="startRename('onedrive', '${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')">✏️ Rename</button>` : ''}
                        ${!isFolder ? ` | <button class="delete-btn" onclick="deleteOneDriveFile('${file.id}', '${escapeHtml(file.name).replace(/'/g, "\\'")}')">🗑️ Delete</button>` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    fileList.innerHTML = html;
}

// OneDrive rename function
function renameOneDriveFile(fileId, newName, originalName) {
    if (!newName || newName.trim() === '') {
        showAlert('File name cannot be empty', 'error');
        return;
    }
    
    newName = newName.trim();
    if (newName === originalName) {
        return; // No change needed
    }
    
    fetch('../api/onedrive/rename.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ fileId: fileId, newName: newName })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`File renamed successfully to "${data.name}"`, 'success');
            // Refresh file list
            listOneDriveFiles();
        } else {
            showAlert('Rename failed: ' + data.message, 'error');
            // Reset the input back to original name
            const nameSpan = document.getElementById(`onedrive-name-${fileId}`);
            const nameInput = document.getElementById(`onedrive-input-${fileId}`);
            if (nameSpan && nameInput) {
                nameSpan.innerHTML = `📄 ${originalName}`;
                nameInput.value = originalName;
            }
        }
    })
    .catch(error => {
        console.error('Rename error:', error);
        showAlert('Rename failed: Network error', 'error');
        // Reset the input back to original name
        const nameSpan = document.getElementById(`onedrive-name-${fileId}`);
        const nameInput = document.getElementById(`onedrive-input-${fileId}`);
        if (nameSpan && nameInput) {
            nameSpan.innerHTML = `📄 ${originalName}`;
            nameInput.value = originalName;
        }
    });
}

// Delete OneDrive file function
function deleteOneDriveFile(fileId, fileName) {
    if (!confirm(`Are you sure you want to delete "${fileName}" from OneDrive? This action cannot be undone.`)) {
        return;
    }
    
    fetch('../api/onedrive/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ fileId: fileId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(`File "${fileName}" deleted successfully from OneDrive`, 'success');
            // Refresh file list
            listOneDriveFiles();
        } else {
            showAlert('Delete failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showAlert('Delete failed: Network error', 'error');
    });
}

// Fragmentation System Functions
function checkFragmentationAuthStatus() {
    fetch('../api/fragmentation.php?action=status')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('🔍 Fragmentation status response:', data);
            console.log('🔍 authenticated_providers type:', typeof data.authenticated_providers);
            console.log('🔍 authenticated_providers value:', data.authenticated_providers);
            console.log('🔍 is array?', Array.isArray(data.authenticated_providers));
            
            if (data.success === false && data.message === 'Not authenticated') {
                // User is not logged in, show appropriate message
                document.getElementById('fragmentationStatusCard').innerHTML = `
                    <div class="error">
                        <h4>❌ Authentication Required</h4>
                        <p>You need to be logged in to use the fragmentation system.</p>
                        <p><a href="login.html">Please log in here</a></p>
                    </div>
                `;
                document.getElementById('fragmentationUploadSection').style.display = 'none';
                return;
            }
            
            fragmentationAuthStatus = data;
            updateFragmentationStatusCard();
            if (data.fragmentation_available) {
                loadFragmentedFiles();
            }
        })
        .catch(error => {
            console.error('Error checking fragmentation auth status:', error);
            document.getElementById('fragmentationStatusCard').innerHTML = 
                `<div class="error">Error checking authentication status: ${error.message}</div>`;
        });
}

function updateFragmentationStatusCard() {
    const statusCard = document.getElementById('fragmentationStatusCard');
    const uploadSection = document.getElementById('fragmentationUploadSection');
    
    if (fragmentationAuthStatus && fragmentationAuthStatus.fragmentation_available) {
        statusCard.className = 'status-card status-ok';
        statusCard.innerHTML = `
            <h4>✅ Fragmentation Available</h4>
            <p>All required cloud services are connected.</p>
            <div class="provider-status">
                ${fragmentationAuthStatus.authenticated_providers && Array.isArray(fragmentationAuthStatus.authenticated_providers) ? fragmentationAuthStatus.authenticated_providers.map(provider => 
                    `<span class="provider connected">${provider.toUpperCase()}</span>`
                ).join('') : ''}
            </div>
        `;
        uploadSection.style.display = 'block';
    } else if (fragmentationAuthStatus) {
        statusCard.className = 'status-card status-warning';
        statusCard.innerHTML = `
            <h4>⚠️ Authentication Required</h4>
            <p>Fragmentation requires authentication with all cloud services.</p>
            <div class="provider-status">
                ${fragmentationAuthStatus.authenticated_providers && Array.isArray(fragmentationAuthStatus.authenticated_providers) ? fragmentationAuthStatus.authenticated_providers.map(provider => 
                    `<span class="provider connected">${provider.toUpperCase()}</span>`
                ).join('') : ''}
                ${fragmentationAuthStatus.missing_providers && Array.isArray(fragmentationAuthStatus.missing_providers) ? fragmentationAuthStatus.missing_providers.map(provider => 
                    `<span class="provider disconnected">${provider.toUpperCase()}</span>`
                ).join('') : ''}
            </div>
            <p style="margin-top: 15px;">
                Please authenticate with: <strong>${fragmentationAuthStatus.missing_providers && Array.isArray(fragmentationAuthStatus.missing_providers) ? fragmentationAuthStatus.missing_providers.join(', ') : 'all providers'}</strong>
            </p>
        `;
        uploadSection.style.display = 'none';
    } else {
        statusCard.className = 'status-card status-error';
        statusCard.innerHTML = `
            <h4>❌ Service Unavailable</h4>
            <p>Unable to check fragmentation service status. Please ensure all services are running.</p>
        `;
        uploadSection.style.display = 'none';
    }
}

function setupFragmentationFileUpload() {
    const fileInput = document.getElementById('fragmentationFileInput');
    const uploadSection = document.getElementById('fragmentationUploadSection');
    const uploadBtn = document.getElementById('fragmentationUploadBtn');
    
    if (!fileInput || !uploadSection || !uploadBtn) {
        console.error('Fragmentation UI elements not found');
        return;
    }
    
    fileInput.addEventListener('change', function() {
        selectedFragmentationFile = this.files[0];
        if (selectedFragmentationFile) {
            uploadSection.querySelector('p').textContent = `Selected: ${selectedFragmentationFile.name} (${formatFileSize(selectedFragmentationFile.size)})`;
            uploadBtn.disabled = false;
        }
    });
    
    // Drag and drop functionality
    uploadSection.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
    });
    
    uploadSection.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
    });
    
    uploadSection.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            selectedFragmentationFile = files[0];
            fileInput.files = files;
            this.querySelector('p').textContent = `Selected: ${selectedFragmentationFile.name} (${formatFileSize(selectedFragmentationFile.size)})`;
            uploadBtn.disabled = false;
        }
    });
}

function uploadFragmentedFile() {
    if (!selectedFragmentationFile || !fragmentationAuthStatus || !fragmentationAuthStatus.fragmentation_available) {
        showFragmentationMessage('Please select a file and ensure all cloud services are authenticated', 'error');
        return;
    }
    
    const uploadBtn = document.getElementById('fragmentationUploadBtn');
    const formData = new FormData();
    
    formData.append('file', selectedFragmentationFile);
    formData.append('chunk_size', document.getElementById('chunkSize').value);
    formData.append('redundancy_level', document.getElementById('redundancyLevel').value);
    
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';
    
    fetch('../api/fragmentation.php?action=upload', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showFragmentationMessage('File uploaded and fragmented successfully!', 'success');
            selectedFragmentationFile = null;
            document.getElementById('fragmentationFileInput').value = '';
            document.getElementById('fragmentationUploadSection').querySelector('p').textContent = 'Drop a file here or click to select';
            loadFragmentedFiles();
        } else {
            showFragmentationMessage('Upload failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        showFragmentationMessage('Upload failed: ' + error.message, 'error');
    })
    .finally(() => {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload & Fragment';
    });
}

function loadFragmentedFiles() {
    fetch('../api/fragmentation.php?action=list')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                displayFragmentedFiles(data.files);
            } else {
                document.getElementById('fragmentationFilesList').innerHTML = 
                    '<div class="error">Failed to load files: ' + data.message + '</div>';
            }
        })
        .catch(error => {
            console.error('Error loading files:', error);
            document.getElementById('fragmentationFilesList').innerHTML = 
                '<div class="error">Error loading files: ' + error.message + '</div>';
        });
}

function displayFragmentedFiles(files) {
    const filesList = document.getElementById('fragmentationFilesList');
    
    if (!files || files.length === 0) {
        filesList.innerHTML = '<p style="text-align: center; color: rgba(255, 255, 255, 0.8);">No fragmented files found.</p>';
        return;
    }
    
    filesList.innerHTML = files.map(file => `
        <div class="file-item">
            <div class="file-info">
                <h4>${escapeHtml(file.original_filename)}</h4>
                <div class="file-meta">
                    Size: ${formatFileSize(file.original_size)} | 
                    Status: ${escapeHtml(file.status)} | 
                    Chunks: ${file.uploaded_chunks}/${file.total_chunks} |
                    Created: ${new Date(file.created_at).toLocaleDateString()}
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: ${(file.uploaded_chunks / file.total_chunks) * 100}%"></div>
                </div>
            </div>
            <div class="file-actions">
                <button class="btn btn-success" onclick="downloadFragmentedFile(${file.id})" 
                        ${file.status !== 'complete' ? 'disabled' : ''}>
                    Download
                </button>
                <button class="btn" onclick="viewFragmentedFileInfo(${file.id})">Info</button>
                <button class="btn btn-danger" onclick="deleteFragmentedFile(${file.id})">Delete</button>
            </div>
        </div>
    `).join('');
}

function downloadFragmentedFile(fileId) {
    const link = document.createElement('a');
    link.href = `../api/fragmentation.php?action=download&id=${fileId}`;
    link.download = ''; // This will use the filename from the server
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function viewFragmentedFileInfo(fileId) {
    fetch(`../api/fragmentation.php?action=info&id=${fileId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const info = data.file_info;
                const stats = data.storage_statistics;
                const integrity = data.integrity;
                
                alert(`File Information:
Name: ${info.original_filename}
Size: ${formatFileSize(info.original_size)}
MIME Type: ${info.mime_type}
Chunks: ${info.total_chunks}
Chunk Size: ${formatFileSize(info.chunk_size)}
Redundancy Level: ${info.redundancy_level}
Status: ${info.status}

Storage Distribution:
Dropbox: ${stats.dropbox} chunks
Google Drive: ${stats.google} chunks
OneDrive: ${stats.onedrive} chunks

Integrity Check:
Complete: ${integrity.is_complete ? 'Yes' : 'No'}
Progress: ${integrity.completion_percentage}%`);
            } else {
                showFragmentationMessage('Failed to get file info: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error getting file info:', error);
            showFragmentationMessage('Error getting file info: ' + error.message, 'error');
        });
}

function deleteFragmentedFile(fileId) {
    if (!confirm('Are you sure you want to delete this fragmented file? This action cannot be undone.')) {
        return;
    }
    
    fetch(`../api/fragmentation.php?action=delete&id=${fileId}`, {
        method: 'DELETE'
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showFragmentationMessage('File deleted successfully!', 'success');
            loadFragmentedFiles();
        } else {
            showFragmentationMessage('Delete failed: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showFragmentationMessage('Delete failed: ' + error.message, 'error');
    });
}

function showFragmentationMessage(message, type) {
    const messageDiv = document.createElement('div');
    messageDiv.className = type;
    messageDiv.textContent = message;
    
    const fragmentationSection = document.querySelector('.fragmentation-section');
    if (fragmentationSection) {
        fragmentationSection.insertBefore(messageDiv, fragmentationSection.firstChild);
        
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    } else {
        // Fallback to regular alert system
        if (typeof showAlert === 'function') {
            showAlert(message, type);
        } else {
            alert(message);
        }
    }
}

// Function to refresh fragmentation status when cloud services connect/disconnect
function refreshFragmentationStatus() {
    if (fragmentationAuthStatus !== null) {
        console.log('Refreshing fragmentation status due to cloud service change...');
        checkFragmentationAuthStatus();
    }
}

// Override the existing cloud service UI update functions to refresh fragmentation status
function setupFragmentationStatusRefresh() {
    // Override Google Drive UI update function
    if (typeof window.updateGoogleUI === 'function') {
        const originalUpdateGoogleUI = window.updateGoogleUI;
        window.updateGoogleUI = function(connected) {
            originalUpdateGoogleUI(connected);
            console.log('Google Drive status changed to:', connected);
            setTimeout(refreshFragmentationStatus, 500); // Small delay to ensure backend is updated
        };
    }
    
    // Override Dropbox UI update function
    if (typeof window.updateDropboxUI === 'function') {
        const originalUpdateDropboxUI = window.updateDropboxUI;
        window.updateDropboxUI = function(connected) {
            originalUpdateDropboxUI(connected);
            console.log('Dropbox status changed to:', connected);
            setTimeout(refreshFragmentationStatus, 500); // Small delay to ensure backend is updated
        };
    }
    
    // Override OneDrive UI update function
    if (typeof window.updateOneDriveUI === 'function') {
        const originalUpdateOneDriveUI = window.updateOneDriveUI;
        window.updateOneDriveUI = function(connected) {
            originalUpdateOneDriveUI(connected);
            console.log('OneDrive status changed to:', connected);
            setTimeout(refreshFragmentationStatus, 500); // Small delay to ensure backend is updated
        };
    }
    
    // Add refresh button for manual refresh
    setTimeout(addFragmentationRefreshButton, 2000);
    
    // Also listen for OAuth callback completions by monitoring URL changes
    let lastUrl = location.href;
    new MutationObserver(() => {
        const currentUrl = location.href;
        if (currentUrl !== lastUrl) {
            lastUrl = currentUrl;
            // Check if we're returning from OAuth
            if (currentUrl.includes('oauth') || currentUrl.includes('code=')) {
                console.log('OAuth callback detected, refreshing fragmentation status');
                setTimeout(refreshFragmentationStatus, 1000);
            }
        }
    }).observe(document, { subtree: true, childList: true });
    
    // Handle page visibility changes (when user comes back from OAuth)
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            console.log('Page became visible, checking for OAuth completion');
            setTimeout(refreshFragmentationStatus, 500);
        }
    });
    
    console.log('Fragmentation status refresh hooks installed');
}

// Add refresh button for manual refresh
function addFragmentationRefreshButton() {
    const statusCard = document.getElementById('fragmentationStatusCard');
    if (statusCard && !statusCard.querySelector('.refresh-btn')) {
        const refreshBtn = document.createElement('button');
        refreshBtn.className = 'btn refresh-btn';
        refreshBtn.textContent = '🔄 Refresh Status';
        refreshBtn.style.marginTop = '10px';
        refreshBtn.onclick = function() {
            refreshBtn.textContent = '🔄 Refreshing...';
            refreshBtn.disabled = true;
            checkFragmentationAuthStatus();
            setTimeout(() => {
                refreshBtn.textContent = '🔄 Refresh Status';
                refreshBtn.disabled = false;
            }, 2000);
        };
        statusCard.appendChild(refreshBtn);
    }
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

// Universal rename helper functions
function startRename(service, fileId, originalName, element) {
    const nameSpan = document.getElementById(`${service}-name-${fileId}`);
    const nameInput = document.getElementById(`${service}-input-${fileId}`);
    
    if (nameSpan && nameInput) {
        nameSpan.style.display = 'none';
        nameInput.style.display = 'inline-block';
        nameInput.focus();
        nameInput.select();
    }
}

function cancelRename(service, fileId) {
    const nameSpan = document.getElementById(`${service}-name-${fileId}`);
    const nameInput = document.getElementById(`${service}-input-${fileId}`);
    
    if (nameSpan && nameInput) {
        nameSpan.style.display = 'inline-block';
        nameInput.style.display = 'none';
        // Reset to original value
        nameInput.value = nameInput.defaultValue;
    }
}

function handleRenameKeydown(event, service, fileId, originalName, filePath = null) {
    if (event.key === 'Enter') {
        event.preventDefault();
        const nameInput = document.getElementById(`${service}-input-${fileId}`);
        const newName = nameInput.value.trim();
        
        // Hide the input and show the span
        const nameSpan = document.getElementById(`${service}-name-${fileId}`);
        if (nameSpan && nameInput) {
            nameSpan.style.display = 'inline-block';
            nameInput.style.display = 'none';
        }
        
        // Call the appropriate rename function
        if (service === 'google') {
            renameGoogleFile(fileId, newName, originalName);
        } else if (service === 'dropbox') {
            // For dropbox, get the path from the data attribute
            const nameSpan = document.getElementById(`${service}-name-${fileId}`);
            const actualPath = nameSpan ? nameSpan.getAttribute('data-path') : null;
            if (actualPath) {
                renameDropboxFile(actualPath, newName, originalName);
            } else {
                showAlert('Error: Could not find file path', 'error');
            }
        } else if (service === 'onedrive') {
            renameOneDriveFile(fileId, newName, originalName);
        }
    } else if (event.key === 'Escape') {
        event.preventDefault();
        cancelRename(service, fileId);
    }
}

// Folder navigation functions
function navigateToGoogleFolder(folderId, folderName) {
    console.log('Navigating to Google folder:', folderName, 'ID:', folderId);
    listGoogleFiles(folderId);
}

function navigateToDropboxFolder(folderPath, folderName) {
    console.log('Navigating to Dropbox folder:', folderName, 'Path:', folderPath);
    listDropboxFiles(folderPath);
}

function navigateToOneDriveFolder(folderId, folderName) {
    console.log('Navigating to OneDrive folder:', folderName, 'ID:', folderId);
    // Push current location to navigation stack
    onedriveNavigationStack.push({
        id: currentOneDrivePath || 'root',
        name: currentOneDrivePath ? 'Previous Folder' : 'Root'
    });
    listOneDriveFiles(folderId);
}

function goBack(service) {
    if (service === 'google') {
        if (currentGooglePath !== 'root') {
            // For simplicity, go back to root. In a more advanced implementation,
            // you'd maintain a path stack
            listGoogleFiles('root');
        }
    } else if (service === 'dropbox') {
        if (currentDropboxPath) {
            const parentPath = currentDropboxPath.split('/').slice(0, -1).join('/');
            listDropboxFiles(parentPath);
        }
    } else if (service === 'onedrive') {
        if (onedriveNavigationStack.length > 0) {
            const previousLocation = onedriveNavigationStack.pop();
            listOneDriveFiles(previousLocation.id === 'root' ? '' : previousLocation.id);
        }
    }
}

function getNavigationBar(service) {
    let currentPath = '';
    let serviceName = '';
    
    if (service === 'google') {
        currentPath = currentGooglePath === 'root' ? 'Root' : 'Subfolder';
        serviceName = 'Google Drive';
    } else if (service === 'dropbox') {
        currentPath = currentDropboxPath || 'Root';
        serviceName = 'Dropbox';
    } else if (service === 'onedrive') {
        currentPath = onedriveNavigationStack.length > 0 ? 'Subfolder' : 'Root';
        serviceName = 'OneDrive';
    }
    
    const showBackButton = (service === 'google' && currentGooglePath !== 'root') ||
                          (service === 'dropbox' && currentDropboxPath !== '') ||
                          (service === 'onedrive' && onedriveNavigationStack.length > 0);
    
    return `
        <div class="navigation-bar">
            <div class="breadcrumb">
                <span class="service-name">${serviceName}:</span>
                <span class="current-path">📁 ${currentPath}</span>
            </div>
            ${showBackButton ? `<button class="back-btn" onclick="goBack('${service}')">⬅️ Back</button>` : ''}
        </div>
    `;
}

