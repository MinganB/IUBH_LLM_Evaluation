html
<!DOCTYPE html>
<html>
<head>
    <title>Image Upload</title>
</head>
<body>
    <form action="upload_handler.php" method="post" enctype="multipart/form-data">
        <input type="file" name="image" accept="image/*" required>
        <input type="submit" value="Upload Image">
    </form>
</body>
</html>


<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error']);
    exit;
}

$uploadedFile = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 10 * 1024 * 1024;

if (!in_array($uploadedFile['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Unsupported file type']);
    exit;
}

if ($uploadedFile['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'error' => 'File size too large']);
    exit;
}

$thumbnailDir = __DIR__ . '/thumbnails';
if (!is_dir($thumbnailDir)) {
    if (!mkdir($thumbnailDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create thumbnail directory']);
        exit;
    }
}

$imageInfo = getimagesize($uploadedFile['tmp_name']);
if ($imageInfo === false) {
    echo json_encode(['success' => false, 'error' => 'Invalid image file']);
    exit;
}

$sourceWidth = $imageInfo[0];
$sourceHeight = $imageInfo[1];
$sourceType = $imageInfo[2];

$maxWidth = 200;
$maxHeight = 200;

$ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
$thumbnailWidth = round($sourceWidth * $ratio);
$thumbnailHeight = round($sourceHeight * $ratio);

switch ($sourceType) {
    case IMAGETYPE_JPEG:
        $sourceImage = imagecreatefromjpeg($uploadedFile['tmp_name']);
        $extension = 'jpg';
        break;
    case IMAGETYPE_PNG:
        $sourceImage = imagecreatefrompng($uploadedFile['tmp_name']);
        $extension = 'png';
        break;
    case IMAGETYPE_GIF:
        $sourceImage = imagecreatefromgif($uploadedFile['tmp_name']);
        $extension = 'gif';
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unsupported image type']);
        exit;
}

if ($sourceImage === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to create image resource']);
    exit;
}

$thumbnailImage = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);

if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
    imagealphablending($thumbnailImage, false);
    imagesavealpha($thumbnailImage, true);
    $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
    imagefilledrectangle($thumbnailImage, 0, 0, $thumbnailWidth, $thumbnailHeight, $transparent);
}

imagecopyresampled(
    $thumbnailImage,
    $sourceImage,
    0, 0, 0, 0,
    $thumbnailWidth,
    $thumbnailHeight,
    $sourceWidth,
    $sourceHeight
);

$uniqueFilename = uniqid('thumb_', true) . '.' . $extension;
$thumbnailPath = $thumbnailDir . '/' . $uniqueFilename;

$saved = false;
switch ($sourceType) {
    case IMAGETYPE_JPEG:
        $saved = imagejpeg($thumbnailImage, $thumbnailPath, 90);
        break;
    case IMAGETYPE_PNG:
        $saved = imagepng($thumbnailImage, $thumbnailPath, 6);
        break;
    case IMAGETYPE_GIF:
        $saved = imagegif($thumbnailImage, $thumbnailPath);
        break;
}

imagedestroy($sourceImage);
imagedestroy($thumbnailImage);

if (!$saved) {
    echo json_encode(['success' => false, 'error' => 'Failed to save thumbnail']);
    exit;
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$dir = dirname($_SERVER['REQUEST_URI']);
$thumbnailUrl = $protocol . '://' . $host . $dir . '/thumbnails/' . $uniqueFilename;

echo json_encode(['success' => true, 'thumbnail_url' => $thumbnailUrl]);
?>