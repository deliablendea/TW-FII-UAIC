<?php
class EmailConfig {
  
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_SECURE = 'tls'; 
    const SMTP_AUTH = true;
    
  
    const SMTP_USERNAME = 'liviagosp@gmail.com';  
    const SMTP_PASSWORD = 'zecrbookryggymmi';     
    const FROM_EMAIL = 'liviagosp@gmail.com';     
    const FROM_NAME = 'Cloud9 Support';           
    const REPLY_TO = 'liviagosp@gmail.com';      
    
    
    const APP_URL = 'http://localhost/TW-FII-UAIC';
    
   
    public static function getSmtpConfig() {
        return [
            'host' => self::SMTP_HOST,
            'port' => self::SMTP_PORT,
            'secure' => self::SMTP_SECURE,
            'auth' => self::SMTP_AUTH,
            'username' => self::SMTP_USERNAME,
            'password' => self::SMTP_PASSWORD
        ];
    }
    
        
    public static function getSenderConfig() {
        return [
            'from_email' => self::FROM_EMAIL,
            'from_name' => self::FROM_NAME,
            'reply_to' => self::REPLY_TO
        ];
    }
    
    public static function getAppUrl() {
        return self::APP_URL;
    }
    
    
    public static function isConfigured() {
        return self::SMTP_USERNAME !== 'your-email@gmail.com' 
            && self::SMTP_PASSWORD !== 'your-app-password';
    }
}
?> 