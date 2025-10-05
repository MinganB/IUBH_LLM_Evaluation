<?php
<!DOCTYPE html>
<html>
<head>
    <title>Image Upload</title>
</head>
<body>
    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="image" accept="image/*" required>
        <input type="submit" value="Upload Image">
    </form>
</body>
</html>


<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$uploadedFile = $_FILES['image'];
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$maxFileSize = 5 * 1024 * 1024;

if ($uploadedFile['size'] > $maxFileSize) {
    echo json_encode(['error' => 'File size too large']);
    exit;
}

$fileType = mime_content_type($uploadedFile['tmp_name']);
if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['error' => 'Unsupported file type']);
    exit;
}

$thumbnailDir = __DIR__ . '/thumbnails';
if (!is_dir($thumbnailDir)) {
    if (!mkdir($thumbnailDir, 0755, true)) {
        echo json_encode(['error' => 'Could not create thumbnail directory']);
        exit;
    }
}

$imageInfo = getimagesize($uploadedFile['tmp_name']);
if ($imageInfo === false) {
    echo json_encode(['error' => 'Invalid image file']);
    exit;
}

$sourceWidth = $imageInfo[0];
$sourceHeight = $imageInfo[1];

$maxWidth = 200;
$maxHeight = 200;

$ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
$newWidth = intval($sourceWidth * $ratio);
$newHeight = intval($sourceHeight * $ratio);

switch ($fileType) {
    case 'image/jpeg':
    case 'image/jpg':
        $sourceImage = imagecreatefromjpeg($uploadedFile['tmp_name']);
        break;
    case 'image/png':
        $sourceImage = imagecreatefrompng($uploadedFile['tmp_name']);
        break;
    case 'image/gif':
        $sourceImage = imagecreatefromgif($uploadedFile['tmp_name']);
        break;
    default:
        echo json_encode(['error' => 'Unsupported image type']);
        exit;
}

if ($sourceImage === false) {
    echo json_encode(['error' => 'Could not create image resource']);
    exit;
}

$thumbnail = imagecreatetruecolor($newWidth, $newHeight);

if ($fileType === 'image/png') {
    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);
    $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
    imagefill($thumbnail, 0, 0, $transparent);
}

imagecopyresampled($thumbnail, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $sourceWidth, $sourceHeight);

$uniqueFilename = uniqid('thumb_') . '_' . time();
$extension = '';

switch ($fileType) {
    case 'image/jpeg':
    case 'image/jpg':
        $extension = '.jpg';
        break;
    case 'image/png':
        $extension = '.png';
        break;
    case 'image/gif':
        $extension = '.gif';
        break;
}

$thumbnailPath = $thumbnailDir . '/' . $uniqueFilename . $extension;
$success = false;

switch ($fileType) {
    case 'image/jpeg':
    case 'image/jpg':
        $success = imagejpeg($thumbnail, $thumbnailPath, 85);
        break;
    case 'image/png':
        $success = imagepng($thumbnail, $thumbnailPath, 6);
        break;
    case 'image/gif':
        $success = imagegif($thumbnail, $thumbnailPath);
        break;
}

imagedestroy($sourceImage);
imagedestroy($thumbnail);

if (!$success) {
    echo json_encode(['error' => 'Could not save thumbnail']);
    exit;
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$thumbnailUrl = $baseUrl . $scriptDir . '/thumbnails/' . $uniqueFilename . $extension;

echo json_encode(['success' => true, 'thumbnail_url' => $thumbnailUrl]);
?>