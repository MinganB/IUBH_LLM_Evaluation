<?php
class ImageUploadHandler {
    private $uploadDir;
    private $thumbnailDir;
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $maxFileSize = 5242880;
    private $thumbnailWidth = 150;
    private $thumbnailHeight = 150;
    
    public function __construct($uploadDir = 'uploads/profiles/', $thumbnailDir = 'uploads/thumbnails/') {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->thumbnailDir = rtrim($thumbnailDir, '/') . '/';
        $this->createDirectories();
    }
    
    private function createDirectories() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
    }
    
    public function uploadImage($file, $userId) {
        if (!$this->validateFile($file)) {
            return ['success' => false, 'error' => 'Invalid file'];
        }
        
        $fileExtension = $this->getFileExtension($file['type']);
        $fileName = $this->generateFileName($userId, $fileExtension);
        $uploadPath = $this->uploadDir . $fileName;
        $thumbnailPath = $this->thumbnailDir . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => false, 'error' => 'Failed to upload file'];
        }
        
        if (!$this->createThumbnail($uploadPath, $thumbnailPath)) {
            unlink($uploadPath);
            return ['success' => false, 'error' => 'Failed to create thumbnail'];
        }
        
        return [
            'success' => true,
            'original' => $fileName,
            'thumbnail' => $fileName,
            'original_path' => $uploadPath,
            'thumbnail_path' => $thumbnailPath
        ];
    }
    
    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
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
        
        return true;
    }
    
    private function getFileExtension($mimeType) {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        
        return $extensions[$mimeType] ?? 'jpg';
    }
    
    private function generateFileName($userId, $extension) {
        return 'profile_' . intval($userId) . '_' . time() . '.' . $extension;
    }
    
    private function createThumbnail($sourcePath, $thumbnailPath) {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        $sourceImage = $this->createImageFromFile($sourcePath, $mimeType);
        if (!$sourceImage) {
            return false;
        }
        
        $dimensions = $this->calculateThumbnailDimensions($sourceWidth, $sourceHeight);
        
        $thumbnailImage = imagecreatetruecolor($this->thumbnailWidth, $this->thumbnailHeight);
        
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
            imagefill($thumbnailImage, 0, 0, $transparent);
        }
        
        $offsetX = ($this->thumbnailWidth - $dimensions['width']) / 2;
        $offsetY = ($this->thumbnailHeight - $dimensions['height']) / 2;
        
        imagecopyresampled(
            $thumbnailImage,
            $sourceImage,
            $offsetX,
            $offsetY,
            0,
            0,
            $dimensions['width'],
            $dimensions['height'],
            $sourceWidth,
            $sourceHeight
        );
        
        $result = $this->saveImage($thumbnailImage, $thumbnailPath, $mimeType);
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
        
        return $result;
    }
    
    private function createImageFromFile($filePath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filePath);
            case 'image/png':
                return imagecreatefrompng($filePath);
            case 'image/gif':
                return imagecreatefromgif($filePath);
            case 'image/webp':
                return imagecreatefromwebp($filePath);
            default:
                return false;
        }
    }
    
    private function calculateThumbnailDimensions($sourceWidth, $sourceHeight) {
        $ratio = min($this->thumbnailWidth / $sourceWidth, $this->thumbnailHeight / $sourceHeight);
        
        return [
            'width' => intval($sourceWidth * $ratio),
            'height' => intval($sourceHeight * $ratio)
        ];
    }
    
    private function saveImage($image, $filePath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagejpeg($image, $filePath, 85);
            case 'image/png':
                return imagepng($image, $filePath, 8);
            case 'image/gif':
                return imagegif($image, $filePath);
            case 'image/webp':
                return imagewebp($image, $filePath, 85);
            default:
                return false;
        }
    }
    
    public function deleteImages($fileName) {
        $originalPath = $this->uploadDir . $fileName;
        $thumbnailPath = $this->thumbnailDir . $fileName;
        
        $deleted = true;
        if (file_exists($originalPath)) {
            $deleted = $deleted && unlink($originalPath);
        }
        if (file_exists($thumbnailPath)) {
            $deleted = $deleted && unlink($thumbnailPath);
        }
        
        return $deleted;
    }
}

class ProfileImageManager {
    private $db;
    private $imageHandler;
    
    public function __construct($database, $imageHandler) {
        $this->db = $database;
        $this->imageHandler = $imageHandler;
    }
    
