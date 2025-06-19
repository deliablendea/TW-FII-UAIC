# File Fragmentation System

## Overview

This modular web instrument abstracts operations with large files by storing them fragmented and redundantly across multiple cloud services including Dropbox, Google Drive, and Microsoft OneDrive. The system ensures secure, efficient storage and retrieval of large files with built-in redundancy for data protection.

## Key Features

### 🗂️ **File Fragmentation**
- Split large files into configurable chunks (64KB - 10MB)
- Distributed storage across multiple cloud providers
- Configurable redundancy levels (1-3 copies per chunk)
- SHA-256 integrity verification for each chunk and complete file

### ☁️ **Multi-Cloud Support**
- **Dropbox Integration** - File uploads with folder organization
- **Google Drive Integration** - Direct API integration with Google Drive v3
- **OneDrive Integration** - Microsoft Graph API integration
- **OAuth Authentication** - Secure authentication for all providers

### 🔐 **Security & Reliability**
- **OAuth 2.0 Authentication** - Secure authentication with all cloud providers
- **Integrity Verification** - SHA-256 hashing for data integrity
- **Redundant Storage** - Multiple copies across different providers
- **Safe Reconstruction** - Automatic failover between storage locations

### 📊 **Management Features**
- **Progress Tracking** - Real-time upload progress monitoring
- **Storage Statistics** - Distribution visualization across providers
- **File Management** - List, download, and delete fragmented files
- **Status Monitoring** - Authentication status and file integrity checks

## Prerequisites

### Required Cloud Service Authentication
The fragmentation system **requires authentication with ALL three cloud services**:
- ✅ Dropbox
- ✅ Google Drive  
- ✅ OneDrive

**Note**: Fragmentation features will be disabled until all three services are authenticated.

### Database Requirements
The system requires the following database tables:

```sql
-- Run the migration file
source db/migrations/create_fragmented_files_tables.sql;
```

## Installation & Setup

### 1. Database Migration
```bash
# Apply the fragmentation tables migration
psql -d your_database -f db/migrations/create_fragmented_files_tables.sql
```

### 2. Authentication Setup
Ensure your users authenticate with all three cloud services through the existing OAuth controllers:
- `controllers/DropboxOAuthController.php`
- `controllers/GoogleOAuthController.php`
- `controllers/OneDriveOAuthController.php`

### 3. File Permissions
Ensure PHP has write permissions to the system temp directory for temporary file operations.

## Usage

### Web Interface
Access the fragmentation system through: `/views/fragmentation.php`

#### Upload Process
1. **Authentication Check** - System verifies all cloud services are connected
2. **File Selection** - Upload via drag-and-drop or file browser
3. **Configuration**:
   - **Chunk Size**: 512KB, 1MB, 2MB, or 5MB
   - **Redundancy Level**: 1-3 copies per chunk
4. **Fragmentation** - File is split and uploaded across all providers
5. **Verification** - Integrity checks ensure successful upload

#### File Management
- **List Files** - View all fragmented files with progress indicators
- **Download** - Reconstruct and download complete files
- **File Info** - Detailed statistics and storage distribution
- **Delete** - Remove files from all cloud providers

### API Endpoints

#### Check Authentication Status
```bash
GET /api/fragmentation.php?action=status
```
**Response:**
```json
{
  "success": true,
  "fragmentation_available": true,
  "authenticated_providers": ["dropbox", "google", "onedrive"],
  "missing_providers": [],
  "required_providers": ["dropbox", "google", "onedrive"]
}
```

#### Upload Fragmented File
```bash
POST /api/fragmentation.php?action=upload
Content-Type: multipart/form-data

file: <file>
chunk_size: 1048576 (optional, default 1MB)
redundancy_level: 2 (optional, default 2)
```

#### List Fragmented Files
```bash
GET /api/fragmentation.php?action=list&limit=20&offset=0
```

#### Download Fragmented File
```bash
GET /api/fragmentation.php?action=download&id=<file_id>
```

#### Get File Information
```bash
GET /api/fragmentation.php?action=info&id=<file_id>
```

#### Delete Fragmented File
```bash
DELETE /api/fragmentation.php?action=delete&id=<file_id>
```

## Architecture

### Core Components

#### Models
- **`FragmentedFile.php`** - Manages fragmented file metadata
- **`FileFragment.php`** - Handles individual chunk information

#### Services  
- **`FragmentationService.php`** - Core fragmentation logic and orchestration

#### Controllers
- **`FragmentationController.php`** - HTTP request handling and API responses

#### Views
- **`fragmentation.php`** - Web interface with drag-and-drop functionality

