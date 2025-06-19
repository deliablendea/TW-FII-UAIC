<?php
class OneDriveConfig {
    // Microsoft OneDrive OAuth 2.0 configuration
    const CLIENT_ID = '5902d739-b52b-4f20-8769-e3e0d8892665';
    const CLIENT_SECRET = 'AVC8Q~ih~c9ZwufQgGE-A3oWpCedUMI5CUb0WaFO';
    const REDIRECT_URI = 'http://localhost/TW-FII-UAIC/api/oauth/onedrive/callback.php';
    
    // Microsoft OAuth 2.0 endpoints
    const AUTHORIZATION_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
    const TOKEN_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    
    // Microsoft Graph API endpoints
    const API_URL = 'https://graph.microsoft.com/v1.0';
    
    // Required scopes for OneDrive access
    const SCOPES = 'Files.ReadWrite.All offline_access';
    
    public static function getAuthUrl($state = null) {
        $params = [
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'response_mode' => 'query'
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return self::AUTHORIZATION_URL . '?' . http_build_query($params);
    }
}
?> 