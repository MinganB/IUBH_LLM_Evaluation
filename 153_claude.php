<?php
// /classes/ImageProcessor.php
<?php

class ImageProcessor {
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxFileSize = 5242880; // 5MB
    private $maxWidth = 2048;
    private $maxHeight = 2048;
    private $thumbnailWidth = 150;
    private $thumbnailHeight = 150;
    
    public function validateImage($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        if ($file['size'] > $this->maxFileSize) {
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return false;
        }
        
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            return false;
        }
        
        if ($imageInfo[0] > $this->maxWidth || $imageInfo[1] > $this->maxHeight) {
            return false;
        }
        
        return true;
    }
    
    public function sanitizeFilename($filename) {
        $pathInfo = pathinfo($filename);
        $extension = strtolower($pathInfo['extension']);
        
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            return false;
        }
        
        $basename = preg_replace('/[^a-zA-Z0-9_-]/', '', $pathInfo['filename']);
        $basename = substr($basename, 0, 50);
        
        if (empty($basename)) {
            $basename = 'image';
        }
        
        return $basename . '_' . uniqid() . '.' . $extension;
    }
    
    public function createThumbnail($sourcePath, $thumbnailPath) {
        $imageInfo = getimagesize($sourcePath);
        $sourceImage = null;
        
        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        $aspectRatio = $sourceWidth / $sourceHeight;
        
        if ($aspectRatio > 1) {
            $newWidth = $this->thumbnailWidth;
            $newHeight = $this->thumbnailWidth / $aspectRatio;
        } else {
            $newHeight = $this->thumbnailHeight;
            $newWidth = $this->thumbnailHeight * $aspectRatio;
        }
        
        $thumbnail = imagecreatetruecolor($this->thumbnailWidth, $this->thumbnailHeight);
        
        if ($imageInfo['mime'] === 'image/png' || $imageInfo['mime'] === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }
        
        $offsetX = ($this->thumbnailWidth - $newWidth) / 2;
        $offsetY = ($this->thumbnailHeight - $newHeight) / 2;
        
        imagecopyresampled(
            $thumbnail, $sourceImage,
            $offsetX, $offsetY, 0, 0,
            $newWidth, $newHeight, $sourceWidth, $sourceHeight
        );
        
        $result = false;
        switch ($imageInfo['mime']) {
            case 'image/jpeg':
                $result = imagejpeg($thumbnail, $thumbnailPath, 85);
                break;
            case 'image/png':
                $result = imagepng($thumbnail, $thumbnailPath, 6);
                break;
            case 'image/gif':
                $result = imagegif($thumbnail, $thumbnailPath);
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $result;
    }
}


<?php
// /classes/FileUploader.php
<?php

require_once 'ImageProcessor.php';

class FileUploader {
    private $uploadDir;
    private $thumbnailDir;
    private $logFile;
    private $imageProcessor;
    
    public function __construct($uploadDir, $thumbnailDir, $logFile) {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->thumbnailDir = rtrim($thumbnailDir, '/') . '/';
        $this->logFile = $logFile;
        $this->imageProcessor = new ImageProcessor();
        
        $this->ensureDirectoryExists($this->uploadDir);
        $this->ensureDirectoryExists($this->thumbnailDir);
    }
    
    private function ensureDirectoryExists($dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    private function logAttempt($message, $success = true) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $status = $success ? 'SUCCESS' : 'FAILURE';
        $logEntry = "[{$timestamp}] [{$status}] IP: {$ip} - {$message}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function upload($file) {
        try {
            if (!$this->imageProcessor->validateImage($file)) {
                $this->logAttempt('Invalid image upload attempt', false);
                return ['success' => false, 'message' => 'Invalid file'];
            }
            
            $sanitizedFilename = $this->imageProcessor->sanitizeFilename($file['name']);
            if (!$sanitizedFilename) {
                $this->logAttempt('Filename sanitization failed', false);
                return ['success' => false, 'message' => 'Invalid filename'];
            }
            
            $uploadPath = $this->uploadDir . $sanitizedFilename;
            $thumbnailPath = $this->thumbnailDir . 'thumb_' . $sanitizedFilename;
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $this->logAttempt('Failed to move uploaded file', false);
                return ['success' => false, 'message' => 'Upload failed'];
            }
            
            if (!$this->imageProcessor->createThumbnail($uploadPath, $thumbnailPath)) {
                unlink($uploadPath);
                $this->logAttempt('Thumbnail generation failed', false);
                return ['success' => false, 'message' => 'Processing failed'];
            }
            
            $this->logAttempt("File uploaded successfully: {$sanitizedFilename}");
            return [
                'success' => true,
                'message' => 'Upload successful',
                'filename' => $sanitizedFilename,
                'thumbnail' => 'thumb_' . $sanitizedFilename
            ];
            
        } catch (Exception $e) {
            $this->logAttempt('Exception during upload: ' . $e->getMessage(), false);
            return ['success' => false, 'message' => 'Upload failed'];
        }
    }
}


<?php
// /handlers/upload_handler.php
<?php

header('Content-Type: application/json');

require_once '../classes/FileUploader.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['profile_image'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$uploadDir = '../uploads/images/';
$thumbnailDir = '../uploads/thumbnails/';
$logFile = '../logs/upload.log';

$uploader = new FileUploader($uploadDir, $thumbnailDir, $logFile);
$result = $uploader->upload($_FILES['profile_image']);

echo json_encode($result);


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <div>
        <h2>Upload Profile Picture</h2>
        <form id="uploadForm" action="handlers/upload_handler.php" method="post" enctype="multipart/form-data">
            <div>
                <label for="profile_image">Choose profile picture:</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*" required>
            </div>
            <div>
                <button type="submit">Upload Image</button>
            </div>
        </form>
        <div id="result"></div>
        <div id="preview"></div>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            const previewDiv = document.getElementById('preview');
            
            resultDiv.innerHTML = 'Uploading...';
            previewDiv.innerHTML = '';
            
            fetch('handlers/upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<p>Upload successful!</p>';
                    if (data.thumbnail) {
                        previewDiv.innerHTML = '<img src="uploads/thumbnails/' + data.thumbnail + '" alt="Thumbnail">';
                    }
                } else {
                    resultDiv.innerHTML = '<p>Upload failed: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<p>Upload failed. Please try again.</p>';
            });
        });
        
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview').innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px;">';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>
</html>
?>