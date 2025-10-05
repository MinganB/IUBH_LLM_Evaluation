<?php
// /classes/ImageProcessor.php
<?php

class ImageProcessor
{
    private $uploadDir;
    private $thumbnailDir;
    private $logFile;
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxFileSize = 5242880; // 5MB
    private $maxWidth = 2048;
    private $maxHeight = 2048;
    private $thumbnailSize = 150;

    public function __construct($uploadDir, $thumbnailDir, $logFile)
    {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->thumbnailDir = rtrim($thumbnailDir, '/') . '/';
        $this->logFile = $logFile;
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
    }

    public function processUpload($file, $userId)
    {
        $clientIp = $this->getClientIp();
        
        try {
            $this->validateFile($file);
            
            $sanitizedFilename = $this->sanitizeFilename($file['name'], $userId);
            $originalPath = $this->uploadDir . $sanitizedFilename;
            $thumbnailPath = $this->thumbnailDir . $sanitizedFilename;
            
            if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
                throw new Exception('Failed to move uploaded file');
            }
            
            $this->createThumbnail($originalPath, $thumbnailPath);
            
            $this->logUpload(true, $userId, $sanitizedFilename, $clientIp);
            
            return [
                'success' => true,
                'filename' => $sanitizedFilename,
                'original' => $originalPath,
                'thumbnail' => $thumbnailPath
            ];
            
        } catch (Exception $e) {
            $this->logUpload(false, $userId, $file['name'] ?? 'unknown', $clientIp, $e->getMessage());
            return ['success' => false, 'error' => 'Upload failed'];
        }
    }

    private function validateFile($file)
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new Exception('Invalid file parameters');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . $file['error']);
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds maximum allowed');
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

        if ($imageInfo[0] > $this->maxWidth || $imageInfo[1] > $this->maxHeight) {
            throw new Exception('Image dimensions exceed maximum allowed');
        }
    }

    private function sanitizeFilename($filename, $userId)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($extension, $allowedExtensions)) {
            $extension = 'jpg';
        }

        $sanitized = preg_replace('/[^a-zA-Z0-9]/', '', basename($filename, '.' . pathinfo($filename, PATHINFO_EXTENSION)));
        $sanitized = substr($sanitized, 0, 50);
        
        return 'user_' . intval($userId) . '_' . time() . '_' . $sanitized . '.' . $extension;
    }

    private function createThumbnail($sourcePath, $thumbnailPath)
    {
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
                throw new Exception('Unsupported image type for thumbnail');
        }

        if ($sourceImage === false) {
            throw new Exception('Failed to create image resource');
        }

        $aspectRatio = $sourceWidth / $sourceHeight;
        
        if ($aspectRatio > 1) {
            $thumbWidth = $this->thumbnailSize;
            $thumbHeight = $this->thumbnailSize / $aspectRatio;
        } else {
            $thumbHeight = $this->thumbnailSize;
            $thumbWidth = $this->thumbnailSize * $aspectRatio;
        }

        $thumbnailImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbnailImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
        }

        imagecopyresampled($thumbnailImage, $sourceImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);

        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($thumbnailImage, $thumbnailPath, 85);
                break;
            case 'image/png':
                imagepng($thumbnailImage, $thumbnailPath, 6);
                break;
            case 'image/gif':
                imagegif($thumbnailImage, $thumbnailPath);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
    }

    private function getClientIp()
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    private function logUpload($success, $userId, $filename, $clientIp, $errorMessage = '')
    {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $logEntry = sprintf(
            "[%s] %s - User ID: %s, Filename: %s, IP: %s%s\n",
            $timestamp,
            $status,
            $userId,
            $filename,
            $clientIp,
            $errorMessage ? ', Error: ' . $errorMessage : ''
        );
        
        error_log($logEntry, 3, $this->logFile);
    }
}


<?php
// /classes/Database.php
<?php

class Database
{
    private $pdo;

    public function __construct($host, $dbname, $username, $password)
    {
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new PDO($dsn, $username, $password, $options);
    }

    public function updateUserProfileImage($userId, $originalPath, $thumbnailPath)
    {
        $stmt = $this->pdo->prepare("UPDATE users SET profile_image = ?, profile_thumbnail = ?, updated_at = NOW() WHERE id = ?");
        return $stmt->execute([$originalPath, $thumbnailPath, $userId]);
    }

    public function getUserById($userId)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
}


<?php
// /handlers/upload_handler.php
<?php

require_once '../classes/ImageProcessor.php';
require_once '../classes/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

if (!isset($_FILES['profile_image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$config = [
    'upload_dir' => '../uploads/original/',
    'thumbnail_dir' => '../uploads/thumbnails/',
    'log_file' => '../logs/upload.log',
    'db_host' => 'localhost',
    'db_name' => 'social_media',
    'db_user' => 'your_db_user',
    'db_pass' => 'your_db_password'
];

try {
    $imageProcessor = new ImageProcessor($config['upload_dir'], $config['thumbnail_dir'], $config['log_file']);
    $result = $imageProcessor->processUpload($_FILES['profile_image'], $userId);
    
    if ($result['success']) {
        $database = new Database($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);
        $database->updateUserProfileImage($userId, $result['original'], $result['thumbnail']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Profile image updated successfully',
            'filename' => $result['filename']
        ]);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log('Upload handler error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}


<?php
// /public/upload_profile_image.php
<?php

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
    <title>Upload Profile Image</title>
</head>
<body>
    <div class="upload-container">
        <h2>Upload Profile Image</h2>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="profile_image">Choose profile image:</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/*" required>
            </div>
            
            <div class="form-group">
                <button type="submit" id="uploadBtn">Upload Image</button>
            </div>
        </form>
        
        <div id="uploadStatus"></div>
        
        <div id="previewContainer" style="display: none;">
            <h3>Preview:</h3>
            <img id="imagePreview" alt="Preview" style="max-width: 200px; max-height: 200px;">
        </div>
    </div>

    <script>
        document.getElementById('profile_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('previewContainer').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });

        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const fileInput = document.getElementById('profile_image');
            const statusDiv = document.getElementById('uploadStatus');
            const uploadBtn = document.getElementById('uploadBtn');
            
            if (!fileInput.files[0]) {
                statusDiv.innerHTML = '<div class="error">Please select a file to upload.</div>';
                return;
            }
            
            formData.append('profile_image', fileInput.files[0]);
            
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            statusDiv.innerHTML = '<div class="info">Uploading image...</div>';
            
            fetch('../handlers/upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = '<div class="success">' + data.message + '</div>';
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    statusDiv.innerHTML = '<div class="error">' + data.error + '</div>';
                }
            })
            .catch(error => {
                statusDiv.innerHTML = '<div class="error">An error occurred during upload.</div>';
            })
            .finally(() => {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload Image';
            });
        });
    </script>
</body>
</html>


<?php
// /public/profile.php
<?php

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../classes/Database.php';

$config = [
    'db_host' => 'localhost',
    'db_name' => 'social_media',
    'db_user' => 'your_db_user',
    'db_pass' => 'your_db_password'
];

try {
    $database = new Database($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);
    $user = $database->getUserById($_SESSION['user_id']);
} catch (Exception $e) {
    error_log('Profile page error: ' . $e->getMessage());
    $user = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="
?>