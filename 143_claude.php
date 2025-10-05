<?php
// /classes/ImageProcessor.php
class ImageProcessor {
    private $uploadDir;
    private $thumbnailDir;
    private $maxFileSize;
    private $allowedTypes;
    
    public function __construct($uploadDir = 'uploads/', $thumbnailDir = 'thumbnails/') {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->thumbnailDir = rtrim($thumbnailDir, '/') . '/';
        $this->maxFileSize = 5 * 1024 * 1024;
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
    }
    
    public function validateImage($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'Invalid file upload'];
        }
        
        if ($file['size'] > $this->maxFileSize) {
            return ['valid' => false, 'error' => 'File too large'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            return ['valid' => false, 'error' => 'Invalid image file'];
        }
        
        return ['valid' => true, 'mime_type' => $mimeType, 'image_info' => $imageInfo];
    }
    
    public function processImage($file, $userId) {
        $validation = $this->validateImage($file);
        if (!$validation['valid']) {
            return ['success' => false, 'error' => $validation['error']];
        }
        
        $extension = $this->getExtensionFromMime($validation['mime_type']);
        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
        $thumbnailFilename = 'thumb_' . $filename;
        
        $uploadPath = $this->uploadDir . $filename;
        $thumbnailPath = $this->thumbnailDir . $thumbnailFilename;
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => false, 'error' => 'Failed to save original image'];
        }
        
        $thumbnailResult = $this->createThumbnail($uploadPath, $thumbnailPath, 150, 150);
        if (!$thumbnailResult) {
            unlink($uploadPath);
            return ['success' => false, 'error' => 'Failed to create thumbnail'];
        }
        
        return [
            'success' => true,
            'original_path' => $uploadPath,
            'thumbnail_path' => $thumbnailPath,
            'filename' => $filename,
            'thumbnail_filename' => $thumbnailFilename
        ];
    }
    
    private function createThumbnail($sourcePath, $destinationPath, $width, $height) {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        $sourceImage = $this->createImageFromFile($sourcePath, $imageInfo[2]);
        if (!$sourceImage) {
            return false;
        }
        
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        $aspectRatio = $sourceWidth / $sourceHeight;
        $thumbAspectRatio = $width / $height;
        
        if ($aspectRatio > $thumbAspectRatio) {
            $newHeight = $height;
            $newWidth = $height * $aspectRatio;
        } else {
            $newWidth = $width;
            $newHeight = $width / $aspectRatio;
        }
        
        $thumbnail = imagecreatetruecolor($width, $height);
        
        if ($imageInfo[2] == IMAGETYPE_PNG || $imageInfo[2] == IMAGETYPE_GIF) {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }
        
        $x = ($width - $newWidth) / 2;
        $y = ($height - $newHeight) / 2;
        
        imagecopyresampled($thumbnail, $sourceImage, $x, $y, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
        
        $result = $this->saveImage($thumbnail, $destinationPath, $imageInfo[2]);
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $result;
    }
    
    private function createImageFromFile($path, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($path);
            default:
                return false;
        }
    }
    
    private function saveImage($image, $path, $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagejpeg($image, $path, 90);
            case IMAGETYPE_PNG:
                return imagepng($image, $path, 9);
            case IMAGETYPE_GIF:
                return imagegif($image, $path);
            case IMAGETYPE_WEBP:
                return imagewebp($image, $path, 90);
            default:
                return false;
        }
    }
    
    private function getExtensionFromMime($mimeType) {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        return $extensions[$mimeType] ?? 'jpg';
    }
    
    public function deleteImage($filename, $thumbnailFilename) {
        $originalPath = $this->uploadDir . $filename;
        $thumbnailPath = $this->thumbnailDir . $thumbnailFilename;
        
        $deleted = true;
        if (file_exists($originalPath)) {
            $deleted = unlink($originalPath) && $deleted;
        }
        if (file_exists($thumbnailPath)) {
            $deleted = unlink($thumbnailPath) && $deleted;
        }
        
        return $deleted;
    }
}
?>


<?php
// /classes/Database.php
class Database {
    private $pdo;
    
