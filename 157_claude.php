<?php
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
        <button type="submit">Upload Image</button>
    </form>
</body>
</html>


<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$uploadedFile = $_FILES['profile_image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 5 * 1024 * 1024;

if (!in_array($uploadedFile['type'], $allowedTypes)) {
    echo json_encode(['error' => 'Unsupported file type. Only JPEG, PNG, and GIF are allowed']);
    exit;
}

if ($uploadedFile['size'] > $maxFileSize) {
    echo json_encode(['error' => 'File size too large. Maximum size is 5MB']);
    exit;
}

$thumbnailDir = __DIR__ . '/thumbnails';
if (!is_dir($thumbnailDir)) {
    if (!mkdir($thumbnailDir, 0755, true)) {
        echo json_encode(['error' => 'Failed to create thumbnails directory']);
        exit;
    }
}

$imageInfo = getimagesize($uploadedFile['tmp_name']);
if (!$imageInfo) {
    echo json_encode(['error' => 'Invalid image file']);
    exit;
}

$originalWidth = $imageInfo[0];
$originalHeight = $imageInfo[1];
$imageType = $imageInfo[2];

switch ($imageType) {
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
        echo json_encode(['error' => 'Unsupported image type']);
        exit;
}

if (!$sourceImage) {
    echo json_encode(['error' => 'Failed to create image resource']);
    exit;
}

$maxWidth = 200;
$maxHeight = 200;

$ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
$thumbnailWidth = (int)($originalWidth * $ratio);
$thumbnailHeight = (int)($originalHeight * $ratio);

$thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);

if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);
    $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
    imagefill($thumbnail, 0, 0, $transparent);
}

if (!imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $thumbnailWidth, $thumbnailHeight, $originalWidth, $originalHeight)) {
    imagedestroy($sourceImage);
    imagedestroy($thumbnail);
    echo json_encode(['error' => 'Failed to resize image']);
    exit;
}

$uniqueFilename = uniqid('thumb_', true) . '.' . $extension;
$thumbnailPath = $thumbnailDir . '/' . $uniqueFilename;

$saved = false;
switch ($imageType) {
    case IMAGETYPE_JPEG:
        $saved = imagejpeg($thumbnail, $thumbnailPath, 90);
        break;
    case IMAGETYPE_PNG:
        $saved = imagepng($thumbnail, $thumbnailPath);
        break;
    case IMAGETYPE_GIF:
        $saved = imagegif($thumbnail, $thumbnailPath);
        break;
}

imagedestroy($sourceImage);
imagedestroy($thumbnail);

if (!$saved) {
    echo json_encode(['error' => 'Failed to save thumbnail']);
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$thumbnailUrl = $protocol . $host . $scriptDir . '/thumbnails/' . $uniqueFilename;

echo json_encode([
    'success' => true,
    'thumbnail_url' => $thumbnailUrl,
    'filename' => $uniqueFilename
]);
?>