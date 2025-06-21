<?php
require_once __DIR__ . '/../models/OAuthToken.php';
require_once __DIR__ . '/../config/GoogleConfig.php';

class GoogleDriveService {
    private $oauthTokenModel;
    private $userId;
    
    const DRIVE_API_URL = 'https://www.googleapis.com/drive/v3';
    const UPLOAD_API_URL = 'https://www.googleapis.com/upload/drive/v3';
    
    public function __construct($pdo, $userId) {
        $this->oauthTokenModel = new OAuthToken($pdo);
        $this->userId = $userId;
    }
    
    public function uploadFile($filePath, $fileName, $parentFolderId = null) {
        $token = $this->getValidToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No valid Google Drive token'];
        }
        
        // Create metadata
        $metadata = [
            'name' => $fileName
        ];
        
        if ($parentFolderId) {
            $metadata['parents'] = [$parentFolderId];
        }
        
        // Determine MIME type
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        
        // Prepare multipart upload
        $boundary = uniqid();
        $delimiter = '-------314159265358979323846';
        
        $postData = "--{$delimiter}\r\n";
        $postData .= "Content-Type: application/json; charset=UTF-8\r\n\r\n";
        $postData .= json_encode($metadata) . "\r\n";
        $postData .= "--{$delimiter}\r\n";
        $postData .= "Content-Type: {$mimeType}\r\n\r\n";
        $postData .= file_get_contents($filePath) . "\r\n";
        $postData .= "--{$delimiter}--";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::UPLOAD_API_URL . '/files?uploadType=multipart');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: multipart/related; boundary=' . $delimiter,
            'Content-Length: ' . strlen($postData)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 || $httpCode === 201) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'file_id' => $data['id'],
                'name' => $data['name'],
                'size' => $data['size'] ?? null,
                'web_view_link' => $data['webViewLink'] ?? null
            ];
        } else {
            error_log("Google Drive upload failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to upload file to Google Drive'];
        }
    }
    
    public function downloadFile($fileId, $savePath = null) {
        $token = $this->getValidToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No valid Google Drive token'];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::DRIVE_API_URL . '/files/' . $fileId . '?alt=media');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            if ($savePath) {
                file_put_contents($savePath, $response);
                return ['success' => true, 'saved_to' => $savePath];
            } else {
                return ['success' => true, 'content' => $response];
            }
        } else {
            error_log("Google Drive download failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to download file from Google Drive'];
        }
    }
    
    public function listFiles($pageSize = 10, $pageToken = null, $parentFolderId = null) {
        $token = $this->getValidToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No valid Google Drive token'];
        }
        
        $params = [
            'pageSize' => $pageSize,
            'fields' => 'nextPageToken, files(id, name, size, mimeType, createdTime, modifiedTime, webViewLink)'
        ];
        
        // Add parent folder filter if specified
        if ($parentFolderId && $parentFolderId !== 'root') {
            $params['q'] = "'{$parentFolderId}' in parents and trashed=false";
        } else {
            $params['q'] = "trashed=false";
        }
        
        if ($pageToken) {
            $params['pageToken'] = $pageToken;
        }
        
        $url = self::DRIVE_API_URL . '/files?' . http_build_query($params);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'files' => $data['files'],
                'nextPageToken' => $data['nextPageToken'] ?? null
            ];
        } else {
            error_log("Google Drive list failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to list files from Google Drive'];
        }
    }
    
    public function deleteFile($fileId) {
        $token = $this->getValidToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No valid Google Drive token'];
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::DRIVE_API_URL . '/files/' . $fileId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 204) {
            return ['success' => true, 'message' => 'File deleted successfully'];
        } else {
            error_log("Google Drive delete failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to delete file from Google Drive'];
        }
    }
    
    public function renameFile($fileId, $newName) {
        $token = $this->getValidToken();
        if (!$token) {
            return ['success' => false, 'message' => 'No valid Google Drive token'];
        }
        
        $metadata = [
            'name' => $newName
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::DRIVE_API_URL . '/files/' . $fileId);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($metadata));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'success' => true, 
                'message' => 'File renamed successfully',
                'name' => $data['name']
            ];
        } else {
            error_log("Google Drive rename failed: HTTP $httpCode - $response");
            return ['success' => false, 'message' => 'Failed to rename file in Google Drive'];
        }
    }
    
    private function getValidToken() {
        $token = $this->oauthTokenModel->getToken($this->userId, 'google');
        if (!$token) {
            return null;
        }
        
        // Check if token is expired and refresh if needed
        if ($token['expires_at'] && strtotime($token['expires_at']) <= time() + 300) {
            $refreshResult = $this->refreshToken($token['refresh_token']);
            if ($refreshResult['success']) {
                return $refreshResult['access_token'];
            } else {
                return null;
            }
        }
        
        return $token['access_token'];
    }
    
    private function refreshToken($refreshToken) {
        if (!$refreshToken) {
            return ['success' => false, 'message' => 'No refresh token available'];
        }
        
        $postData = [
            'client_id' => GoogleConfig::CLIENT_ID,
            'client_secret' => GoogleConfig::CLIENT_SECRET,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, GoogleConfig::TOKEN_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if ($data && isset($data['access_token'])) {
                $expiresAt = null;
                if (isset($data['expires_in'])) {
                    $expiresAt = date('Y-m-d H:i:s', time() + $data['expires_in']);
                }
                
                // Save the refreshed token
                $this->oauthTokenModel->refreshToken(
                    $this->userId,
                    'google',
                    $data['access_token'],
                    $data['refresh_token'] ?? $refreshToken,
                    $expiresAt
                );
                
                return ['success' => true, 'access_token' => $data['access_token']];
            }
        }
        
        return ['success' => false, 'message' => 'Failed to refresh token'];
    }
}
?> 