    public function __construct($host = 'localhost', $dbname = 'social_media', $username = 'root', $password = '') {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public function updateUserProfileImage($userId, $filename, $thumbnailFilename) {
        $stmt = $this->pdo->prepare("UPDATE users SET profile_image = ?, profile_thumbnail = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$filename, $thumbnailFilename, $userId]);
    }
    
    public function getUserProfileImages($userId) {
        $stmt = $this->pdo->prepare("SELECT profile_image, profile_thumbnail FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    public function userExists($userId) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetchColumn() > 0;
    }
}
?>


<?php
// /handlers/ImageUploadHandler.php
require_once '../classes/ImageProcessor.php';
require_once '../classes/Database.php';

class ImageUploadHandler {
    private $imageProcessor;
    private $database;
    
    public function __construct() {
        $this->imageProcessor = new ImageProcessor('../uploads/', '../thumbnails/');
        $this->database = new Database();
    }
    
    public function handleUpload() {
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
            return;
        }
        
        if (!isset($_POST['user_id']) || !is_numeric($_POST['user_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid user ID']);
            return;
        }
        
        $userId = intval($_POST['user_id']);
        
        if (!$this->database->userExists($userId)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'User not found']);
            return;
        }
        
        if (!isset($_FILES['profile_image'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No image file provided']);
            return;
        }
        
        $oldImages = $this->database->getUserProfileImages($userId);
        
        $result = $this->imageProcessor->processImage($_FILES['profile_image'], $userId);
        
        if (!$result['success']) {
            http_response_code(400);
            echo json_encode($result);
            return;
        }
        
        $dbResult = $this->database->updateUserProfileImage($userId, $result['filename'], $result['thumbnail_filename']);
        
        if (!$dbResult) {
            $this->imageProcessor->deleteImage($result['filename'], $result['thumbnail_filename']);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update database']);
            return;
        }
        
        if ($oldImages && $oldImages['profile_image']) {
            $this->imageProcessor->deleteImage($oldImages['profile_image'], $oldImages['profile_thumbnail']);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile image updated successfully',
            'thumbnail_url' => 'thumbnails/' . $result['thumbnail_filename'],
            'image_url' => 'uploads/' . $result['filename']
        ]);
    }
}

$handler = new ImageUploadHandler();
$handler->handleUpload();
?>


<?php
// /public/upload_profile_image.php
require_once '../handlers/ImageUploadHandler.php';
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Image</title>
</head>
<body>
    <div id="upload-container">
        <h2>Upload Profile Image</h2>
        <form id="profile-upload-form" enctype="multipart/form-data">
            <input type="hidden" id="user_id" name="user_id" value="1">
            <div>
                <label for="profile_image">Choose Profile Image:</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*" required>
            </div>
            <div>
                <button type="submit">Upload Image</button>
            </div>
        </form>
        <div id="preview-container">
            <img id="image-preview" src="" alt="Image preview" style="display: none; max-width: 200px; margin-top: 10px;">
        </div>
        <div id="result-message"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('profile-upload-form');
            const fileInput = document.getElementById('profile_image');
            const preview = document.getElementById('image-preview');
            const resultMessage = document.getElementById('result-message');

            fileInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                resultMessage.textContent = 'Uploading...';
                
                fetch('upload_profile_image.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        resultMessage.textContent = data.message;
                        resultMessage.style.color = 'green';
                        if (data.thumbnail_url) {
                            preview.src = data.thumbnail_url + '?' + Date.now();
                        }
                    } else {
                        resultMessage.textContent = 'Error: ' + data.error;
                        resultMessage.style.color = 'red';
                    }
                })
                .catch(error => {
                    resultMessage.textContent = 'Upload failed: ' + error.message;
                    resultMessage.style.color = 'red';
                });
            });
        });
    </script>
</body>
</html>


sql
-- Database schema (save as schema.sql)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    profile_image VARCHAR(255),
    profile_thumbnail VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO users (username, email) VALUES ('testuser', 'test@example.com');


<?php
// /public/.htaccess
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^uploads/(.*)$ ../uploads/$1 [L]
RewriteRule ^thumbnails/(.*)$ ../thumbnails/$1 [L]
?>