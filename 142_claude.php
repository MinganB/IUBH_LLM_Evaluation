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
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
    }
    
    public function validateImage($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error');
        }
        
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds limit');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('Invalid file type');
        }
        
        return true;
    }
    
    public function processImage($file, $userId) {
        $this->validateImage($file);
        
        $extension = $this->getExtensionFromMime($file['type']);
        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
        $originalPath = $this->uploadDir . $filename;
        $thumbnailPath = $this->thumbnailDir . 'thumb_' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
            throw new Exception('Failed to save original image');
        }
        
        $this->createThumbnail($originalPath, $thumbnailPath, 150, 150);
        
        return [
            'original' => $filename,
            'thumbnail' => 'thumb_' . $filename,
            'original_path' => $originalPath,
            'thumbnail_path' => $thumbnailPath
        ];
    }
    
    private function createThumbnail($sourcePath, $destPath, $width, $height) {
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
        
        $aspectRatio = $sourceWidth / $sourceHeight;
        
        if ($aspectRatio > 1) {
            $newWidth = $width;
            $newHeight = $width / $aspectRatio;
        } else {
            $newWidth = $height * $aspectRatio;
            $newHeight = $height;
        }
        
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);
        
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($thumbnail, $destPath, 85);
                break;
            case 'image/png':
                imagepng($thumbnail, $destPath);
                break;
            case 'image/gif':
                imagegif($thumbnail, $destPath);
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
    }
    
    private function getExtensionFromMime($mimeType) {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        ];
        
        return $extensions[$mimeType] ?? 'jpg';
    }
    
    public function deleteImage($filename) {
        $originalPath = $this->uploadDir . $filename;
        $thumbnailPath = $this->thumbnailDir . 'thumb_' . $filename;
        
        if (file_exists($originalPath)) {
            unlink($originalPath);
        }
        
        if (file_exists($thumbnailPath)) {
            unlink($thumbnailPath);
        }
    }
}
?>


<?php
// /classes/User.php
class User {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function updateProfilePicture($userId, $originalImage, $thumbnailImage) {
        $stmt = $this->db->prepare("UPDATE users SET profile_picture = ?, profile_thumbnail = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$originalImage, $thumbnailImage, $userId]);
    }
    
    public function getUser($userId) {
        $stmt = $this->db->prepare("SELECT id, username, profile_picture, profile_thumbnail FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getPreviousProfilePicture($userId) {
        $stmt = $this->db->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['profile_picture'] : null;
    }
}
?>


<?php
// /classes/Database.php
class Database {
    private $connection;
    private $host = 'localhost';
    private $dbname = 'social_media';
    private $username = 'root';
    private $password = '';
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname}",
                $this->username,
                $this->password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
}
?>


<?php
// /handlers/upload_profile_picture.php
session_start();

require_once '../classes/Database.php';
require_once '../classes/ImageProcessor.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    if (!isset($_FILES['profile_picture'])) {
        throw new Exception('No file uploaded');
    }
    
    $userId = $_SESSION['user_id'];
    $uploadedFile = $_FILES['profile_picture'];
    
    $database = new Database();
    $imageProcessor = new ImageProcessor('../uploads/', '../thumbnails/');
    $user = new User($database->getConnection());
    
    $previousImage = $user->getPreviousProfilePicture($userId);
    
    $result = $imageProcessor->processImage($uploadedFile, $userId);
    
    $updateSuccess = $user->updateProfilePicture($userId, $result['original'], $result['thumbnail']);
    
    if (!$updateSuccess) {
        $imageProcessor->deleteImage($result['original']);
        throw new Exception('Failed to update user profile');
    }
    
    if ($previousImage) {
        $imageProcessor->deleteImage($previousImage);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Profile picture updated successfully',
        'data' => [
            'original' => $result['original'],
            'thumbnail' => $result['thumbnail']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>


<?php
// /handlers/get_profile_picture.php
session_start();

require_once '../classes/Database.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }
    
    $userId = $_POST['user_id'] ?? $_SESSION['user_id'];
    
    $database = new Database();
    $user = new User($database->getConnection());
    
    $userData = $user->getUser($userId);
    
    if (!$userData) {
        throw new Exception('User not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_id' => $userData['id'],
            'username' => $userData['username'],
            'profile_picture' => $userData['profile_picture'],
            'profile_thumbnail' => $userData['profile_thumbnail']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Picture Upload</title>
</head>
<body>
    <div id="profile-section">
        <div id="current-profile">
            <img id="profile-image" src="" alt="Profile Picture" width="150" height="150">
            <img id="profile-thumbnail" src="" alt="Profile Thumbnail" width="50" height="50">
        </div>
        
        <form id="upload-form" enctype="multipart/form-data">
            <input type="file" id="profile-picture-input" name="profile_picture" accept="image/*" required>
            <button type="submit">Upload Profile Picture</button>
        </form>
        
        <div id="preview-section" style="display: none;">
            <h3>Preview:</h3>
            <img id="preview-image" src="" alt="Preview" width="150" height="150">
        </div>
        
        <div id="message"></div>
    </div>

    <script>
        class ProfilePictureManager {
            constructor() {
                this.uploadForm = document.getElementById('upload-form');
                this.fileInput = document.getElementById('profile-picture-input');
                this.profileImage = document.getElementById('profile-image');
                this.profileThumbnail = document.getElementById('profile-thumbnail');
                this.previewImage = document.getElementById('preview-image');
                this.previewSection = document.getElementById('preview-section');
                this.messageDiv = document.getElementById('message');
                
                this.init();
            }
            
            init() {
                this.uploadForm.addEventListener('submit', (e) => this.handleUpload(e));
                this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
                this.loadCurrentProfile();
            }
            
            async loadCurrentProfile() {
                try {
                    const formData = new FormData();
                    
                    const response = await fetch('../handlers/get_profile_picture.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success && result.data.profile_picture) {
                        this.profileImage.src = '../uploads/' + result.data.profile_picture;
                        this.profileThumbnail.src = '../thumbnails/' + result.data.profile_thumbnail;
                    } else {
                        this.profileImage.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150"><rect width="150" height="150" fill="%23ddd"/><text x="50%" y="50%" text-anchor="middle" dy=".3em">No Image</text></svg>';
                        this.profileThumbnail.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="50" height="50"><rect width="50" height="50" fill="%23ddd"/><text x="50%" y="50%" text-anchor="middle" dy=".3em" font-size="10">No Image</text></svg>';
                    }
                } catch (error) {
                    this.showMessage('Error loading profile picture', 'error');
                }
            }
            
            handleFileSelect(event) {
                const file = event.target.files[0];
                if (file) {
                    if (this.validateFile(file)) {
                        this.showPreview(file);
                    }
                }
            }
            
            validateFile(file) {
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                const maxSize = 5 * 1024 * 1024;
                
                if (!allowedTypes.includes(file.type)) {
                    this.showMessage('Please select a valid image file (JPEG, PNG, or GIF)', 'error');
                    return false;
                }
                
                if (file.size > maxSize) {
                    this.showMessage('File size must be less than 5MB', 'error');
                    return false;
                }
                
                return true;
            }
            
            showPreview(file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.previewImage.src = e.target.result;
                    this.previewSection.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
            
            async handleUpload(event) {
                event.preventDefault();
                
                const file = this.fileInput.files[0];
                if (!file) {
                    this.showMessage('Please select a file', 'error');
                    return;
                }
                
                if (!this.validateFile(file)) {
                    return;
                }
                
                const formData = new FormData();
                formData.append('profile_picture', file
?>