<?php
class ImageHandler {
    private $uploadDir;
    private $thumbDir;
    private $maxFileSize;
    private $allowedTypes;
    
    public function __construct($uploadDir = 'uploads/', $thumbDir = 'thumbnails/') {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->thumbDir = rtrim($thumbDir, '/') . '/';
        $this->maxFileSize = 5 * 1024 * 1024;
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        
        $this->createDirectories();
    }
    
    private function createDirectories() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbDir)) {
            mkdir($this->thumbDir, 0755, true);
        }
    }
    
    public function uploadImage($file) {
        if (!$this->validateFile($file)) {
            return false;
        }
        
        $filename = $this->generateFileName($file['name']);
        $uploadPath = $this->uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $this->generateThumbnail($uploadPath, $filename);
            return $filename;
        }
        
        return false;
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
        
        return in_array($mimeType, $this->allowedTypes);
    }
    
    private function generateFileName($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid() . '_' . time() . '.' . strtolower($extension);
    }
    
    private function generateThumbnail($sourcePath, $filename) {
        $imageInfo = getimagesize($sourcePath);
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        $thumbWidth = 150;
        $thumbHeight = 150;
        
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
                return false;
        }
        
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        if ($mimeType === 'image/png') {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
        }
        
        imagecopyresampled(
            $thumbImage, $sourceImage,
            0, 0, 0, 0,
            $thumbWidth, $thumbHeight,
            $sourceWidth, $sourceHeight
        );
        
        $thumbPath = $this->thumbDir . $filename;
        
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($thumbImage, $thumbPath, 90);
                break;
            case 'image/png':
                imagepng($thumbImage, $thumbPath, 9);
                break;
            case 'image/gif':
                imagegif($thumbImage, $thumbPath);
                break;
        }
        
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);
        
        return true;
    }
    
    public function deleteImage($filename) {
        $uploadPath = $this->uploadDir . $filename;
        $thumbPath = $this->thumbDir . $filename;
        
        if (file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }
        
        return true;
    }
    
    public function getImageUrl($filename) {
        return $this->uploadDir . $filename;
    }
    
    public function getThumbnailUrl($filename) {
        return $this->thumbDir . $filename;
    }
}

session_start();

$imageHandler = new ImageHandler();
$message = '';
$uploadedImage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $result = $imageHandler->uploadImage($_FILES['profile_image']);
    
    if ($result) {
        $_SESSION['profile_image'] = $result;
        $uploadedImage = $result;
        $message = 'Image uploaded successfully!';
    } else {
        $message = 'Error uploading image. Please check file size and format.';
    }
}

if (isset($_POST['delete_image']) && isset($_SESSION['profile_image'])) {
    $imageHandler->deleteImage($_SESSION['profile_image']);
    unset($_SESSION['profile_image']);
    $message = 'Image deleted successfully!';
    $uploadedImage = '';
}

if (isset($_SESSION['profile_image'])) {
    $uploadedImage = $_SESSION['profile_image'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Picture Upload</title>
</head>
<body>
    <div>
        <h1>Upload Profile Picture</h1>
        
        <?php if ($message): ?>
            <div><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div>
                <label for="profile_image">Choose Profile Picture:</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*" required>
            </div>
            <div>
                <button type="submit">Upload Image</button>
            </div>
        </form>
        
        <?php if ($uploadedImage): ?>
            <div>
                <h2>Current Profile Picture</h2>
                <div>
                    <img src="<?php echo htmlspecialchars($imageHandler->getThumbnailUrl($uploadedImage)); ?>" alt="Profile Thumbnail">
                </div>
                <div>
                    <a href="<?php echo htmlspecialchars($imageHandler->getImageUrl($uploadedImage)); ?>" target="_blank">View Full Size</a>
                </div>
                <form method="POST">
                    <button type="submit" name="delete_image" onclick="return confirm('Are you sure you want to delete this image?')">Delete Image</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const maxSize = 5 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('File size must be less than 5MB');
                    e.target.value = '';
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPEG, PNG, and GIF files are allowed');
                    e.target.value = '';
                    return;
                }
            }
        });
    </script>
</body>
</html>
?>