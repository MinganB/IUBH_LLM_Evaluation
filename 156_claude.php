<?php
<!DOCTYPE html>
<html>
<head>
    <title>Image Upload</title>
</head>
<body>
    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="image" accept="image/*" required>
        <button type="submit">Upload Image</button>
    </form>
</body>
</html>


<?php
require_once 'vendor/autoload.php';

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageUploadHandler {
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxFileSize = 5242880;
    private $maxWidth = 2000;
    private $maxHeight = 2000;
    private $thumbnailDir = 'thumbnails/';
    private $logFile = 'logs/upload.log';
    
    public function __construct() {
        if (!file_exists($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }
    
    public function handleUpload() {
        try {
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed');
            }
            
            $file = $_FILES['image'];
            
            $this->validateFile($file);
            
            $manager = new ImageManager(new Driver());
            $image = $manager->read($file['tmp_name']);
            
            $this->validateImageDimensions($image->width(), $image->height());
            
            $thumbnail = $image->scale(width: 200, height: 200);
            
            $filename = $this->generateUniqueFilename($file['name']);
            $thumbnailPath = $this->thumbnailDir . $filename;
            
            $thumbnail->save($thumbnailPath);
            
            $this->logUpload(true, $filename);
            
            return [
                'success' => true,
                'thumbnail_url' => $thumbnailPath
            ];
            
        } catch (Exception $e) {
            $this->logUpload(false, '', $e->getMessage());
            return [
                'success' => false,
                'error' => 'Upload failed'
            ];
        }
    }
    
    private function validateFile($file) {
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds limit');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('Invalid file type');
        }
    }
    
    private function validateImageDimensions($width, $height) {
        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            throw new Exception('Image dimensions exceed limits');
        }
    }
    
    private function generateUniqueFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($extension, $allowedExtensions)) {
            $extension = 'jpg';
        }
        
        return uniqid('thumb_', true) . '.' . $extension;
    }
    
    private function logUpload($success, $filename = '', $error = '') {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $status = $success ? 'SUCCESS' : 'FAILED';
        $message = $success ? "File: $filename" : "Error: $error";
        
        $logEntry = "[$timestamp] IP: $ip - $status - $message" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$handler = new ImageUploadHandler();
$result = $handler->handleUpload();

if ($result['success']) {
    http_response_code(200);
} else {
    http_response_code(400);
}

echo json_encode($result);
?>