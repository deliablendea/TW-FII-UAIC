<?php
class GoogleConfig {
    // Google OAuth 2.0 configuration
    const CLIENT_ID = 'womp1';
    const CLIENT_SECRET = 'womp2';
    const REDIRECT_URI = 'http://localhost/TW-FII-UAIC/api/oauth/google/callback.php';
    
    // Google OAuth 2.0 endpoints
    const AUTHORIZATION_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';
    
    // Google Drive API scope
    const SCOPES = [
        'https://www.googleapis.com/auth/drive.file',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile'
    ];
    
    public static function getAuthUrl($state = null) {
        $params = [
            'client_id' => self::CLIENT_ID,
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => implode(' ', self::SCOPES),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return self::AUTHORIZATION_URL . '?' . http_build_query($params);
    }
}
?> 