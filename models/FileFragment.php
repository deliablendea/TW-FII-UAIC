<?php
class FileFragment {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($fragmentedFileId, $chunkIndex, $chunkHash, $chunkSize, $storageLocations) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO file_fragments (fragmented_file_id, chunk_index, chunk_hash, chunk_size, storage_locations) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $fragmentedFileId, 
                $chunkIndex, 
                $chunkHash, 
                $chunkSize, 
                json_encode($storageLocations)
            ]);
            
            return [
                'success' => true, 
                'fragment_id' => $this->pdo->lastInsertId()
            ];
            
        } catch (PDOException $e) {
            error_log("FileFragment creation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create fragment record'];
        }
    }
    
    public function getByFragmentedFileId($fragmentedFileId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM file_fragments 
                WHERE fragmented_file_id = ? 
                ORDER BY chunk_index ASC
            ");
            $stmt->execute([$fragmentedFileId]);
            $fragments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON storage locations
            foreach ($fragments as &$fragment) {
                $fragment['storage_locations'] = json_decode($fragment['storage_locations'], true);
            }
            
            return $fragments;
            
        } catch (PDOException $e) {
            error_log("FileFragment fetch error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getByFragmentedFileIdAndChunkIndex($fragmentedFileId, $chunkIndex) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM file_fragments 
                WHERE fragmented_file_id = ? AND chunk_index = ?
            ");
            $stmt->execute([$fragmentedFileId, $chunkIndex]);
            $fragment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($fragment) {
                $fragment['storage_locations'] = json_decode($fragment['storage_locations'], true);
            }
            
            return $fragment;
            
        } catch (PDOException $e) {
            error_log("FileFragment fetch by index error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateStorageLocations($fragmentId, $storageLocations) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE file_fragments 
                SET storage_locations = ? 
                WHERE id = ?
            ");
            $stmt->execute([json_encode($storageLocations), $fragmentId]);
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log("FileFragment storage update error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update storage locations'];
        }
    }
    
    public function deleteByFragmentedFileId($fragmentedFileId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM file_fragments WHERE fragmented_file_id = ?");
            $stmt->execute([$fragmentedFileId]);
            
            return ['success' => true, 'message' => 'Fragments deleted successfully'];
            
        } catch (PDOException $e) {
            error_log("FileFragment delete error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete fragments'];
        }
    }
    
    public function getMissingChunks($fragmentedFileId, $totalChunks) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT chunk_index 
                FROM file_fragments 
                WHERE fragmented_file_id = ?
            ");
            $stmt->execute([$fragmentedFileId]);
            $existingChunks = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $allChunks = range(0, $totalChunks - 1);
            $missingChunks = array_diff($allChunks, $existingChunks);
            
            return array_values($missingChunks);
            
        } catch (PDOException $e) {
            error_log("FileFragment missing chunks error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getStorageStatistics($fragmentedFileId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT storage_locations 
                FROM file_fragments 
                WHERE fragmented_file_id = ?
            ");
            $stmt->execute([$fragmentedFileId]);
            $fragments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $providerStats = [
                'dropbox' => 0,
                'google' => 0,
                'onedrive' => 0
            ];
            
            foreach ($fragments as $fragment) {
                $locations = json_decode($fragment['storage_locations'], true);
                foreach ($locations as $location) {
                    if (isset($providerStats[$location['provider']])) {
                        $providerStats[$location['provider']]++;
                    }
                }
            }
            
            return $providerStats;
            
        } catch (PDOException $e) {
            error_log("FileFragment statistics error: " . $e->getMessage());
            return [];
        }
    }
    
    public function verifyIntegrity($fragmentedFileId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT f.total_chunks, f.redundancy_level, COUNT(fr.id) as fragment_count
                FROM fragmented_files f 
                LEFT JOIN file_fragments fr ON f.id = fr.fragmented_file_id
                WHERE f.id = ? 
                GROUP BY f.id, f.total_chunks, f.redundancy_level
            ");
            $stmt->execute([$fragmentedFileId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return ['success' => false, 'message' => 'Fragmented file not found'];
            }
            
            $isComplete = $result['fragment_count'] == $result['total_chunks'];
            $completionPercentage = ($result['fragment_count'] / $result['total_chunks']) * 100;
            
            return [
                'success' => true,
                'is_complete' => $isComplete,
                'total_chunks' => $result['total_chunks'],
                'uploaded_chunks' => $result['fragment_count'],
                'completion_percentage' => round($completionPercentage, 2),
                'expected_redundancy' => $result['redundancy_level']
            ];
            
        } catch (PDOException $e) {
            error_log("FileFragment integrity check error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to verify integrity'];
        }
    }
}
?> 