    public function updateProfileImage($userId, $file) {
        $userId = intval($userId);
        
        $oldImage = $this->getCurrentProfileImage($userId);
        
        $uploadResult = $this->imageHandler->uploadImage($file, $userId);
        
        if (!$uploadResult['success']) {
            return $uploadResult;
        }
        
        $stmt = $this->db->prepare("UPDATE users SET profile_image = ?, thumbnail_image = ?, updated_at = NOW() WHERE id = ?");
        $updateSuccess = $stmt->execute([
            $uploadResult['original'],
            $uploadResult['thumbnail'],
            $userId
        ]);
        
        if (!$updateSuccess) {
            $this->imageHandler->deleteImages($uploadResult['original']);
            return ['success' => false, 'error' => 'Database update failed'];
        }
        
        if ($oldImage) {
            $this->imageHandler->deleteImages($oldImage);
        }
        
        return $uploadResult;
    }
    
    private function getCurrentProfileImage($userId) {
        $stmt = $this->db->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['profile_image'] : null;
    }
    
    public function getProfileImageUrl($userId, $thumbnail = false) {
        $stmt = $this->db->prepare("SELECT profile_image, thumbnail_image FROM users WHERE id = ?");
        $stmt->execute([intval($userId)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            return null;
        }
        
        $fileName = $thumbnail ? $result['thumbnail_image'] : $result['profile_image'];
        $directory = $thumbnail ? 'uploads/thumbnails/' : 'uploads/profiles/';
        
        return $fileName ? $directory . $fileName : null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=social_media;charset=utf8mb4", $dbUsername, $dbPassword, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        $imageHandler = new ImageUploadHandler();
        $profileManager = new ProfileImageManager($pdo, $imageHandler);
        
        if ($_POST['action'] === 'upload_profile_image') {
            if (!isset($_FILES['profile_image'])) {
                echo json_encode(['success' => false, 'error' => 'No file uploaded']);
                exit;
            }
            
            $result = $profileManager->updateProfileImage($_SESSION['user_id'], $_FILES['profile_image']);
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        error_log($e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
    
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Image Upload</title>
</head>
<body>
    <div class="profile-upload-container">
        <h2>Upload Profile Picture</h2>
        
        <form id="profileImageForm" enctype="multipart/form-data">
            <div class="file-input-container">
                <input type="file" id="profileImage" name="profile_image" accept="image/jpeg,image/png,image/gif,image/webp" required>
                <label for="profileImage">Choose Profile Picture</label>
            </div>
            
            <div id="imagePreview"></div>
            
            <button type="submit" id="uploadBtn">Upload Image</button>
            <div id="uploadProgress"></div>
            <div id="uploadMessage"></div>
        </form>
        
        <div id="currentProfileImage">
            <?php
            if (isset($_SESSION['user_id'])) {
                $pdo = new PDO("mysql:host=localhost;dbname=social_media;charset=utf8mb4", $dbUsername, $dbPassword);
                $imageHandler = new ImageUploadHandler();
                $profileManager = new ProfileImageManager($pdo, $imageHandler);
                $thumbnailUrl = $profileManager->getProfileImageUrl($_SESSION['user_id'], true);
                
                if ($thumbnailUrl) {
                    echo '<img src="' . htmlspecialchars($thumbnailUrl) . '" alt="Current Profile Picture" width="150" height="150">';
                }
            }
            ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('profileImageForm');
        const fileInput = document.getElementById('profileImage');
        const preview = document.getElementById('imagePreview');
        const uploadBtn = document.getElementById('uploadBtn');
        const progress = document.getElementById('uploadProgress');
        const message = document.getElementById('uploadMessage');
        
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                if (file.size > 5242880) {
                    showMessage('File size must be less than 5MB', 'error');
                    this.value = '';
                    return;
                }
                
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!validTypes.includes(file.type)) {
                    showMessage('Please select a valid image file (JPEG, PNG, GIF, or WebP)', 'error');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="max-width: 200px; max-height: 200px;">';
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const file = fileInput.files[0];
            if (!file) {
                showMessage('Please select an image file', 'error');
                return;
            }
            
            const formData = new FormData();
            formData.append('profile_image', file);
            formData.append('action', 'upload_profile_image');
            
            uploadBtn.disabled = true;
            progress.innerHTML = 'Uploading...';
            message.innerHTML = '';
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Profile image updated successfully!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showMessage('Error: ' + data.error, 'error');
                }
            })
            .catch(error => {
                showMessage('Upload failed: ' + error.message, 'error');
            })
            .finally(() => {
                uploadBtn.disabled = false;
                progress.innerHTML = '';
            });
        });
        
        function showMessage(text, type) {
            message.innerHTML = text;
            message.className = type;
            setTimeout(() => {
                message.innerHTML = '';
                message.className = '';
            }, 5000);
        }
    });
    </script>
</body>
</html>
?>