### Data Flow

1. **File Upload** → **Fragmentation** → **Multi-Cloud Distribution**
2. **Authentication Check** → **Chunk Creation** → **Redundant Storage**
3. **Download Request** → **Chunk Retrieval** → **File Reconstruction**
4. **Integrity Verification** at every step

### Database Schema

#### `fragmented_files` Table
- Stores file metadata, chunk information, and status
- Tracks original filename, size, hash, and redundancy settings

#### `file_fragments` Table  
- Individual chunk information and storage locations
- JSON storage of provider-specific file IDs and paths

## Configuration Options

### Chunk Sizes
- **512 KB** - Best for smaller files, faster uploads
- **1 MB** - Balanced performance (default)
- **2 MB** - Better for larger files
- **5 MB** - Maximum efficiency for very large files

### Redundancy Levels
- **Level 1** - Single copy (no redundancy)
- **Level 2** - Double redundancy (default, recommended)
- **Level 3** - Triple redundancy (maximum protection)

## Security Considerations

### Data Protection
- Files are fragmented across multiple providers
- No single provider has complete file access
- SHA-256 integrity verification prevents tampering
- OAuth 2.0 ensures secure cloud service access

### Privacy
- File chunks are stored with randomized names
- No personally identifiable information in chunk names
- Provider-specific folder organization (`/fragments/{file_id}/`)

## Error Handling

### Common Scenarios
- **Partial Upload Failures** - System continues with available chunks
- **Provider Unavailability** - Automatic failover to other providers  
- **Authentication Expiry** - Clear error messages with re-auth prompts
- **Integrity Failures** - Automatic chunk re-verification and re-download

### Recovery Options
- **Missing Chunks** - Identified and reported during integrity checks
- **Provider Failures** - Redundancy ensures data availability
- **Corruption Detection** - SHA-256 verification with re-upload capability

## Performance Considerations

### Optimization Tips
- Choose appropriate chunk sizes based on file types
- Higher redundancy increases upload time but improves reliability
- Monitor provider rate limits during bulk uploads
- Use progress tracking for user experience optimization

### Limitations
- Requires authentication with all three providers
- Upload speed depends on slowest provider
- Storage costs scale with redundancy level
- File reconstruction requires downloading all chunks

## Troubleshooting

### Authentication Issues
```bash
# Check authentication status
curl "your-domain/api/fragmentation.php?action=status"
```

### Upload Failures
- Verify all cloud services are authenticated
- Check file size and chunk size compatibility
- Monitor server logs for specific error messages
- Ensure adequate disk space for temporary files

### Download Issues  
- Verify file integrity status
- Check provider availability
- Confirm user permissions for the file

## API Integration Examples

### JavaScript/AJAX
```javascript
// Check authentication status
fetch('/api/fragmentation.php?action=status')
  .then(response => response.json())
  .then(data => console.log(data));

// Upload file
const formData = new FormData();
formData.append('file', fileInput.files[0]);
formData.append('chunk_size', '1048576');
formData.append('redundancy_level', '2');

fetch('/api/fragmentation.php?action=upload', {
  method: 'POST',
  body: formData
})
.then(response => response.json())
.then(result => console.log(result));
```

### PHP Integration
```php
require_once 'services/FragmentationService.php';

$fragmentationService = new FragmentationService($pdo);

// Check if user can use fragmentation
if ($fragmentationService->areAllProvidersAuthenticated($userId)) {
    // Fragment and upload file
    $result = $fragmentationService->fragmentAndUpload(
        $userId, 
        $filePath, 
        $originalFilename,
        1048576, // 1MB chunks
        2        // Double redundancy
    );
}
```

## Future Enhancements

### Planned Features
- **Background Processing** - Asynchronous upload processing
- **Compression** - Optional file compression before fragmentation
- **Encryption** - Client-side encryption before cloud upload
- **Additional Providers** - Support for Amazon S3, Azure Blob Storage
- **Batch Operations** - Multiple file upload and management
- **Advanced Analytics** - Usage statistics and storage optimization

### API Improvements
- **WebSocket Support** - Real-time upload progress
- **Resumable Uploads** - Continue interrupted uploads
- **Scheduled Operations** - Automated backup and cleanup
- **Provider Health Monitoring** - Automatic provider status checks

---

## Support

For technical support or feature requests, please refer to the main project documentation or create an issue in the project repository.

**Remember**: This fragmentation system requires authentication with all three cloud services (Dropbox, Google Drive, OneDrive) to function. The system is designed to provide maximum redundancy and reliability for critical file storage needs. 