<?php
require_once __DIR__ . '/../models/FragmentedFile.php';
require_once __DIR__ . '/../models/FileFragment.php';
require_once __DIR__ . '/../models/OAuthToken.php';
require_once __DIR__ . '/DropboxService.php';
require_once __DIR__ . '/GoogleDriveService.php';
require_once __DIR__ . '/OneDriveService.php';

class FragmentationService {
    private $pdo;
    private $fragmentedFileModel;
    private $fileFragmentModel;
    private $oauthTokenModel;
    private $dropboxService;
    private $googleDriveService;
    private $oneDriveService;
    
    // Supported cloud providers
    private $providers = ['dropbox', 'google', 'onedrive'];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->fragmentedFileModel = new FragmentedFile($pdo);
        $this->fileFragmentModel = new FileFragment($pdo);
        $this->oauthTokenModel = new OAuthToken($pdo);
        $this->dropboxService = new DropboxService($pdo);
        $this->googleDriveService = new GoogleDriveService($pdo, null); // userId will be set later
        $this->oneDriveService = new OneDriveService($pdo);
    }
    
    /**
     * Check if user has valid tokens for all required cloud services
     */
    public function areAllProvidersAuthenticated($userId) {
        foreach ($this->providers as $provider) {
            if (!$this->oauthTokenModel->isTokenValid($userId, $provider)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get list of missing providers for a user
     */
    public function getMissingProviders($userId) {
        $missing = [];
        foreach ($this->providers as $provider) {
            if (!$this->oauthTokenModel->isTokenValid($userId, $provider)) {
                $missing[] = $provider;
            }
        }
        return $missing;
    }
    
    /**
     * Fragment and upload a file to cloud services with redundancy
     */
    public function fragmentAndUpload($userId, $filePath, $originalFilename, $chunkSize = 1048576, $redundancyLevel = 2) {
        // Check if all providers are authenticated
        if (!$this->areAllProvidersAuthenticated($userId)) {
            return [
                'success' => false, 
                'message' => 'Not all cloud services are authenticated. Required: ' . implode(', ', $this->getMissingProviders($userId))
            ];
        }
        
        // Validate file
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'File not found'];
        }
        
        $fileSize = filesize($filePath);
        $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';
        $fileHash = hash_file('sha256', $filePath);
        
        // Check if file already exists
        $existingFile = $this->fragmentedFileModel->getByUserAndHash($userId, $fileHash);
        if ($existingFile) {
            return [
                'success' => false, 
                'message' => 'File already exists in fragmented storage',
                'fragmented_file_id' => $existingFile['id']
            ];
        }
        
        // Create fragmented file record
        $createResult = $this->fragmentedFileModel->create($userId, $originalFilename, $fileSize, $mimeType, $fileHash, $chunkSize, $redundancyLevel);
        if (!$createResult['success']) {
            return $createResult;
        }
        
        $fragmentedFileId = $createResult['fragmented_file_id'];
        $totalChunks = $createResult['total_chunks'];
        
        try {
            // Fragment the file
            $file = fopen($filePath, 'rb');
            if (!$file) {
                throw new Exception('Cannot open file for reading');
            }
            
            $chunkIndex = 0;
            $uploadedChunks = 0;
            $failedChunks = [];
            
            while (!feof($file)) {
                $chunkData = fread($file, $chunkSize);
                if ($chunkData === false) {
                    throw new Exception('Failed to read chunk');
                }
                
                $actualChunkSize = strlen($chunkData);
                $chunkHash = hash('sha256', $chunkData);
                
                // Upload chunk to cloud services with redundancy
                $uploadResult = $this->uploadChunkWithRedundancy($userId, $chunkData, $chunkIndex, $fragmentedFileId, $redundancyLevel);
                
                if ($uploadResult['success']) {
                    // Save fragment metadata
                    $this->fileFragmentModel->create(
                        $fragmentedFileId, 
                        $chunkIndex, 
                        $chunkHash, 
                        $actualChunkSize, 
                        $uploadResult['storage_locations']
                    );
                    $uploadedChunks++;
                } else {
                    $failedChunks[] = $chunkIndex;
                    error_log("Failed to upload chunk $chunkIndex: " . $uploadResult['message']);
                }
                
                $chunkIndex++;
            }
            
            fclose($file);
            
            // Update status based on upload success
            if (count($failedChunks) === 0) {
                $this->fragmentedFileModel->updateStatus($fragmentedFileId, 'complete');
                return [
                    'success' => true,
                    'message' => 'File fragmented and uploaded successfully',
                    'fragmented_file_id' => $fragmentedFileId,
                    'total_chunks' => $totalChunks,
                    'uploaded_chunks' => $uploadedChunks
                ];
            } else {
                $this->fragmentedFileModel->updateStatus($fragmentedFileId, 'error');
                return [
                    'success' => false,
                    'message' => 'Some chunks failed to upload',
                    'fragmented_file_id' => $fragmentedFileId,
                    'failed_chunks' => $failedChunks,
                    'uploaded_chunks' => $uploadedChunks,
                    'total_chunks' => $totalChunks
                ];
            }
            
        } catch (Exception $e) {
            $this->fragmentedFileModel->updateStatus($fragmentedFileId, 'error');
            error_log("Fragmentation error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Fragmentation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Upload a single chunk to multiple cloud services for redundancy
     */
    private function uploadChunkWithRedundancy($userId, $chunkData, $chunkIndex, $fragmentedFileId, $redundancyLevel) {
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'fragment_');
        
        if (file_put_contents($tempFile, $chunkData) === false) {
            return ['success' => false, 'message' => 'Failed to create temporary file'];
        }
        
        $fileName = "fragment_{$fragmentedFileId}_{$chunkIndex}.bin";
        $targetPath = "/fragments/{$fragmentedFileId}/";
        $storageLocations = [];
        $successCount = 0;
        
        // Try to upload to all providers for maximum redundancy
        foreach ($this->providers as $provider) {
            $uploadResult = $this->uploadToProvider($userId, $provider, $tempFile, $fileName, $targetPath);
            
            if ($uploadResult['success']) {
                $storageLocations[] = [
                    'provider' => $provider,
                    'file_id' => $uploadResult['file_id'],
                    'path' => $uploadResult['path'] ?? $targetPath . $fileName,
                    'uploaded_at' => date('Y-m-d H:i:s')
                ];
                $successCount++;
            }
        }
        
        // Clean up temporary file
        unlink($tempFile);
        
        // Check if we met the minimum redundancy requirement
        if ($successCount >= $redundancyLevel) {
            return [
                'success' => true,
                'storage_locations' => $storageLocations,
                'redundancy_achieved' => $successCount
            ];
        } else {
            return [
                'success' => false,
                'message' => "Failed to achieve required redundancy level ($successCount/$redundancyLevel)",
                'storage_locations' => $storageLocations
            ];
        }
    }
    
    /**
     * Upload a file to a specific cloud provider
     */
    private function uploadToProvider($userId, $provider, $filePath, $fileName, $targetPath) {
        try {
            switch ($provider) {
                case 'dropbox':
                    return $this->dropboxService->uploadFile($userId, $filePath, $fileName, $targetPath);
                    
                case 'google':
                    // Set userId for Google Drive service
                    $this->googleDriveService = new GoogleDriveService($this->pdo, $userId);
                    $result = $this->googleDriveService->uploadFile($filePath, $fileName);
                    if ($result['success']) {
                        $result['path'] = $targetPath . $fileName;
                    }
                    return $result;
                    
                case 'onedrive':
                    return $this->oneDriveService->uploadFile($userId, $filePath, $fileName, $targetPath);
                    
                default:
                    return ['success' => false, 'message' => 'Unknown provider'];
            }
        } catch (Exception $e) {
            error_log("Upload to $provider failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Reconstruct and download a fragmented file
     */
    public function reconstructAndDownload($userId, $fragmentedFileId, $outputPath = null) {
        // Check if all providers are authenticated
        if (!$this->areAllProvidersAuthenticated($userId)) {
            return [
                'success' => false, 
                'message' => 'Not all cloud services are authenticated'
            ];
        }
        
        // Get fragmented file info
        $fragmentedFile = $this->fragmentedFileModel->getById($fragmentedFileId);
        if (!$fragmentedFile || $fragmentedFile['user_id'] != $userId) {
            return ['success' => false, 'message' => 'Fragmented file not found'];
        }
        
        // Get all fragments
        $fragments = $this->fileFragmentModel->getByFragmentedFileId($fragmentedFileId);
        if (empty($fragments)) {
            return ['success' => false, 'message' => 'No fragments found'];
        }
        
        // Check if all chunks are available
        if (count($fragments) < $fragmentedFile['total_chunks']) {
            return [
                'success' => false, 
                'message' => 'File is incomplete. Missing chunks.',
                'available_chunks' => count($fragments),
                'total_chunks' => $fragmentedFile['total_chunks']
            ];
        }
        
        // Sort fragments by chunk index
        usort($fragments, function($a, $b) {
            return $a['chunk_index'] - $b['chunk_index'];
        });
        
        // Reconstruct file
        $tempDir = sys_get_temp_dir();
        $tempFile = tempnam($tempDir, 'reconstructed_');
        
        try {
            $reconstructedFile = fopen($tempFile, 'wb');
            if (!$reconstructedFile) {
                throw new Exception('Cannot create temporary file for reconstruction');
            }
            
            $totalBytesWritten = 0;
            
            foreach ($fragments as $fragment) {
                $chunkData = $this->downloadChunk($userId, $fragment);
                if ($chunkData === false) {
                    throw new Exception("Failed to download chunk {$fragment['chunk_index']}");
                }
                
                // Debug: Log chunk info
                error_log("Chunk {$fragment['chunk_index']}: expected size {$fragment['chunk_size']}, actual size " . strlen($chunkData));
                
                // Verify chunk integrity
                $actualHash = hash('sha256', $chunkData);
                if ($actualHash !== $fragment['chunk_hash']) {
                    error_log("Chunk {$fragment['chunk_index']} integrity failed. Expected: {$fragment['chunk_hash']}, Got: $actualHash");
                    throw new Exception("Chunk {$fragment['chunk_index']} integrity check failed");
                }
                
                $bytesWritten = fwrite($reconstructedFile, $chunkData);
                if ($bytesWritten === false) {
                    throw new Exception("Failed to write chunk {$fragment['chunk_index']}");
                }
                
                if ($bytesWritten !== strlen($chunkData)) {
                    throw new Exception("Incomplete write for chunk {$fragment['chunk_index']}: wrote $bytesWritten of " . strlen($chunkData) . " bytes");
                }
                
                $totalBytesWritten += $bytesWritten;
            }
            
            fclose($reconstructedFile);
            
            // Verify final file integrity
            $reconstructedHash = hash_file('sha256', $tempFile);
            if ($reconstructedHash !== $fragmentedFile['file_hash']) {
                unlink($tempFile);
                return ['success' => false, 'message' => 'Reconstructed file integrity check failed'];
            }
            
            // Move to final location or return content
            if ($outputPath) {
                if (rename($tempFile, $outputPath)) {
                    return [
                        'success' => true,
                        'message' => 'File reconstructed successfully',
                        'output_path' => $outputPath,
                        'file_size' => $totalBytesWritten
                    ];
                } else {
                    unlink($tempFile);
                    return ['success' => false, 'message' => 'Failed to move reconstructed file'];
                }
            } else {
                $content = file_get_contents($tempFile);
                unlink($tempFile);
                return [
                    'success' => true,
                    'message' => 'File reconstructed successfully',
                    'content' => $content,
                    'file_size' => $totalBytesWritten,
                    'mime_type' => $fragmentedFile['mime_type']
                ];
            }
            
        } catch (Exception $e) {
            if (isset($reconstructedFile)) {
                fclose($reconstructedFile);
            }
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            error_log("Reconstruction error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Reconstruction failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Download a single chunk from cloud services
     */
    private function downloadChunk($userId, $fragment) {
        $storageLocations = $fragment['storage_locations'];
        
        // Try each storage location until one succeeds
        foreach ($storageLocations as $location) {
            try {
                $downloadResult = $this->downloadFromProvider($userId, $location['provider'], $location);
                if ($downloadResult['success']) {
                    return $downloadResult['content'];
                }
            } catch (Exception $e) {
                error_log("Failed to download from {$location['provider']}: " . $e->getMessage());
                continue;
            }
        }
        
        return false;
    }
    
    /**
     * Download a file from a specific cloud provider
     */
    private function downloadFromProvider($userId, $provider, $location) {
        try {
            switch ($provider) {
                case 'dropbox':
                    return $this->dropboxService->downloadFile($userId, $location['path']);
                    
                case 'google':
                    $this->googleDriveService = new GoogleDriveService($this->pdo, $userId);
                    return $this->googleDriveService->downloadFile($location['file_id']);
                    
                case 'onedrive':
                    return $this->oneDriveService->downloadFile($userId, $location['file_id']);
                    
                default:
                    return ['success' => false, 'message' => 'Unknown provider'];
            }
        } catch (Exception $e) {
            error_log("Download from $provider failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Download failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * List fragmented files for a user
     */
    public function listFragmentedFiles($userId, $limit = 20, $offset = 0) {
        if (!$this->areAllProvidersAuthenticated($userId)) {
            return [
                'success' => false, 
                'message' => 'Not all cloud services are authenticated'
            ];
        }
        
        return [
            'success' => true,
            'files' => $this->fragmentedFileModel->listByUser($userId, $limit, $offset)
        ];
    }
    
    /**
     * Delete a fragmented file and all its chunks
     */
    public function deleteFragmentedFile($userId, $fragmentedFileId) {
        if (!$this->areAllProvidersAuthenticated($userId)) {
            return [
                'success' => false, 
                'message' => 'Not all cloud services are authenticated'
            ];
        }
        
        // Get fragmented file info
        $fragmentedFile = $this->fragmentedFileModel->getById($fragmentedFileId);
        if (!$fragmentedFile || $fragmentedFile['user_id'] != $userId) {
            return ['success' => false, 'message' => 'Fragmented file not found'];
        }
        
        // Get all fragments
        $fragments = $this->fileFragmentModel->getByFragmentedFileId($fragmentedFileId);
        
        // Delete chunks from cloud services
        $deletionErrors = [];
        foreach ($fragments as $fragment) {
            foreach ($fragment['storage_locations'] as $location) {
                try {
                    $deleteResult = $this->deleteFromProvider($userId, $location['provider'], $location);
                    if (!$deleteResult['success']) {
                        $deletionErrors[] = "Failed to delete chunk {$fragment['chunk_index']} from {$location['provider']}";
                    }
                } catch (Exception $e) {
                    $deletionErrors[] = "Error deleting chunk {$fragment['chunk_index']} from {$location['provider']}: " . $e->getMessage();
                }
            }
        }
        
        // Delete from database
        $dbDeleteResult = $this->fragmentedFileModel->delete($fragmentedFileId);
        if (!$dbDeleteResult['success']) {
            return $dbDeleteResult;
        }
        
        if (empty($deletionErrors)) {
            return ['success' => true, 'message' => 'Fragmented file deleted successfully'];
        } else {
            return [
                'success' => true, 
                'message' => 'Fragmented file deleted with some errors',
                'errors' => $deletionErrors
            ];
        }
    }
    
    /**
     * Delete a file from a specific cloud provider
     */
    private function deleteFromProvider($userId, $provider, $location) {
        try {
            switch ($provider) {
                case 'dropbox':
                    return $this->dropboxService->deleteFile($userId, $location['path']);
                    
                case 'google':
                    $this->googleDriveService = new GoogleDriveService($this->pdo, $userId);
                    return $this->googleDriveService->deleteFile($location['file_id']);
                    
                case 'onedrive':
                    return $this->oneDriveService->deleteFile($userId, $location['file_id']);
                    
                default:
                    return ['success' => false, 'message' => 'Unknown provider'];
            }
        } catch (Exception $e) {
            error_log("Delete from $provider failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get detailed information about a fragmented file
     */
    public function getFragmentedFileInfo($userId, $fragmentedFileId) {
        if (!$this->areAllProvidersAuthenticated($userId)) {
            return [
                'success' => false, 
                'message' => 'Not all cloud services are authenticated'
            ];
        }
        
        $fragmentedFile = $this->fragmentedFileModel->getById($fragmentedFileId);
        if (!$fragmentedFile || $fragmentedFile['user_id'] != $userId) {
            return ['success' => false, 'message' => 'Fragmented file not found'];
        }
        
        $progress = $this->fragmentedFileModel->getProgress($fragmentedFileId);
        $storageStats = $this->fileFragmentModel->getStorageStatistics($fragmentedFileId);
        $integrity = $this->fileFragmentModel->verifyIntegrity($fragmentedFileId);
        
        return [
            'success' => true,
            'file_info' => $fragmentedFile,
            'progress' => $progress,
            'storage_statistics' => $storageStats,
            'integrity' => $integrity
        ];
    }
}
?> 