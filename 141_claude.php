<?php

class ImageUploadHandler {
    private $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $maxFileSize = 5242880;
    private $maxWidth = 2000;
    private $maxHeight = 2000;
    private $uploadDir = 'uploads/profiles/';
    private $thumbnailDir = 'uploads/thumbnails/';
    private $logFile = 'logs/uploads.log';
    private $thumbnailWidth = 150;
    private $thumbnailHeight = 150;

    public function __construct() {
        if (!extension_loaded('imagick')) {
            throw new Exception('ImageMagick extension is required');
        }
        
        $this->ensureDirectoriesExist();
    }

    private function ensureDirectoriesExist() {
        $dirs = [$this->uploadDir, $this->thumbnailDir, dirname($this->logFile)];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public function handleUpload($userId) {
        try {
            if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Upload failed');
            }

            $file = $_FILES['profile_image'];
            
            $this->validateFile($file);
            
            $filename = $this->sanitizeFilename($file['name'], $userId);
            $uploadPath = $this->uploadDir . $filename;
            $thumbnailPath = $this->thumbnailDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                throw new Exception('Failed to save file');
            }

            $this->generateThumbnail($uploadPath, $thumbnailPath);
            
            $this->logUpload($userId, $filename, 'SUCCESS');
            
            return [
                'success' => true,
                'filename' => $filename,
                'original_path' => $uploadPath,
                'thumbnail_path' => $thumbnailPath
            ];

        } catch (Exception $e) {
            $this->logUpload($userId, $file['name'] ?? 'unknown', 'FAILED: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Upload failed'];
        }
    }

    private function validateFile($file) {
        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File too large');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new Exception('Invalid file type');
        }

        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Invalid image file');
        }

        if ($imageInfo[0] > $this->maxWidth || $imageInfo[1] > $this->maxHeight) {
            throw new Exception('Image dimensions too large');
        }
    }

    private function sanitizeFilename($originalName, $userId) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $extension = strtolower($extension);
        
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $extension = 'jpg';
        }
        
        return 'user_' . intval($userId) . '_' . uniqid() . '.' . $extension;
    }

    private function generateThumbnail($sourcePath, $thumbnailPath) {
        $imagick = new Imagick($sourcePath);
        
        $imagick->thumbnailImage($this->thumbnailWidth, $this->thumbnailHeight, true, true);
        $imagick->setImageFormat('jpeg');
        $imagick->setImageCompressionQuality(85);
        $imagick->writeImage($thumbnailPath);
        $imagick->clear();
    }

    private function logUpload($userId, $filename, $status) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $logEntry = sprintf(
            "[%s] User: %s | IP: %s | File: %s | Status: %s | UA: %s\n",
            $timestamp,
            $userId,
            $ip,
            $filename,
            $status,
            $userAgent
        );
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    $uploader = new ImageUploadHandler();
    $result = $uploader->handleUpload($_SESSION['user_id']);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile Picture Upload</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <div class="upload-container">
        <h2>Upload Profile Picture</h2>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="profile_image">Choose Profile Picture:</label>
                <input type="file" 
                       id="profile_image" 
                       name="profile_image" 
                       accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" 
                       required>
            </div>
            
            <button type="submit" name="upload" id="uploadBtn">Upload</button>
        </form>

        <div id="preview" style="display: none;">
            <h3>Preview:</h3>
            <img id="previewImage" style="max-width: 200px; max-height: 200px;">
        </div>

        <div id="result" style="display: none;"></div>
        
        <div id="loading" style="display: none;">
            <p>Uploading...</p>
        </div>
    </div>

    <script>
    document.getElementById('profile_image').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('previewImage').src = e.target.result;
                document.getElementById('preview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        }
    });

    document.getElementById('uploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        const fileInput = document.getElementById('profile_image');
        
        if (!fileInput.files[0]) {
            alert('Please select a file');
            return;
        }
        
        formData.append('profile_image', fileInput.files[0]);
        formData.append('upload', '1');
        
        document.getElementById('loading').style.display = 'block';
        document.getElementById('result').style.display = 'none';
        document.getElementById('uploadBtn').disabled = true;
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('uploadBtn').disabled = false;
            
            const resultDiv = document.getElementById('result');
            resultDiv.style.display = 'block';
            
            if (data.success) {
                resultDiv.innerHTML = `
                    <div style="color: green;">
                        <p>Upload successful!</p>
                        <p>Original: ${data.original_path}</p>
                        <p>Thumbnail: ${data.thumbnail_path}</p>
                        <img src="${data.thumbnail_path}" style="max-width: 150px;">
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div style="color: red;">
                        <p>Upload failed: ${data.error}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('uploadBtn').disabled = false;
            
            const resultDiv = document.getElementById('result');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = `
                <div style="color: red;">
                    <p>Upload failed: Network error</p>
                </div>
            `;
        });
    });
    </script>
</body>
</html>
?>