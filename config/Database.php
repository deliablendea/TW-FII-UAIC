<?php
class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $host = 'localhost';
        $port = '6433';
        $dbname = 'postgres';
        $username = 'postgres';
        $password = 'admin';
        
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        
        try {
            $this->pdo = new PDO($dsn, $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }   
    
    public function getConnection() {
        return $this->pdo;
    }
    
    private function __clone() {}
    
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
?> 