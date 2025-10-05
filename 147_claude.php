**index.html**
<?php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload</title>
</head>
<body>
    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="image">Select Image:</label>
        <input type="file" name="image" id="image" accept="image/*" required>
        <button type="submit">Upload Image</button>
    </form>
</body>
</html>


**upload_handler.php**
<?php
require_once 'vendor/autoload.php';

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploader
{
    private const UPLOAD_DIR = 'uploads/';
    private const THUMBNAIL_DIR = 'thumbnails/';
    private const LOG_FILE = 'upload_log.txt';
    private const MAX_FILE_SIZE = 5242880;
    private const MAX_WIDTH = 4000;
    private const MAX_HEIGHT = 4000;
    private const THUMBNAIL_WIDTH = 200;
    private const THUMBNAIL_HEIGHT = 200;
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    private $manager;
    
    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
        $this->createDirectories();
    }
    
    private function createDirectories()
    {
        if (!is_dir(self::UPLOAD_DIR)) {
            mkdir(self::UPLOAD_DIR, 0755, true);
        }
        if (!is_dir(self::THUMBNAIL_DIR)) {
            mkdir(self::THUMBNAIL_DIR, 0755, true);
        }
    }
    
    private function sanitizeFilename($filename)
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        $filename = preg_replace('/\.{2,}/', '.', $filename);
        $filename = trim($filename, '.');
        
        if (empty($filename)) {
            $filename = 'upload_' . uniqid();
        }
        
        return $filename;
    }
    
    private function validateFile($file)
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        if ($file['size'] > self::MAX_FILE_SIZE) {
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, self::ALLOWED_TYPES)) {
            return false;
        }
        
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return false;
        }
        
        if ($imageInfo[0] > self::MAX_WIDTH || $imageInfo[1] > self::MAX_HEIGHT) {
            return false;
        }
        
        return true;
    }
    
    private function logActivity($message, $success = true)
    {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $status = $success ? 'SUCCESS' : 'FAILURE';
        $logEntry = "[{$timestamp}] [{$status}] IP: {$ip} - {$message}" . PHP_EOL;
        
        file_put_contents(self::LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function processUpload()
    {
        if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            $this->logActivity('No valid file uploaded', false);
            return ['success' => false, 'message' => 'Upload failed'];
        }
        
        $file = $_FILES['image'];
        
        if (!$this->validateFile($file)) {
            $this->logActivity('File validation failed: ' . $file['name'], false);
            return ['success' => false, 'message' => 'Invalid file'];
        }
        
        $originalName = $this->sanitizeFilename($file['name']);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = pathinfo($originalName, PATHINFO_FILENAME) . '_' . uniqid() . '.' . $extension;
        
        $uploadPath = self::UPLOAD_DIR . $filename;
        $thumbnailPath = self::THUMBNAIL_DIR . $filename;
        
        try {
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $this->logActivity('Failed to move uploaded file: ' . $originalName, false);
                return ['success' => false, 'message' => 'Upload failed'];
            }
            
            $image = $this->manager->read($uploadPath);
            $thumbnail = $image->resize(self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);
            $thumbnail->save($thumbnailPath);
            
            $this->logActivity('File uploaded successfully: ' . $filename, true);
            
            return [
                'success' => true,
                'message' => 'Upload successful',
                'filename' => $filename,
                'thumbnail' => $thumbnailPath
            ];
            
        } catch (Exception $e) {
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            $this->logActivity('Processing failed: ' . $originalName . ' - Error: ' . $e->getMessage(), false);
            return ['success' => false, 'message' => 'Processing failed'];
        }
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$uploader = new ImageUploader();
$result = $uploader->processUpload();

if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result);
?>


**composer.json**
json
{
    "require": {
        "intervention/image": "^3.0"
    }
}