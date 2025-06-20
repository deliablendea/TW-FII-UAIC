<?php
session_start();
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../services/FragmentationService.php';

class FragmentationController {
    private $fragmentationService;
    
    public function __construct() {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $this->fragmentationService = new FragmentationService($pdo);
    }
    
    /**
     * Check if user is logged in
     */
    private function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Not authenticated']);
            exit;
        }
        return $_SESSION['user_id'];
    }
    
    /**
     * Upload and fragment a file
     */
    public function uploadFragmented() {
        $userId = $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }
        
        // Check if user has all required cloud service authentications
        if (!$this->fragmentationService->areAllProvidersAuthenticated($userId)) {
            $missingProviders = $this->fragmentationService->getMissingProviders($userId);
            http_response_code(403);
            echo json_encode([
                'success' => false, 
                'message' => 'Fragmentation requires authentication with all cloud services',
                'missing_providers' => $missingProviders,
                'required_providers' => ['dropbox', 'google', 'onedrive']
            ]);
            return;
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
            return;
        }
        
        $uploadedFile = $_FILES['file'];
        $originalFilename = $uploadedFile['name'];
        $tempFilePath = $uploadedFile['tmp_name'];
        
        // Get optional parameters
        $chunkSize = isset($_POST['chunk_size']) ? intval($_POST['chunk_size']) : 1048576; // Default 1MB
        $redundancyLevel = isset($_POST['redundancy_level']) ? intval($_POST['redundancy_level']) : 2;
        
        // Validate parameters
        if ($chunkSize < 64 * 1024 || $chunkSize > 10 * 1024 * 1024) { // 64KB to 10MB
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid chunk size (64KB - 10MB allowed)']);
            return;
        }
        
        if ($redundancyLevel < 1 || $redundancyLevel > 3) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid redundancy level (1-3 allowed)']);
            return;
        }
        
        // Fragment and upload the file
        $result = $this->fragmentationService->fragmentAndUpload($userId, $tempFilePath, $originalFilename, $chunkSize, $redundancyLevel);
        
        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        
        echo json_encode($result);
    }
    
    /**
     * List fragmented files
     */
    public function listFragmented() {
        $userId = $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }
        
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
        
        // Validate parameters
        if ($limit < 1 || $limit > 100) {
            $limit = 20;
        }
        if ($offset < 0) {
            $offset = 0;
        }
        
        $result = $this->fragmentationService->listFragmentedFiles($userId, $limit, $offset);
        
        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(403);
        }
        
        echo json_encode($result);
    }
    
    /**
     * Download and reconstruct a fragmented file
     */
    public function downloadFragmented() {
        $userId = $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }
        
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing fragmented file ID']);
            return;
        }
        
        $fragmentedFileId = intval($_GET['id']);
        
        // Reconstruct the file
        $result = $this->fragmentationService->reconstructAndDownload($userId, $fragmentedFileId);
        
        if ($result['success']) {
            // Get file info for proper headers
            $fileInfo = $this->fragmentationService->getFragmentedFileInfo($userId, $fragmentedFileId);
            $filename = $fileInfo['file_info']['original_filename'] ?? 'download';
            $mimeType = $result['mime_type'] ?? 'application/octet-stream';
            
            // CRITICAL: Clean ALL output buffers to prevent corruption
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Disable error reporting for clean output
            $oldErrorReporting = error_reporting(0);
            
            // Set headers for file download
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
            header('Content-Length: ' . strlen($result['content']));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Accept-Ranges: bytes');
            
            // Flush any remaining output
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();
            
            // Output the file content as binary
            echo $result['content'];
            
            // Restore error reporting
            error_reporting($oldErrorReporting);
            
            // Explicitly exit to prevent any trailing output
            exit;
        } else {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode($result);
        }
    }
    
    /**
     * Get detailed information about a fragmented file
     */
    public function getFragmentedInfo() {
        $userId = $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }
        
        if (!isset($_GET['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing fragmented file ID']);
            return;
        }
        
        $fragmentedFileId = intval($_GET['id']);
        
        $result = $this->fragmentationService->getFragmentedFileInfo($userId, $fragmentedFileId);
        
        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(404);
        }
        
        echo json_encode($result);
    }
    
    /**
     * Delete a fragmented file
     */
    public function deleteFragmented() {
        $userId = $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }
        
        // Get ID from URL or request body
        $fragmentedFileId = null;
        if (isset($_GET['id'])) {
            $fragmentedFileId = intval($_GET['id']);
        } else {
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['id'])) {
                $fragmentedFileId = intval($input['id']);
            }
        }
        
        if (!$fragmentedFileId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Missing fragmented file ID']);
            return;
        }
        
        $result = $this->fragmentationService->deleteFragmentedFile($userId, $fragmentedFileId);
        
        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        
        echo json_encode($result);
    }
    
    /**
     * Check authentication status for fragmentation
     */
    public function checkFragmentationStatus() {
        $userId = $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }
        
        $allAuthenticated = $this->fragmentationService->areAllProvidersAuthenticated($userId);
        $missingProviders = $this->fragmentationService->getMissingProviders($userId);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'fragmentation_available' => $allAuthenticated,
            'authenticated_providers' => array_values(array_diff(['dropbox', 'google', 'onedrive'], $missingProviders)),
            'missing_providers' => array_values($missingProviders),
            'required_providers' => ['dropbox', 'google', 'onedrive']
        ]);
    }
    
    /**
     * Handle the request based on action parameter
     */
    public function handleRequest() {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'upload':
                $this->uploadFragmented();
                break;
            case 'list':
                $this->listFragmented();
                break;
            case 'download':
                $this->downloadFragmented();
                break;
            case 'info':
                $this->getFragmentedInfo();
                break;
            case 'delete':
                $this->deleteFragmented();
                break;
            case 'status':
                $this->checkFragmentationStatus();
                break;
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
        }
    }
}

// Handle the request if this file is accessed directly
if ($_SERVER['SCRIPT_NAME'] === '/controllers/FragmentationController.php' || 
    basename($_SERVER['SCRIPT_NAME']) === 'FragmentationController.php') {
    $controller = new FragmentationController();
    $controller->handleRequest();
}
?> 