<?php
class FragmentedFile {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($userId, $originalFilename, $originalSize, $mimeType, $fileHash, $chunkSize = 1048576, $redundancyLevel = 2) {
        try {
            $totalChunks = ceil($originalSize / $chunkSize);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO fragmented_files (user_id, original_filename, original_size, mime_type, file_hash, chunk_size, total_chunks, redundancy_level) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $originalFilename, $originalSize, $mimeType, $fileHash, $chunkSize, $totalChunks, $redundancyLevel]);
            
            $fragmentedFileId = $this->pdo->lastInsertId();
            
            return [
                'success' => true, 
                'fragmented_file_id' => $fragmentedFileId,
                'total_chunks' => $totalChunks
            ];
            
        } catch (PDOException $e) {
            error_log("FragmentedFile creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create fragmented file record'];
        }
    }
    
    public function getById($fragmentedFileId) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM fragmented_files WHERE id = ?");
            $stmt->execute([$fragmentedFileId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("FragmentedFile fetch error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getByUserAndHash($userId, $fileHash) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM fragmented_files WHERE user_id = ? AND file_hash = ?");
            $stmt->execute([$userId, $fileHash]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("FragmentedFile fetch by hash error: " . $e->getMessage());
            return false;
        }
    }
    
    public function listByUser($userId, $limit = 20, $offset = 0) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT f.*, 
                       COUNT(fr.id) as uploaded_chunks
                FROM fragmented_files f 
                LEFT JOIN file_fragments fr ON f.id = fr.fragmented_file_id
                WHERE f.user_id = ? 
                GROUP BY f.id 
                ORDER BY f.created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$userId, $limit, $offset]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("FragmentedFile list error: " . $e->getMessage());
            return [];
        }
    }
    
    public function updateStatus($fragmentedFileId, $status) {
        try {
            $stmt = $this->pdo->prepare("UPDATE fragmented_files SET status = ? WHERE id = ?");
            $stmt->execute([$status, $fragmentedFileId]);
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("FragmentedFile status update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update status'];
        }
    }
    
    public function delete($fragmentedFileId) {
        try {
            // Start transaction
            $this->pdo->beginTransaction();
            
            // Delete fragments first (will be handled by cascade, but we'll be explicit)
            $stmt = $this->pdo->prepare("DELETE FROM file_fragments WHERE fragmented_file_id = ?");
            $stmt->execute([$fragmentedFileId]);
            
            // Delete main record
            $stmt = $this->pdo->prepare("DELETE FROM fragmented_files WHERE id = ?");
            $stmt->execute([$fragmentedFileId]);
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Fragmented file deleted successfully'];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("FragmentedFile delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete fragmented file'];
        }
    }
    
    public function getProgress($fragmentedFileId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT f.total_chunks, COUNT(fr.id) as uploaded_chunks 
                FROM fragmented_files f 
                LEFT JOIN file_fragments fr ON f.id = fr.fragmented_file_id
                WHERE f.id = ? 
                GROUP BY f.id, f.total_chunks
            ");
            $stmt->execute([$fragmentedFileId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $progress = ($result['uploaded_chunks'] / $result['total_chunks']) * 100;
                return [
                    'total_chunks' => $result['total_chunks'],
                    'uploaded_chunks' => $result['uploaded_chunks'],
                    'progress_percentage' => round($progress, 2)
                ];
            }
            
            return false;
            
        } catch (PDOException $e) {
            error_log("FragmentedFile progress error: " . $e->getMessage());
            return false;
        }
    }
}
?> 