<?php
// /public/upload_form.html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <form action="/handlers/upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_image">Select Profile Picture:</label>
        <input type="file" name="profile_image" id="profile_image" accept="image/jpeg,image/png,image/gif" required>
        <input type="submit" value="Upload Image" name="submit">
    </form>
</body>
</html>


<?php
// /classes/ImageProcessor.php
class ImageProcessor {
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxFileSize = 5242880; // 5MB
    private $thumbnailSize = 150;
    
    public function validateImage($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }
        
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds maximum allowed size');
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('Invalid file type');
        }
        
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Invalid image file');
        }
        
        return true;
    }
    
    public function createThumbnail($sourcePath, $destinationPath, $width = null, $height = null) {
        $width = $width ?: $this->thumbnailSize;
        $height = $height ?: $this->thumbnailSize;
        
        $imageInfo = getimagesize($sourcePath);
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        switch ($mimeType) {
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
                throw new Exception('Unsupported image type');
        }
        
        if (!$sourceImage) {
            throw new Exception('Failed to create image resource');
        }
        
        $aspectRatio = $sourceWidth / $sourceHeight;
        
        if ($aspectRatio > 1) {
            $newWidth = $width;
            $newHeight = $width / $aspectRatio;
        } else {
            $newHeight = $height;
            $newWidth = $height * $aspectRatio;
        }
        
        $thumbnailImage = imagecreatetruecolor($width, $height);
        
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbnailImage, 0, 0, $width, $height, $transparent);
        }
        
        $offsetX = ($width - $newWidth) / 2;
        $offsetY = ($height - $newHeight) / 2;
        
        imagecopyresampled(
            $thumbnailImage,
            $sourceImage,
            $offsetX,
            $offsetY,
            0,
            0,
            $newWidth,
            $newHeight,
            $sourceWidth,
            $sourceHeight
        );
        
        switch ($mimeType) {
            case 'image/jpeg':
                $result = imagejpeg($thumbnailImage, $destinationPath, 85);
                break;
            case 'image/png':
                $result = imagepng($thumbnailImage, $destinationPath, 6);
                break;
            case 'image/gif':
                $result = imagegif($thumbnailImage, $destinationPath);
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
        
        if (!$result) {
            throw new Exception('Failed to save thumbnail');
        }
        
        return true;
    }
    
    public function generateUniqueFilename($originalFilename) {
        $pathInfo = pathinfo($originalFilename);
        $extension = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : 'jpg';
        return uniqid('img_', true) . '.' . $extension;
    }
}


<?php
// /classes/FileUploadManager.php
class FileUploadManager {
    private $uploadDir;
    private $thumbnailDir;
    private $imageProcessor;
    
    public function __construct($uploadDir = '../uploads/', $thumbnailDir = '../uploads/thumbnails/') {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->thumbnailDir = rtrim($thumbnailDir, '/') . '/';
        $this->imageProcessor = new ImageProcessor();
        $this->createDirectories();
    }
    
    private function createDirectories() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
        
        $htaccessContent = "Options -Indexes\n<Files *.php>\nOrder allow,deny\nDeny from all\n</Files>";
        
        if (!file_exists($this->uploadDir . '.htaccess')) {
            file_put_contents($this->uploadDir . '.htaccess', $htaccessContent);
        }
        
        if (!file_exists($this->thumbnailDir . '.htaccess')) {
            file_put_contents($this->thumbnailDir . '.htaccess', $htaccessContent);
        }
    }
    
    public function uploadImage($file) {
        $this->imageProcessor->validateImage($file);
        
        $uniqueFilename = $this->imageProcessor->generateUniqueFilename($file['name']);
        $uploadPath = $this->uploadDir . $uniqueFilename;
        $thumbnailPath = $this->thumbnailDir . $uniqueFilename;
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to move uploaded file');
        }
        
        try {
            $this->imageProcessor->createThumbnail($uploadPath, $thumbnailPath);
        } catch (Exception $e) {
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            throw $e;
        }
        
        return [
            'original' => $uploadPath,
            'thumbnail' => $thumbnailPath,
            'filename' => $uniqueFilename
        ];
    }
}


<?php
// /handlers/upload_handler.php
error_reporting(0);
ini_set('display_errors', 0);

require_once '../classes/ImageProcessor.php';
require_once '../classes/FileUploadManager.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['submit']) || !isset($_FILES['profile_image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

try {
    $fileUploadManager = new FileUploadManager();
    $result = $fileUploadManager->uploadImage($_FILES['profile_image']);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded successfully',
        'data' => [
            'filename' => $result['filename'],
            'original_path' => basename($result['original']),
            'thumbnail_path' => basename($result['thumbnail'])
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}


<?php
// /public/upload_profile.php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <h1>Upload Profile Picture</h1>
    
    <form id="uploadForm" action="/handlers/upload_handler.php" method="POST" enctype="multipart/form-data">
        <div>
            <label for="profile_image">Select Profile Picture:</label>
            <input type="file" name="profile_image" id="profile_image" accept="image/jpeg,image/png,image/gif" required>
        </div>
        <div>
            <input type="submit" value="Upload Image" name="submit">
        </div>
    </form>
    
    <div id="result"></div>
    
    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            
            fetch('/handlers/upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<p>Upload successful! Thumbnail created.</p>';
                } else {
                    resultDiv.innerHTML = '<p>Error: ' + data.error + '</p>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<p>Upload failed. Please try again.</p>';
            });
        });
    </script>
</body>
</html>
?>