<?php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <form id="uploadForm" action="/handlers/upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_image">Choose Profile Picture:</label>
        <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif" required>
        <input type="submit" value="Upload Image">
    </form>

    <div id="result"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('/handlers/upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('result');
                if (data.success) {
                    resultDiv.innerHTML = '<p>Success! Thumbnail created: <a href="' + data.thumbnail_url + '" target="_blank">View Thumbnail</a></p>';
                } else {
                    resultDiv.innerHTML = '<p>Error: ' + data.error + '</p>';
                }
            })
            .catch(error => {
                document.getElementById('result').innerHTML = '<p>Error: Upload failed</p>';
            });
        });
    </script>
</body>
</html>


<?php
require_once '../classes/ImageProcessor.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$uploadedFile = $_FILES['profile_image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 5 * 1024 * 1024;

if (!in_array($uploadedFile['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Unsupported file type']);
    exit;
}

if ($uploadedFile['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'error' => 'File too large']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type']);
    exit;
}

try {
    $imageProcessor = new ImageProcessor();
    $thumbnailPath = $imageProcessor->createThumbnail($uploadedFile['tmp_name'], $mimeType);
    
    if ($thumbnailPath) {
        $thumbnailUrl = '/thumbnails/' . basename($thumbnailPath);
        echo json_encode(['success' => true, 'thumbnail_url' => $thumbnailUrl]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to create thumbnail']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Processing error']);
}
?>


<?php
class ImageProcessor {
    private $thumbnailDir;
    private $maxWidth = 200;
    private $maxHeight = 200;
    
    public function __construct() {
        $this->thumbnailDir = $_SERVER['DOCUMENT_ROOT'] . '/thumbnails';
        
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
    }
    
    public function createThumbnail($sourcePath, $mimeType) {
        $sourceImage = $this->createImageFromFile($sourcePath, $mimeType);
        
        if (!$sourceImage) {
            return false;
        }
        
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        $dimensions = $this->calculateThumbnailDimensions($sourceWidth, $sourceHeight);
        
        $thumbnailImage = imagecreatetruecolor($dimensions['width'], $dimensions['height']);
        
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbnailImage, 0, 0, $dimensions['width'], $dimensions['height'], $transparent);
        }
        
        imagecopyresampled(
            $thumbnailImage, $sourceImage,
            0, 0, 0, 0,
            $dimensions['width'], $dimensions['height'],
            $sourceWidth, $sourceHeight
        );
        
        $filename = $this->generateUniqueFilename($mimeType);
        $thumbnailPath = $this->thumbnailDir . '/' . $filename;
        
        $saved = $this->saveImage($thumbnailImage, $thumbnailPath, $mimeType);
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
        
        return $saved ? $thumbnailPath : false;
    }
    
    private function createImageFromFile($filePath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filePath);
            case 'image/png':
                return imagecreatefrompng($filePath);
            case 'image/gif':
                return imagecreatefromgif($filePath);
            default:
                return false;
        }
    }
    
    private function calculateThumbnailDimensions($sourceWidth, $sourceHeight) {
        $ratio = min($this->maxWidth / $sourceWidth, $this->maxHeight / $sourceHeight);
        
        return [
            'width' => intval($sourceWidth * $ratio),
            'height' => intval($sourceHeight * $ratio)
        ];
    }
    
    private function generateUniqueFilename($mimeType) {
        $extension = $this->getExtensionFromMimeType($mimeType);
        $uniqueId = uniqid() . '_' . bin2hex(random_bytes(8));
        return 'thumb_' . $uniqueId . '.' . $extension;
    }
    
    private function getExtensionFromMimeType($mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return 'jpg';
            case 'image/png':
                return 'png';
            case 'image/gif':
                return 'gif';
            default:
                return 'jpg';
        }
    }
    
    private function saveImage($image, $path, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagejpeg($image, $path, 85);
            case 'image/png':
                return imagepng($image, $path, 6);
            case 'image/gif':
                return imagegif($image, $path);
            default:
                return false;
        }
    }
}
?>