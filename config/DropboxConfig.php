<?php
class DropboxConfig {
    // Dropbox OAuth 2.0 configuration
    const CLIENT_ID = '29lo7sj7uvy9j0d';
    const CLIENT_SECRET = 'mbiesp0vqdjc6yo';
    const REDIRECT_URI = 'http://localhost/TW-FII-UAIC/api/oauth/dropbox/callback.php';
    
    // Dropbox OAuth 2.0 endpoints
    const AUTHORIZATION_URL = 'https://www.dropbox.com/oauth2/authorize';
    const TOKEN_URL = 'https://api.dropboxapi.com/oauth2/token';
    
    // Dropbox API endpoints
    const API_URL = 'https://api.dropboxapi.com/2';
    const CONTENT_URL = 'https://content.dropboxapi.com/2';
    
    public static function getAuthUrl($state = null) {
        $params = [
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'token_access_type' => 'offline' // For refresh tokens
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return self::AUTHORIZATION_URL . '?' . http_build_query($params);
    }
}
?> 