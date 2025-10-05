<?php
class ImageUploadHandler {
    private $uploadDir = 'uploads/';
    private $thumbDir = 'thumbnails/';
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private $maxFileSize = 5242880; // 5MB
    private $thumbWidth = 150;
    private $thumbHeight = 150;

    public function __construct() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!file_exists($this->thumbDir)) {
            mkdir($this->thumbDir, 0755, true);
        }
    }

    public function uploadImage($file) {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception('No file uploaded');
        }

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

        $extension = $this->getExtensionFromMime($mimeType);
        $fileName = uniqid() . '.' . $extension;
        $uploadPath = $this->uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            throw new Exception('Failed to save uploaded file');
        }

        $thumbnailPath = $this->generateThumbnail($uploadPath, $fileName);

        return [
            'original' => $uploadPath,
            'thumbnail' => $thumbnailPath,
            'filename' => $fileName
        ];
    }

    private function generateThumbnail($sourcePath, $fileName) {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new Exception('Invalid image file');
        }

        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $sourceMime = $imageInfo['mime'];

        $sourceImage = $this->createImageFromFile($sourcePath, $sourceMime);
        if (!$sourceImage) {
            throw new Exception('Failed to create image resource');
        }

        $aspectRatio = $sourceWidth / $sourceHeight;
        if ($aspectRatio > 1) {
            $newWidth = $this->thumbWidth;
            $newHeight = $this->thumbWidth / $aspectRatio;
        } else {
            $newHeight = $this->thumbHeight;
            $newWidth = $this->thumbHeight * $aspectRatio;
        }

        $thumbnail = imagecreatetruecolor($this->thumbWidth, $this->thumbHeight);
        $white = imagecolorallocate($thumbnail, 255, 255, 255);
        imagefill($thumbnail, 0, 0, $white);

        $destX = ($this->thumbWidth - $newWidth) / 2;
        $destY = ($this->thumbHeight - $newHeight) / 2;

        imagecopyresampled(
            $thumbnail, $sourceImage,
            $destX, $destY, 0, 0,
            $newWidth, $newHeight, $sourceWidth, $sourceHeight
        );

        $thumbnailPath = $this->thumbDir . $fileName;
        $this->saveImage($thumbnail, $thumbnailPath, $sourceMime);

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        return $thumbnailPath;
    }

    private function createImageFromFile($path, $mime) {
        switch ($mime) {
            case 'image/jpeg':
                return imagecreatefromjpeg($path);
            case 'image/png':
                return imagecreatefrompng($path);
            case 'image/gif':
                return imagecreatefromgif($path);
            case 'image/webp':
                return imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    private function saveImage($image, $path, $mime) {
        switch ($mime) {
            case 'image/jpeg':
                return imagejpeg($image, $path, 85);
            case 'image/png':
                return imagepng($image, $path, 8);
            case 'image/gif':
                return imagegif($image, $path);
            case 'image/webp':
                return imagewebp($image, $path, 85);
            default:
                return false;
        }
    }

    private function getExtensionFromMime($mime) {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        return $extensions[$mime] ?? 'jpg';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    header('Content-Type: application/json');
    
    try {
        $handler = new ImageUploadHandler();
        $result = $handler->uploadImage($_FILES['image']);
        
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload with Thumbnail</title>
</head>
<body>
    <div class="upload-container">
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="upload-area">
                <input type="file" id="imageInput" name="image" accept="image/*" required>
                <label for="imageInput">Choose Image</label>
            </div>
            <button type="submit">Upload Image</button>
        </form>
        
        <div id="result" style="display: none;">
            <div id="images">
                <div>
                    <h3>Original Image</h3>
                    <img id="originalImage" alt="Original">
                </div>
                <div>
                    <h3>Thumbnail</h3>
                    <img id="thumbnailImage" alt="Thumbnail">
                </div>
            </div>
        </div>
        
        <div id="error" style="display: none; color: red;"></div>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const fileInput = document.getElementById('imageInput');
            const file = fileInput.files[0];
            
            if (!file) {
                showError('Please select a file');
                return;
            }
            
            formData.append('image', file);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showResult(data.data);
                } else {
                    showError(data.error);
                }
            })
            .catch(error => {
                showError('Upload failed: ' + error.message);
            });
        });
        
        function showResult(data) {
            document.getElementById('error').style.display = 'none';
            document.getElementById('originalImage').src = data.original;
            document.getElementById('thumbnailImage').src = data.thumbnail;
            document.getElementById('result').style.display = 'block';
        }
        
        function showError(message) {
            document.getElementById('result').style.display = 'none';
            document.getElementById('error').textContent = message;
            document.getElementById('error').style.display = 'block';
        }
        
        document.getElementById('imageInput').addEventListener('change', function() {
            document.getElementById('result').style.display = 'none';
            document.getElementById('error').style.display = 'none';
        });
    </script>
</body>
</html>
?>