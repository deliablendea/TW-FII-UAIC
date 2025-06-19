<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Fragmentation System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        .status-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        .status-ok { border-color: #28a745; background-color: #d4edda; }
        .status-warning { border-color: #ffc107; background-color: #fff3cd; }
        .status-error { border-color: #dc3545; background-color: #f8d7da; }
        
        .upload-section {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            transition: border-color 0.3s;
        }
        .upload-section:hover { border-color: #007bff; }
        .upload-section.dragover { border-color: #007bff; background-color: #f8f9fa; }
        
        .btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        .btn:hover { background-color: #0056b3; }
        .btn:disabled { background-color: #6c757d; cursor: not-allowed; }
        .btn-danger { background-color: #dc3545; }
        .btn-danger:hover { background-color: #c82333; }
        .btn-success { background-color: #28a745; }
        .btn-success:hover { background-color: #218838; }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .files-list {
            margin-top: 30px;
        }
        .file-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .file-info h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .file-meta {
            color: #666;
            font-size: 14px;
        }
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .progress-fill {
            height: 100%;
            background-color: #28a745;
            transition: width 0.3s;
        }
        
        .provider-status {
            display: flex;
            gap: 15px;
            margin: 15px 0;
        }
        .provider {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .provider.connected { background-color: #d4edda; color: #155724; }
        .provider.disconnected { background-color: #f8d7da; color: #721c24; }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        .success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .file-item {
                flex-direction: column;
                align-items: flex-start;
            }
            .file-actions {
                margin-top: 15px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🗂️ File Fragmentation System</h1>
            <p>Securely store large files across multiple cloud services with redundancy</p>
        </div>
        
        <div id="statusCard" class="status-card">
            <div class="loading">Checking authentication status...</div>
        </div>
        
        <div id="uploadSection" class="upload-section" style="display: none;">
            <h3>Upload File for Fragmentation</h3>
            <p>Drop a file here or click to select</p>
            <input type="file" id="fileInput" style="display: none;">
            <button class="btn" onclick="document.getElementById('fileInput').click()">Select File</button>
            
            <div class="form-group" style="margin-top: 20px; max-width: 400px; margin-left: auto; margin-right: auto;">
                <label for="chunkSize">Chunk Size:</label>
                <select id="chunkSize" class="form-control">
                    <option value="524288">512 KB</option>
                    <option value="1048576" selected>1 MB</option>
                    <option value="2097152">2 MB</option>
                    <option value="5242880">5 MB</option>
                </select>
            </div>
            
            <div class="form-group" style="max-width: 400px; margin-left: auto; margin-right: auto;">
                <label for="redundancyLevel">Redundancy Level:</label>
                <select id="redundancyLevel" class="form-control">
                    <option value="1">1 (Single copy)</option>
                    <option value="2" selected>2 (Double redundancy)</option>
                    <option value="3">3 (Triple redundancy)</option>
                </select>
            </div>
            
            <button id="uploadBtn" class="btn" onclick="uploadFile()" disabled>Upload & Fragment</button>
        </div>
        
        <div class="files-list">
            <h3>Fragmented Files</h3>
            <div id="filesList">
                <div class="loading">Loading files...</div>
            </div>
        </div>
    </div>

    <script>
        let authStatus = null;
        let selectedFile = null;
        
        // Check authentication status on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkAuthStatus();
            setupFileUpload();
        });
        
        function checkAuthStatus() {
            fetch('api/fragmentation.php?action=status')
                .then(response => response.json())
                .then(data => {
                    authStatus = data;
                    updateStatusCard();
                    if (data.fragmentation_available) {
                        loadFiles();
                    }
                })
                .catch(error => {
                    console.error('Error checking auth status:', error);
                    document.getElementById('statusCard').innerHTML = 
                        '<div class="error">Error checking authentication status</div>';
                });
        }
        
        function updateStatusCard() {
            const statusCard = document.getElementById('statusCard');
            const uploadSection = document.getElementById('uploadSection');
            
            if (authStatus.fragmentation_available) {
                statusCard.className = 'status-card status-ok';
                statusCard.innerHTML = `
                    <h4>✅ Fragmentation Available</h4>
                    <p>All required cloud services are connected.</p>
                    <div class="provider-status">
                        ${authStatus.authenticated_providers.map(provider => 
                            `<span class="provider connected">${provider.toUpperCase()}</span>`
                        ).join('')}
                    </div>
                `;
                uploadSection.style.display = 'block';
            } else {
                statusCard.className = 'status-card status-warning';
                statusCard.innerHTML = `
                    <h4>⚠️ Authentication Required</h4>
                    <p>Fragmentation requires authentication with all cloud services.</p>
                    <div class="provider-status">
                        ${authStatus.authenticated_providers.map(provider => 
                            `<span class="provider connected">${provider.toUpperCase()}</span>`
                        ).join('')}
                        ${authStatus.missing_providers.map(provider => 
                            `<span class="provider disconnected">${provider.toUpperCase()}</span>`
                        ).join('')}
                    </div>
                    <p style="margin-top: 15px;">
                        Please authenticate with: <strong>${authStatus.missing_providers.join(', ')}</strong>
                    </p>
                `;
                uploadSection.style.display = 'none';
            }
        }
        
        function setupFileUpload() {
            const fileInput = document.getElementById('fileInput');
            const uploadSection = document.getElementById('uploadSection');
            const uploadBtn = document.getElementById('uploadBtn');
            
            fileInput.addEventListener('change', function() {
                selectedFile = this.files[0];
                if (selectedFile) {
                    uploadSection.querySelector('p').textContent = `Selected: ${selectedFile.name} (${formatFileSize(selectedFile.size)})`;
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
                    selectedFile = files[0];
                    fileInput.files = files;
                    this.querySelector('p').textContent = `Selected: ${selectedFile.name} (${formatFileSize(selectedFile.size)})`;
                    uploadBtn.disabled = false;
                }
            });
        }
        
        function uploadFile() {
            if (!selectedFile || !authStatus.fragmentation_available) return;
            
            const uploadBtn = document.getElementById('uploadBtn');
            const formData = new FormData();
            
            formData.append('file', selectedFile);
            formData.append('chunk_size', document.getElementById('chunkSize').value);
            formData.append('redundancy_level', document.getElementById('redundancyLevel').value);
            
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            
            fetch('api/fragmentation.php?action=upload', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('File uploaded and fragmented successfully!', 'success');
                    selectedFile = null;
                    document.getElementById('fileInput').value = '';
                    document.getElementById('uploadSection').querySelector('p').textContent = 'Drop a file here or click to select';
                    loadFiles();
                } else {
                    showMessage('Upload failed: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Upload error:', error);
                showMessage('Upload failed: ' + error.message, 'error');
            })
            .finally(() => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload & Fragment';
            });
        }
        
        function loadFiles() {
            fetch('api/fragmentation.php?action=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayFiles(data.files);
                    } else {
                        document.getElementById('filesList').innerHTML = 
                            '<div class="error">Failed to load files: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading files:', error);
                    document.getElementById('filesList').innerHTML = 
                        '<div class="error">Error loading files</div>';
                });
        }
        
        function displayFiles(files) {
            const filesList = document.getElementById('filesList');
            
            if (files.length === 0) {
                filesList.innerHTML = '<p style="text-align: center; color: #666;">No fragmented files found.</p>';
                return;
            }
            
            filesList.innerHTML = files.map(file => `
                <div class="file-item">
                    <div class="file-info">
                        <h4>${file.original_filename}</h4>
                        <div class="file-meta">
                            Size: ${formatFileSize(file.original_size)} | 
                            Status: ${file.status} | 
                            Chunks: ${file.uploaded_chunks}/${file.total_chunks} |
                            Created: ${new Date(file.created_at).toLocaleDateString()}
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${(file.uploaded_chunks / file.total_chunks) * 100}%"></div>
                        </div>
                    </div>
                    <div class="file-actions">
                        <button class="btn btn-success" onclick="downloadFile(${file.id})" 
                                ${file.status !== 'complete' ? 'disabled' : ''}>
                            Download
                        </button>
                        <button class="btn" onclick="viewFileInfo(${file.id})">Info</button>
                        <button class="btn btn-danger" onclick="deleteFile(${file.id})">Delete</button>
                    </div>
                </div>
            `).join('');
        }
        
        function downloadFile(fileId) {
            const link = document.createElement('a');
            link.href = `api/fragmentation.php?action=download&id=${fileId}`;
            link.click();
        }
        
        function viewFileInfo(fileId) {
            fetch(`api/fragmentation.php?action=info&id=${fileId}`)
                .then(response => response.json())
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
                        showMessage('Failed to get file info: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error getting file info:', error);
                    showMessage('Error getting file info', 'error');
                });
        }
        
        function deleteFile(fileId) {
            if (!confirm('Are you sure you want to delete this fragmented file? This action cannot be undone.')) {
                return;
            }
            
            fetch(`api/fragmentation.php?action=delete&id=${fileId}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('File deleted successfully!', 'success');
                    loadFiles();
                } else {
                    showMessage('Delete failed: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Delete error:', error);
                showMessage('Delete failed: ' + error.message, 'error');
            });
        }
        
        function formatFileSize(bytes) {
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            if (bytes === 0) return '0 Bytes';
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return Math.round(bytes / Math.pow(1024, i) * 100) / 100 + ' ' + sizes[i];
        }
        
        function showMessage(message, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = type;
            messageDiv.textContent = message;
            
            const container = document.querySelector('.container');
            container.insertBefore(messageDiv, container.firstChild);
            
            setTimeout(() => {
                messageDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html> 