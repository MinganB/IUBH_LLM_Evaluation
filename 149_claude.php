<?php
// upload_form.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_image">Choose Profile Picture:</label>
        <input type="file" name="profile_image" id="profile_image" accept="image/*" required>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generate_csrf_token()); ?>">
        <button type="submit">Upload Image</button>
    </form>
    
    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] === 'success'): ?>
            <p>Profile picture uploaded successfully!</p>
        <?php elseif ($_GET['status'] === 'error'): ?>
            <p>Error uploading profile picture. Please try again.</p>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>

<?php
function generate_csrf_token() {
    if (!isset($_SESSION)) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
?>


<?php
// upload_handler.php
session_start();

class ImageUploadHandler {
    private $upload_dir = 'uploads/';
    private $thumbnail_dir = 'thumbnails/';
    private $max_file_size = 5242880; // 5MB
    private $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $thumbnail_width = 150;
    private $thumbnail_height = 150;
    
    public function __construct() {
        $this->createDirectories();
    }
    
    private function createDirectories() {
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
        if (!file_exists($this->thumbnail_dir)) {
            mkdir($this->thumbnail_dir, 0755, true);
        }
    }
    
    public function handleUpload() {
        try {
            if (!$this->validateRequest()) {
                throw new Exception('Invalid request');
            }
            
            if (!$this->validateCSRF()) {
                throw new Exception('CSRF token validation failed');
            }
            
            $file = $_FILES['profile_image'];
            
            if (!$this->validateFile($file)) {
                throw new Exception('Invalid file');
            }
            
            $filename = $this->generateFilename($file['name']);
            $upload_path = $this->upload_dir . $filename;
            
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            $thumbnail_path = $this->thumbnail_dir . 'thumb_' . $filename;
            
            if (!$this->generateThumbnail($upload_path, $thumbnail_path)) {
                unlink($upload_path);
                throw new Exception('Failed to generate thumbnail');
            }
            
            $this->cleanupOldFiles();
            
            header('Location: upload_form.php?status=success');
            exit;
            
        } catch (Exception $e) {
            error_log('Image upload error: ' . $e->getMessage());
            header('Location: upload_form.php?status=error');
            exit;
        }
    }
    
    private function validateRequest() {
        return $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image']);
    }
    
    private function validateCSRF() {
        return isset($_POST['csrf_token']) && 
               isset($_SESSION['csrf_token']) && 
               hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    }
    
    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        if ($file['size'] > $this->max_file_size) {
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $this->allowed_types)) {
            return false;
        }
        
        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            return false;
        }
        
        return true;
    }
    
    private function generateFilename($original_name) {
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        return uniqid('profile_', true) . '.' . $extension;
    }
    
    private function generateThumbnail($source_path, $thumbnail_path) {
        $image_info = getimagesize($source_path);
        $mime_type = $image_info['mime'];
        
        switch ($mime_type) {
            case 'image/jpeg':
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source_image = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $source_image = imagecreatefromgif($source_path);
                break;
            case 'image/webp':
                $source_image = imagecreatefromwebp($source_path);
                break;
            default:
                return false;
        }
        
        if (!$source_image) {
            return false;
        }
        
        $source_width = imagesx($source_image);
        $source_height = imagesy($source_image);
        
        $aspect_ratio = $source_width / $source_height;
        
        if ($aspect_ratio > 1) {
            $new_width = $this->thumbnail_width;
            $new_height = $this->thumbnail_width / $aspect_ratio;
        } else {
            $new_height = $this->thumbnail_height;
            $new_width = $this->thumbnail_height * $aspect_ratio;
        }
        
        $thumbnail_image = imagecreatetruecolor($this->thumbnail_width, $this->thumbnail_height);
        
        if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
            imagealphablending($thumbnail_image, false);
            imagesavealpha($thumbnail_image, true);
            $transparent = imagecolorallocatealpha($thumbnail_image, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail_image, 0, 0, $this->thumbnail_width, $this->thumbnail_height, $transparent);
        }
        
        $x_offset = ($this->thumbnail_width - $new_width) / 2;
        $y_offset = ($this->thumbnail_height - $new_height) / 2;
        
        imagecopyresampled(
            $thumbnail_image,
            $source_image,
            $x_offset,
            $y_offset,
            0,
            0,
            $new_width,
            $new_height,
            $source_width,
            $source_height
        );
        
        $result = false;
        switch ($mime_type) {
            case 'image/jpeg':
                $result = imagejpeg($thumbnail_image, $thumbnail_path, 90);
                break;
            case 'image/png':
                $result = imagepng($thumbnail_image, $thumbnail_path, 9);
                break;
            case 'image/gif':
                $result = imagegif($thumbnail_image, $thumbnail_path);
                break;
            case 'image/webp':
                $result = imagewebp($thumbnail_image, $thumbnail_path, 90);
                break;
        }
        
        imagedestroy($source_image);
        imagedestroy($thumbnail_image);
        
        return $result;
    }
    
    private function cleanupOldFiles() {
        $files = glob($this->upload_dir . '*');
        $thumbnails = glob($this->thumbnail_dir . '*');
        
        if (count($files) > 10) {
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            for ($i = 0; $i < count($files) - 10; $i++) {
                unlink($files[$i]);
            }
        }
        
        if (count($thumbnails) > 10) {
            usort($thumbnails, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            for ($i = 0; $i < count($thumbnails) - 10; $i++) {
                unlink($thumbnails[$i]);
            }
        }
    }
}

$handler = new ImageUploadHandler();
$handler->handleUpload();
?>