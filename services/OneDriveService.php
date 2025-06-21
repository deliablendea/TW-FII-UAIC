<?php
require_once __DIR__ . '/../config/OneDriveConfig.php';
require_once __DIR__ . '/../models/OAuthToken.php';

class OneDriveService {
    private $oauthTokenModel;
    
    public function __construct($pdo) {
        $this->oauthTokenModel = new OAuthToken($pdo);
    }
    
    public function uploadFile($userId, $filePath, $fileName, $targetPath = '/') {
        $token = $this->getValidToken($userId);
        if (!$token) {
            return ['success' => false, 'message' => 'No valid OneDrive token found'];
        }
        
        // Sanitize file name for OneDrive
        $fileName = $this->sanitizeFileName($fileName);
        
        // Construct the upload URL
        $uploadPath = $targetPath === '/' ? $fileName : rtrim($targetPath, '/') . '/' . $fileName;
        $url = OneDriveConfig::API_URL . '/me/drive/root:/' . urlencode($uploadPath) . ':/content';
        
        // Read file content
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return ['success' => false, 'message' => 'Failed to read file'];
        }
        
        // For files larger than 4MB, we should use resumable upload
        // For now, we'll handle smaller files with simple upload
        if (strlen($fileContent) > 4 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File too large. OneDrive integration currently supports files up to 4MB'];
        }
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/octet-stream'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fileContent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            error_log("OneDrive upload failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Upload failed', 'http_code' => $httpCode];
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            return ['success' => false, 'message' => 'Invalid response from OneDrive'];
        }
        
        return [
            'success' => true,
            'file_id' => $data['id'],
            'name' => $data['name'],
            'path' => $data['parentReference']['path'] . '/' . $data['name'],
            'size' => $data['size'],
            'web_url' => $data['webUrl'] ?? null
        ];
    }
    
    public function listFiles($userId, $path = '', $folderId = null) {
        $token = $this->getValidToken($userId);
        if (!$token) {
            return ['success' => false, 'message' => 'No valid OneDrive token found'];
        }
        
        // Construct the list URL
        if ($folderId) {
            // Use folder ID for navigation
            $url = OneDriveConfig::API_URL . '/me/drive/items/' . urlencode($folderId) . '/children';
        } elseif (empty($path) || $path === '/') {
            $url = OneDriveConfig::API_URL . '/me/drive/root/children';
        } else {
            $url = OneDriveConfig::API_URL . '/me/drive/root:/' . urlencode(trim($path, '/')) . ':/children';
        }
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("OneDrive list files failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to list files', 'http_code' => $httpCode];
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['value'])) {
            return ['success' => false, 'message' => 'Invalid response from OneDrive'];
        }
        
        $files = [];
        foreach ($data['value'] as $item) {
            $files[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'type' => isset($item['folder']) ? 'folder' : 'file',
                'path' => $item['parentReference']['path'] . '/' . $item['name'],
                'size' => $item['size'] ?? 0,
                'modified' => $item['lastModifiedDateTime'] ?? null,
                'web_url' => $item['webUrl'] ?? null
            ];
        }
        
        return [
            'success' => true,
            'files' => $files
        ];
    }
    
    public function deleteFile($userId, $fileId) {
        $token = $this->getValidToken($userId);
        if (!$token) {
            return ['success' => false, 'message' => 'No valid OneDrive token found'];
        }
        
        $url = OneDriveConfig::API_URL . '/me/drive/items/' . urlencode($fileId);
        
        $headers = [
            'Authorization: Bearer ' . $token
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 204) {
            error_log("OneDrive delete failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to delete file', 'http_code' => $httpCode];
        }
        
        return ['success' => true, 'message' => 'File deleted successfully'];
    }
    
    public function renameFile($userId, $fileId, $newName) {
        $token = $this->getValidToken($userId);
        if (!$token) {
            return ['success' => false, 'message' => 'No valid OneDrive token found'];
        }
        
        // Sanitize the filename
        $sanitizedName = $this->sanitizeFileName($newName);
        
        $url = OneDriveConfig::API_URL . '/me/drive/items/' . urlencode($fileId);
        
        $metadata = [
            'name' => $sanitizedName
        ];
        
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("OneDrive rename failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to rename file', 'http_code' => $httpCode];
        }
        
        $data = json_decode($response, true);
        return [
            'success' => true, 
            'message' => 'File renamed successfully',
            'name' => $data['name'] ?? $sanitizedName
        ];
    }
    
    public function downloadFile($userId, $fileId) {
        $token = $this->getValidToken($userId);
        if (!$token) {
            return ['success' => false, 'message' => 'No valid OneDrive token found'];
        }
        
        $url = OneDriveConfig::API_URL . '/me/drive/items/' . urlencode($fileId) . '/content';
        
        $headers = [
            'Authorization: Bearer ' . $token
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("OneDrive download failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to download file', 'http_code' => $httpCode];
        }
        
        return [
            'success' => true,
            'content' => $response,
            'content_type' => $contentType
        ];
    }
    
    private function getValidToken($userId) {
        $tokenData = $this->oauthTokenModel->getToken($userId, 'onedrive');
        if (!$tokenData) {
            return null;
        }
        
        // Check if token is expired
        if ($tokenData['expires_at'] && strtotime($tokenData['expires_at']) <= time()) {
            // Token is expired, try to refresh if we have a refresh token
            if ($tokenData['refresh_token']) {
                $newToken = $this->refreshToken($tokenData['refresh_token']);
                if ($newToken['success']) {
                    $this->oauthTokenModel->saveToken(
                        $userId,
                        'onedrive',
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
            'client_id' => OneDriveConfig::CLIENT_ID,
            'client_secret' => OneDriveConfig::CLIENT_SECRET,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
            'scope' => OneDriveConfig::SCOPES
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, OneDriveConfig::TOKEN_URL);
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
            error_log("OneDrive token refresh failed: HTTP $httpCode - $response");
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
    
    private function sanitizeFileName($fileName) {
        // OneDrive doesn't allow certain characters in file names
        $invalidChars = ['\\', '/', ':', '*', '?', '"', '<', '>', '|'];
        return str_replace($invalidChars, '_', $fileName);
    }
}
?> 