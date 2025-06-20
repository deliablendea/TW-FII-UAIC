<?php
class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($name, $email, $password) {
        try {
            if ($this->findByEmail($email)) {
                return ['success' => false, 'message' => 'Email already registered'];
            }
            
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $passwordHash]);
            
            return ['success' => true, 'message' => 'Account created successfully!'];
            
        } catch (PDOException $e) {
            error_log("User creation error: " . $e->getMessage());
            if ($e->getCode() == '23505') { // PostgreSQL unique violation error
                return ['success' => false, 'message' => 'Email already registered'];
            }
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    public function findByEmail($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("User find error: " . $e->getMessage());
            return false;
        }
    }
    
    public function findById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, email, created_at FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("User find by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public function update($id, $data) {
        try {
            $fields = [];
            $values = [];
            
            foreach ($data as $key => $value) {
                if (in_array($key, ['name', 'email', 'password_hash'])) {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }
            
            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields to update'];
            }
            
            $values[] = $id;
            $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            
            return ['success' => true, 'message' => 'User updated successfully'];
            
        } catch (PDOException $e) {
            error_log("User update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
}
?> 