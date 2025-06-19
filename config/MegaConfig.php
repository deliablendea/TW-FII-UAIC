<?php
class MegaConfig {
    // MEGA API configuration
    const API_URL = 'https://g.api.mega.co.nz/cs';
    const UPLOAD_URL = 'https://g.api.mega.co.nz/cs';
    
    // MEGA doesn't use OAuth - it uses email/password authentication
    // We'll store encrypted credentials in the database
    
    public static function encryptCredentials($email, $password) {
        // Simple encryption for storing credentials
        $key = 'your-encryption-key-change-this'; // Change this to a secure key
        $data = json_encode(['email' => $email, 'password' => $password]);
        return base64_encode(openssl_encrypt($data, 'AES-128-CBC', $key, 0, substr($key, 0, 16)));
    }
    
    public static function decryptCredentials($encryptedData) {
        $key = 'your-encryption-key-change-this'; // Same key as above
        $decrypted = openssl_decrypt(base64_decode($encryptedData), 'AES-128-CBC', $key, 0, substr($key, 0, 16));
        return json_decode($decrypted, true);
    }
    
    public static function makeApiRequest($data, $sessionId = null) {
        $url = self::API_URL;
        if ($sessionId) {
            $url .= '?id=' . $sessionId;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: TW-FII-UAIC-CloudStorage/1.0'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => 'HTTP Error: ' . $httpCode];
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'Invalid JSON response'];
        }
        
        return ['success' => true, 'data' => $decoded];
    }
    
    public static function login($email, $password) {
        // MEGA login process
        $loginData = [
            ['a' => 'us', 'user' => $email, 'uh' => self::generatePasswordHash($email, $password)]
        ];
        
        $response = self::makeApiRequest($loginData);
        
        if (!$response['success']) {
            return $response;
        }
        
        $result = $response['data'][0] ?? null;
        if (is_array($result) && isset($result['tsid'])) {
            return ['success' => true, 'session_id' => $result['tsid']];
        }
        
        return ['success' => false, 'error' => 'Login failed'];
    }
    
    private static function generatePasswordHash($email, $password) {
        // MEGA uses a specific password hashing algorithm
        // This is a simplified version - in production you'd want the exact MEGA algorithm
        return hash('sha256', strtolower($email) . $password);
    }
}
?> 