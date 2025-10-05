html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_image">Select Profile Picture:</label>
        <input type="file" id="profile_image" name="profile_image" accept="image/*" required>
        <button type="submit">Upload Image</button>
    </form>
</body>
</html>


<?php
require_once 'vendor/autoload.php';

use Intervention\Image\ImageManagerStatic as Image;

class ProfileImageUploader {
    private $maxFileSize = 5242880;
    private $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $maxWidth = 2000;
    private $maxHeight = 2000;
    private $thumbnailSize = 200;
    private $uploadDir = 'uploads/';
    private $thumbnailDir = 'uploads/thumbnails/';
    private $logFile = 'logs/upload.log';
    
    public function __construct() {
        $this->ensureDirectories();
        Image::configure(['driver' => 'gd']);
    }
    
    private function ensureDirectories() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }
    
    public function handleUpload() {
        try {
            if (!isset($_FILES['profile_image'])) {
                $this->logEvent('No file uploaded', false);
                return ['success' => false, 'message' => 'No file selected'];
            }
            
            $file = $_FILES['profile_image'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->logEvent('Upload error: ' . $file['error'], false);
                return ['success' => false, 'message' => 'Upload failed'];
            }
            
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                $this->logEvent('Validation failed: ' . $validation['message'], false);
                return ['success' => false, 'message' => $validation['message']];
            }
            
            $filename = $this->generateSecureFilename($file['name']);
            $originalPath = $this->uploadDir . $filename;
            $thumbnailPath = $this->thumbnailDir . 'thumb_' . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
                $this->logEvent('Failed to move uploaded file', false);
                return ['success' => false, 'message' => 'Upload failed'];
            }
            
            $this->createThumbnail($originalPath, $thumbnailPath);
            
            $this->logEvent('File uploaded successfully: ' . $filename, true);
            return [
                'success' => true, 
                'message' => 'Upload successful',
                'original' => $originalPath,
                'thumbnail' => $thumbnailPath
            ];
            
        } catch (Exception $e) {
            $this->logEvent('Exception: ' . $e->getMessage(), false);
            return ['success' => false, 'message' => 'Upload failed'];
        }
    }
    
    private function validateFile($file) {
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'message' => 'File too large'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return ['valid' => false, 'message' => 'Invalid file type'];
        }
        
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'message' => 'Invalid image'];
        }
        
        if ($imageInfo[0] > $this->maxWidth || $imageInfo[1] > $this->maxHeight) {
            return ['valid' => false, 'message' => 'Image dimensions too large'];
        }
        
        return ['valid' => true, 'message' => 'Valid'];
    }
    
    private function generateSecureFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($extension, $allowedExtensions)) {
            $extension = 'jpg';
        }
        
        return uniqid('profile_', true) . '.' . $extension;
    }
    
    private function createThumbnail($originalPath, $thumbnailPath) {
        $image = Image::make($originalPath);
        $image->fit($this->thumbnailSize, $this->thumbnailSize, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $image->save($thumbnailPath, 85);
    }
    
    private function logEvent($message, $success) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->getClientIP();
        $status = $success ? 'SUCCESS' : 'FAILURE';
        $logEntry = "[$timestamp] [$status] IP: $ip - $message" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$uploader = new ProfileImageUploader();
$result = $uploader->handleUpload();

http_response_code($result['success'] ? 200 : 400);
echo json_encode($result);
?>


json
{
    "require": {
        "intervention/image": "^2.7"
    }
}