<?php
// /public/upload_form.html
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Profile Picture</title>
</head>
<body>
    <form action="upload_handler.php" method="post" enctype="multipart/form-data">
        <input type="file" name="profile_image" accept="image/*" required>
        <input type="submit" value="Upload Image">
    </form>
</body>
</html>


<?php
// /classes/ImageProcessor.php
class ImageProcessor
{
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxThumbnailWidth = 200;
    private $maxThumbnailHeight = 200;
    
    public function validateImage($file)
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        $mimeType = mime_content_type($file['tmp_name']);
        return in_array($mimeType, $this->allowedTypes);
    }
    
    public function generateThumbnail($sourcePath, $destinationPath)
    {
        $imageInfo = getimagesize($sourcePath);
        if (!$imageInfo) {
            return false;
        }
        
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        $sourceImage = $this->createImageResource($sourcePath, $mimeType);
        if (!$sourceImage) {
            return false;
        }
        
        $dimensions = $this->calculateThumbnailDimensions($sourceWidth, $sourceHeight);
        
        $thumbnail = imagecreatetruecolor($dimensions['width'], $dimensions['height']);
        
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefill($thumbnail, 0, 0, $transparent);
        }
        
        imagecopyresampled(
            $thumbnail,
            $sourceImage,
            0, 0, 0, 0,
            $dimensions['width'],
            $dimensions['height'],
            $sourceWidth,
            $sourceHeight
        );
        
        $result = $this->saveThumbnail($thumbnail, $destinationPath, $mimeType);
        
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        
        return $result;
    }
    
    private function createImageResource($path, $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($path);
            case 'image/png':
                return imagecreatefrompng($path);
            case 'image/gif':
                return imagecreatefromgif($path);
            default:
                return false;
        }
    }
    
    private function calculateThumbnailDimensions($sourceWidth, $sourceHeight)
    {
        $ratio = min($this->maxThumbnailWidth / $sourceWidth, $this->maxThumbnailHeight / $sourceHeight);
        
        return [
            'width' => round($sourceWidth * $ratio),
            'height' => round($sourceHeight * $ratio)
        ];
    }
    
    private function saveThumbnail($image, $path, $mimeType)
    {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagejpeg($image, $path, 90);
            case 'image/png':
                return imagepng($image, $path, 8);
            case 'image/gif':
                return imagegif($image, $path);
            default:
                return false;
        }
    }
    
    public function generateUniqueFilename($originalName)
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        return uniqid('thumb_', true) . '.' . $extension;
    }
}
?>


<?php
// /handlers/upload_handler.php
require_once '../classes/ImageProcessor.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['profile_image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No image file uploaded']);
    exit;
}

$uploadedFile = $_FILES['profile_image'];

if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'File upload failed']);
    exit;
}

$imageProcessor = new ImageProcessor();

if (!$imageProcessor->validateImage($uploadedFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type. Only JPEG, PNG, and GIF files are allowed']);
    exit;
}

$thumbnailDir = '../thumbnails';
if (!is_dir($thumbnailDir)) {
    if (!mkdir($thumbnailDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create thumbnail directory']);
        exit;
    }
}

$thumbnailFilename = $imageProcessor->generateUniqueFilename($uploadedFile['name']);
$thumbnailPath = $thumbnailDir . '/' . $thumbnailFilename;

if (!$imageProcessor->generateThumbnail($uploadedFile['tmp_name'], $thumbnailPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to generate thumbnail']);
    exit;
}

$thumbnailUrl = '/thumbnails/' . $thumbnailFilename;

echo json_encode(['thumbnail_url' => $thumbnailUrl]);
?>