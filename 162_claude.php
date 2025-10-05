<?php
class ImageProcessor {
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxFileSize = 5242880;
    private $maxDimensions = 2048;
    private $thumbnailMaxWidth = 200;
    private $thumbnailMaxHeight = 200;
    private $uploadDir = __DIR__ . '/../uploads/';
    private $thumbnailDir = __DIR__ . '/../thumbnails/';
    private $logFile = __DIR__ . '/../logs/upload.log';

    public function __construct() {
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

    public function processUpload($file) {
        try {
            $this->validateFile($file);
            
            $originalPath = $this->saveOriginalFile($file);
            $thumbnailPath = $this->generateThumbnail($originalPath);
            
            $this->logUpload(true, "File uploaded successfully");
            
            return [
                'success' => true,
                'thumbnail_url' => '/thumbnails/' . basename($thumbnailPath)
            ];
        } catch (Exception $e) {
            $this->logUpload(false, $e->getMessage());
            return [
                'success' => false,
                'error' => 'Upload failed'
            ];
        }
    }

    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . $file['error']);
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File too large');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('Invalid file type');
        }

        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new Exception('Invalid image file');
        }

        if ($imageInfo[0] > $this->maxDimensions || $imageInfo[1] > $this->maxDimensions) {
            throw new Exception('Image dimensions too large');
        }
    }

    private function saveOriginalFile($file) {
        $extension = $this->getExtensionFromMime($file['type']);
        $filename = $this->generateUniqueFilename($extension);
        $filepath = $this->uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to save file');
        }

        return $filepath;
    }

    private function generateThumbnail($originalPath) {
        $imageInfo = getimagesize($originalPath);
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        $source = $this->createImageFromFile($originalPath, $mimeType);
        if (!$source) {
            throw new Exception('Failed to create image resource');
        }

        $dimensions = $this->calculateThumbnailDimensions($originalWidth, $originalHeight);
        $thumbnail = imagecreatetruecolor($dimensions['width'], $dimensions['height']);

        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $dimensions['width'], $dimensions['height'], $transparent);
        }

        imagecopyresampled(
            $thumbnail, $source, 0, 0, 0, 0,
            $dimensions['width'], $dimensions['height'],
            $originalWidth, $originalHeight
        );

        $thumbnailFilename = $this->generateUniqueFilename('jpg');
        $thumbnailPath = $this->thumbnailDir . $thumbnailFilename;

        imagejpeg($thumbnail, $thumbnailPath, 90);

        imagedestroy($source);
        imagedestroy($thumbnail);

        return $thumbnailPath;
    }

    private function createImageFromFile($filepath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filepath);
            case 'image/png':
                return imagecreatefrompng($filepath);
            case 'image/gif':
                return imagecreatefromgif($filepath);
            default:
                return false;
        }
    }

    private function calculateThumbnailDimensions($originalWidth, $originalHeight) {
        $ratio = min($this->thumbnailMaxWidth / $originalWidth, $this->thumbnailMaxHeight / $originalHeight);
        
        return [
            'width' => (int)($originalWidth * $ratio),
            'height' => (int)($originalHeight * $ratio)
        ];
    }

    private function generateUniqueFilename($extension) {
        return uniqid('img_', true) . '.' . $extension;
    }

    private function getExtensionFromMime($mimeType) {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif'
        ];
        return $extensions[$mimeType] ?? 'jpg';
    }

    private function logUpload($success, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $status = $success ? 'SUCCESS' : 'FAILURE';
        $logEntry = "[$timestamp] $status - IP: $ip - $message" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>


<?php
require_once __DIR__ . '/../classes/ImageProcessor.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$processor = new ImageProcessor();
$result = $processor->processUpload($_FILES['image']);

if (!$result['success']) {
    http_response_code(400);
}

echo json_encode($result);
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <form id="uploadForm" enctype="multipart/form-data">
        <label for="image">Choose profile picture:</label>
        <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif" required>
        <button type="submit">Upload</button>
    </form>
    
    <div id="result"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const fileInput = document.getElementById('image');
            
            if (fileInput.files.length === 0) {
                document.getElementById('result').innerHTML = '<p>Please select a file</p>';
                return;
            }
            
            formData.append('image', fileInput.files[0]);
            
            fetch('/handlers/upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('result');
                if (data.success) {
                    resultDiv.innerHTML = `<p>Upload successful!</p><img src="${data.thumbnail_url}" alt="Thumbnail">`;
                } else {
                    resultDiv.innerHTML = `<p>Upload failed: ${data.error}</p>`;
                }
            })
            .catch(error => {
                document.getElementById('result').innerHTML = '<p>An error occurred during upload</p>';
            });
        });
    </script>
</body>
</html>
?>