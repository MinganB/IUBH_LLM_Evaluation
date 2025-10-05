<?php
class ThumbnailGenerator {
    private $uploadDir;
    private $thumbnailDir;
    private $maxFileSize;
    private $allowedTypes;
    
    public function __construct($uploadDir = 'uploads/', $thumbnailDir = 'thumbnails/', $maxFileSize = 5242880) {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->thumbnailDir = rtrim($thumbnailDir, '/') . '/';
        $this->maxFileSize = $maxFileSize;
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
    }
    
    public function handleUpload() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file uploaded or upload error'];
        }
        
        $file = $_FILES['image'];
        
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'message' => 'File size exceeds maximum limit'];
        }
        
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }
        
        $extension = $this->getExtensionFromMime($mimeType);
        $filename = uniqid() . '.' . $extension;
        $filepath = $this->uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['success' => false, 'message' => 'Failed to save uploaded file'];
        }
        
        $thumbnailResult = $this->generateThumbnail($filepath, $filename);
        
        if (!$thumbnailResult['success']) {
            unlink($filepath);
            return $thumbnailResult;
        }
        
        return [
            'success' => true,
            'message' => 'Image uploaded and thumbnail generated successfully',
            'original' => $filename,
            'thumbnail' => $thumbnailResult['thumbnail'],
            'original_url' => $this->uploadDir . $filename,
            'thumbnail_url' => $this->thumbnailDir . $thumbnailResult['thumbnail']
        ];
    }
    
    private function generateThumbnail($originalPath, $originalFilename, $width = 200, $height = 200) {
        $imageInfo = getimagesize($originalPath);
        if (!$imageInfo) {
            return ['success' => false, 'message' => 'Invalid image file'];
        }
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        $sourceImage = $this->createImageFromFile($originalPath, $mimeType);
        if (!$sourceImage) {
            return ['success' => false, 'message' => 'Failed to create image resource'];
        }
        
        $aspectRatio = $originalWidth / $originalHeight;
        if ($aspectRatio > 1) {
            $newWidth = $width;
            $newHeight = $width / $aspectRatio;
        } else {
            $newHeight = $height;
            $newWidth = $height * $aspectRatio;
        }
        
        $thumbnailImage = imagecreatetruecolor($newWidth, $newHeight);
        
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
            imagefill($thumbnailImage, 0, 0, $transparent);
        }
        
        imagecopyresampled(
            $thumbnailImage, $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );
        
        $thumbnailFilename = 'thumb_' . $originalFilename;
        $thumbnailPath = $this->thumbnailDir . $thumbnailFilename;
        
        $success = $this->saveImage($thumbnailImage, $thumbnailPath, $mimeType);
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
        
        if ($success) {
            return ['success' => true, 'thumbnail' => $thumbnailFilename];
        } else {
            return ['success' => false, 'message' => 'Failed to save thumbnail'];
        }
    }
    
    private function createImageFromFile($filepath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filepath);
            case 'image/png':
                return imagecreatefrompng($filepath);
            case 'image/gif':
                return imagecreatefromgif($filepath);
            case 'image/webp':
                return imagecreatefromwebp($filepath);
            default:
                return false;
        }
    }
    
    private function saveImage($image, $filepath, $mimeType, $quality = 85) {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagejpeg($image, $filepath, $quality);
            case 'image/png':
                return imagepng($image, $filepath, 9);
            case 'image/gif':
                return imagegif($image, $filepath);
            case 'image/webp':
                return imagewebp($image, $filepath, $quality);
            default:
                return false;
        }
    }
    
    private function getExtensionFromMime($mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
                return 'jpg';
            case 'image/png':
                return 'png';
            case 'image/gif':
                return 'gif';
            case 'image/webp':
                return 'webp';
            default:
                return 'jpg';
        }
    }
}

$thumbnailGenerator = new ThumbnailGenerator();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $result = $thumbnailGenerator->handleUpload();
    echo json_encode($result);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Thumbnail Generator</title>
</head>
<body>
    <div class="container">
        <h1>Image Thumbnail Generator</h1>
        
        <form id="uploadForm" enctype="multipart/form-data">
            <div class="upload-area">
                <input type="file" id="imageInput" name="image" accept="image/*" required>
                <label for="imageInput">Choose an image file</label>
            </div>
            <button type="submit">Upload and Generate Thumbnail</button>
        </form>
        
        <div id="loading" style="display: none;">
            <p>Processing image...</p>
        </div>
        
        <div id="result" style="display: none;">
            <h2>Result</h2>
            <div id="images">
                <div class="image-container">
                    <h3>Original Image</h3>
                    <img id="originalImage" alt="Original Image">
                </div>
                <div class="image-container">
                    <h3>Thumbnail</h3>
                    <img id="thumbnailImage" alt="Thumbnail">
                </div>
            </div>
        </div>
        
        <div id="error" style="display: none;">
            <h2>Error</h2>
            <p id="errorMessage"></p>
        </div>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const imageInput = document.getElementById('imageInput');
            
            if (!imageInput.files[0]) {
                showError('Please select an image file');
                return;
            }
            
            formData.append('image', imageInput.files[0]);
            
            showLoading();
            hideResult();
            hideError();
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                
                if (data.success) {
                    showResult(data);
                } else {
                    showError(data.message);
                }
            })
            .catch(error => {
                hideLoading();
                showError('An error occurred while uploading the image');
                console.error('Error:', error);
            });
        });
        
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }
        
        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }
        
        function showResult(data) {
            document.getElementById('originalImage').src = data.original_url;
            document.getElementById('thumbnailImage').src = data.thumbnail_url;
            document.getElementById('result').style.display = 'block';
        }
        
        function hideResult() {
            document.getElementById('result').style.display = 'none';
        }
        
        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('error').style.display = 'block';
        }
        
        function hideError() {
            document.getElementById('error').style.display = 'none';
        }
    </script>
</body>
</html>
?>