<?php
class ThumbnailGenerator {
    public static function createThumbnail($sourcePath, $destDir, $maxWidth = 200, $maxHeight = 200) {
        if (!is_file($sourcePath)) {
            return false;
        }
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }
        $mime = $imageInfo['mime'];
        switch ($mime) {
            case 'image/jpeg':
                $srcImg = imagecreatefromjpeg($sourcePath);
                $ext = 'jpg';
                break;
            case 'image/png':
                $srcImg = imagecreatefrompng($sourcePath);
                $ext = 'png';
                break;
            case 'image/gif':
                $srcImg = imagecreatefromgif($sourcePath);
                $ext = 'gif';
                break;
            default:
                return false;
        }
        if (!$srcImg) {
            return false;
        }
        $srcW = imagesx($srcImg);
        $srcH = imagesy($srcImg);
        $ratio = min($maxWidth / $srcW, $maxHeight / $srcH, 1);
        $dstW = (int)round($srcW * $ratio);
        $dstH = (int)round($srcH * $ratio);
        $dstImg = imagecreatetruecolor($dstW, $dstH);
        if ($mime === 'image/png') {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $transparent = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
            imagefilledrectangle($dstImg, 0, 0, $dstW, $dstH, $transparent);
        }
        if ($dstW > 0 && $dstH > 0) {
            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
        }
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        $filename = uniqid('thumb_', true) . '.' . $ext;
        $destPath = rtrim($destDir, '/\\') . '/' . $filename;
        switch ($mime) {
            case 'image/jpeg':
                imagejpeg($dstImg, $destPath, 90);
                break;
            case 'image/png':
                imagepng($dstImg, $destPath);
                break;
            case 'image/gif':
                imagegif($dstImg, $destPath);
                break;
        }
        imagedestroy($srcImg);
        imagedestroy($dstImg);
        if (file_exists($destPath)) {
            return $destPath;
        }
        return false;
    }
}
?> 
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Upload Profile Picture</title>
</head>
<body>
<form action="/handlers/upload_handler.php" method="post" enctype="multipart/form-data">
  <input type="file" name="image" accept="image/*" required>
  <button type="submit">Upload</button>
</form>
</body>
</html>
<?php
?> 
<?php
require_once __DIR__ . '/../classes/ThumbnailGenerator.php';
$ROOT = dirname(__DIR__);
$uploadsDir = $ROOT . '/public/uploads';
$thumbsDir = $ROOT . '/public/thumbnails';
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
    exit;
}
$file = $_FILES['image'];
$tmpPath = $file['tmp_name'];
$imageInfo = getimagesize($tmpPath);
if ($imageInfo === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid image file.']);
    exit;
}
$mime = $imageInfo['mime'];
$allowed = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($mime, $allowed)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unsupported image type.']);
    exit;
}
$ext = '';
switch ($mime) {
    case 'image/jpeg': $ext = 'jpg'; break;
    case 'image/png': $ext = 'png'; break;
    case 'image/gif': $ext = 'gif'; break;
}
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}
$originalFilename = uniqid('img_', true) . '.' . $ext;
$uploadPath = $uploadsDir . '/' . $originalFilename;
if (!move_uploaded_file($tmpPath, $uploadPath)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file.']);
    exit;
}
$thumbPath = ThumbnailGenerator::createThumbnail($uploadPath, $thumbsDir, 200, 200);
if ($thumbPath === false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Failed to generate thumbnail.']);
    exit;
}
$thumbnailUrl = '/thumbnails/' . basename($thumbPath);
header('Content-Type: application/json');
echo json_encode(['success' => true, 'thumbnail_url' => $thumbnailUrl]);
exit;
?>