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
            $stmt = $this->pdo->prepare("SELECT id, name, email, password_hash, created_at FROM users WHERE id = ?");
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
    
    public function deleteAccount($userId) {
        try {
            // Start transaction for data consistency
            $this->pdo->beginTransaction();
            
            // First, get all fragmented files for this user to clean up cloud storage
            $fragmentedFilesStmt = $this->pdo->prepare("
                SELECT ff.id, ff.original_filename, fg.storage_locations 
                FROM fragmented_files ff 
                LEFT JOIN file_fragments fg ON ff.id = fg.fragmented_file_id 
                WHERE ff.user_id = ?
            ");
            $fragmentedFilesStmt->execute([$userId]);
            $fragmentedData = $fragmentedFilesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Clean up cloud storage files (best effort - don't fail if cloud cleanup fails)
            $cloudCleanupErrors = [];
            if (!empty($fragmentedData)) {
                try {
                    require_once __DIR__ . '/../services/DropboxService.php';
                    require_once __DIR__ . '/../services/GoogleDriveService.php';
                    require_once __DIR__ . '/../services/OneDriveService.php';
                    
                    $dropboxService = new DropboxService($this->pdo);
                    $googleService = new GoogleDriveService($this->pdo, $userId);
                    $onedriveService = new OneDriveService($this->pdo);
                    
                    foreach ($fragmentedData as $fragmentData) {
                        if (!empty($fragmentData['storage_locations'])) {
                            $locations = json_decode($fragmentData['storage_locations'], true);
                            foreach ($locations as $location) {
                                try {
                                    switch ($location['provider']) {
                                        case 'dropbox':
                                            $dropboxService->deleteFile($userId, $location['path']);
                                            break;
                                        case 'google':
                                            $googleService->deleteFile($location['file_id']);
                                            break;
                                        case 'onedrive':
                                            $onedriveService->deleteFile($userId, $location['file_id']);
                                            break;
                                    }
                                } catch (Exception $e) {
                                    $cloudCleanupErrors[] = "Failed to delete {$location['provider']} file: " . $e->getMessage();
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $cloudCleanupErrors[] = "Cloud cleanup error: " . $e->getMessage();
                }
            }
            
            // Delete user record (CASCADE will handle related tables)
            $deleteStmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $deleteStmt->execute([$userId]);
            
            if ($deleteStmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'User not found or already deleted'];
            }
            
            // Commit transaction
            $this->pdo->commit();
            
            // Log cloud cleanup errors but don't fail the account deletion
            if (!empty($cloudCleanupErrors)) {
                error_log("Account deletion completed with cloud cleanup warnings: " . implode('; ', $cloudCleanupErrors));
            }
            
            return [
                'success' => true, 
                'message' => 'Account deleted successfully',
                'cloud_cleanup_warnings' => $cloudCleanupErrors
            ];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Account deletion database error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred during account deletion'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Account deletion error: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during account deletion'];
        }
    }
}
?> 