<?php
require_once __DIR__ . '/../config/DropboxConfig.php';
require_once __DIR__ . '/../models/OAuthToken.php';

class DropboxService {
    private $oauthTokenModel;
    
    public function __construct($pdo) {
        $this->oauthTokenModel = new OAuthToken($pdo);
    }
    
    public function uploadFile($userId, $filePath, $fileName, $targetPath = '/') {
        $token = $this->getValidToken($userId);
        if (!$token) {
            return ['success' => false, 'message' => 'No valid Dropbox token found'];
        }
        
        // Ensure target path starts with /
        if (!str_starts_with($targetPath, '/')) {
            $targetPath = '/' . $targetPath;
        }
        
        // Ensure target path ends with /
        if (!str_ends_with($targetPath, '/')) {
            $targetPath .= '/';
        }
        
        $dropboxPath = $targetPath . $fileName;
        
        // Read file content
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return ['success' => false, 'message' => 'Failed to read file'];
        }
        
        // Prepare API call
        $url = DropboxConfig::CONTENT_URL . '/files/upload';
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/octet-stream',
            'Dropbox-API-Arg: ' . json_encode([
                'path' => $dropboxPath,
                'mode' => 'add',
                'autorename' => true
            ])
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Dropbox upload failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Upload failed', 'http_code' => $httpCode];
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            return ['success' => false, 'message' => 'Invalid response from Dropbox'];
        }
        
        return [
            'success' => true,
            'file_id' => $data['id'],
            'name' => $data['name'],
            'path' => $data['path_display'],
            'size' => $data['size']
        ];
    }
    
    public function listFiles($userId, $path = '') {
        $token = $this->getValidToken($userId);
        if (!$token) {
            return ['success' => false, 'message' => 'No valid Dropbox token found'];
        }
        
        // Default to root if path is empty
        if (empty($path)) {
            $path = '';
        } else if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        
        $url = DropboxConfig::API_URL . '/files/list_folder';
        
        $postData = json_encode([
            'path' => $path,
            'recursive' => false,
            'include_media_info' => false,
            'include_deleted' => false,
            'include_has_explicit_shared_members' => false,
            'include_mounted_folders' => true
        ]);
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Dropbox list files failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to list files', 'http_code' => $httpCode];
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            return ['success' => false, 'message' => 'Invalid response from Dropbox'];
        }
        
        $files = [];
        foreach ($data['entries'] as $entry) {
            $files[] = [
                'id' => $entry['id'] ?? $entry['path_lower'],
                'name' => $entry['name'],
                'type' => $entry['.tag'], // 'file' or 'folder'
                'path' => $entry['path_display'],
                'size' => $entry['size'] ?? 0,
                'modified' => $entry['server_modified'] ?? null
            ];
        }
        
        return [
            'success' => true,
            'files' => $files,
            'has_more' => $data['has_more'] ?? false
        ];
    }
    
    public function deleteFile($userId, $path) {
        $token = $this->getValidToken($userId);
        if (!$token) {
            return ['success' => false, 'message' => 'No valid Dropbox token found'];
        }
        
        $url = DropboxConfig::API_URL . '/files/delete_v2';
        
        $postData = json_encode([
            'path' => $path
        ]);
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Dropbox delete failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to delete file', 'http_code' => $httpCode];
        }
        
        return ['success' => true, 'message' => 'File deleted successfully'];
    }
    
    public function downloadFile($userId, $path) {
        $token = $this->getValidToken($userId);
        if (!$token) {
            return ['success' => false, 'message' => 'No valid Dropbox token found'];
        }
        
        $url = DropboxConfig::CONTENT_URL . '/files/download';
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Dropbox-API-Arg: ' . json_encode(['path' => $path])
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Dropbox download failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to download file', 'http_code' => $httpCode];
        }
        
        return [
            'success' => true,
            'content' => $response,
            'content_type' => $contentType
        ];
    }
    
    private function getValidToken($userId) {
        $tokenData = $this->oauthTokenModel->getToken($userId, 'dropbox');
        if (!$tokenData) {
            return null;
        }
        
        // Dropbox tokens typically don't expire, but check if we have expiry info
        if ($tokenData['expires_at'] && strtotime($tokenData['expires_at']) <= time()) {
            // Token is expired, try to refresh if we have a refresh token
            if ($tokenData['refresh_token']) {
                $newToken = $this->refreshToken($tokenData['refresh_token']);
                if ($newToken['success']) {
                    $this->oauthTokenModel->saveToken(
                        $userId,
                        'dropbox',
                        $newToken['access_token'],
                        $newToken['refresh_token'] ?? $tokenData['refresh_token'],
                        $newToken['expires_at'] ?? null
                    );
                    return $newToken['access_token'];
                }
            }
            return null;
        }
        
        return $tokenData['access_token'];
    }
    
    private function refreshToken($refreshToken) {
        $postData = [
            'client_id' => DropboxConfig::CLIENT_ID,
            'client_secret' => DropboxConfig::CLIENT_SECRET,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, DropboxConfig::TOKEN_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Dropbox token refresh failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to refresh token'];
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['access_token'])) {
            return ['success' => false, 'message' => 'Invalid refresh response'];
        }
        
        $expiresAt = null;
        if (isset($data['expires_in'])) {
            $expiresAt = date('Y-m-d H:i:s', time() + $data['expires_in']);
        }
        
        return [
            'success' => true,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => $expiresAt
        ];
    }
}
?> 