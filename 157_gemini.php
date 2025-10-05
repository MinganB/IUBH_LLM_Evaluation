<?php
header('Content-Type: application/json');

define('THUMBNAIL_DIR', 'thumbnails/');
define('MAX_THUMB_WIDTH', 200);
define('MAX_THUMB_HEIGHT', 200);
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

function sendJsonResponse($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

if (!isset($_FILES['image'])) {
    sendJsonResponse(false, 'No image file uploaded.');
}

$file = $_FILES['image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            sendJsonResponse(false, 'Uploaded file exceeds maximum size.');
            break;
        case UPLOAD_ERR_PARTIAL:
            sendJsonResponse(false, 'File was only partially uploaded.');
            break;
        case UPLOAD_ERR_NO_FILE:
            sendJsonResponse(false, 'No file was uploaded.');
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            sendJsonResponse(false, 'Missing a temporary folder.');
            break;
        case UPLOAD_ERR_CANT_WRITE:
            sendJsonResponse(false, 'Failed to write file to disk.');
            break;
        case UPLOAD_ERR_EXTENSION:
            sendJsonResponse(false, 'A PHP extension stopped the file upload.');
            break;
        default:
            sendJsonResponse(false, 'Unknown upload error occurred.');
            break;
    }
}

$tmpFilePath = $file['tmp_name'];

$imageInfo = getimagesize($tmpFilePath);
if ($imageInfo === false) {
    sendJsonResponse(false, 'Uploaded file is not a valid image.');
}

$mimeType = $imageInfo['mime'];
if (!in_array($mimeType, ALLOWED_TYPES)) {
    sendJsonResponse(false, 'Unsupported image type. Only JPEG, PNG, and GIF are allowed.');
}

if (!is_dir(THUMBNAIL_DIR)) {
    if (!mkdir(THUMBNAIL_DIR, 0755, true)) {
        sendJsonResponse(false, 'Failed to create thumbnail directory. Check permissions.');
    }
}

$image = null;
switch ($mimeType) {
    case 'image/jpeg':
        $image = imagecreatefromjpeg($tmpFilePath);
        break;
    case 'image/png':
        $image = imagecreatefrompng($tmpFilePath);
        break;
    case 'image/gif':
        $image = imagecreatefromgif($tmpFilePath);
        break;
}

if (!$image) {
    sendJsonResponse(false, 'Could not create image resource from uploaded file.');
}

$originalWidth = imagesx($image);
$originalHeight = imagesy($image);

$newWidth = $originalWidth;
$newHeight = $originalHeight;

if ($originalWidth > MAX_THUMB_WIDTH || $originalHeight > MAX_THUMB_HEIGHT) {
    $ratio = $originalWidth / $originalHeight;
    if (MAX_THUMB_WIDTH / MAX_THUMB_HEIGHT > $ratio) {
        $newHeight = MAX_THUMB_HEIGHT;
        $newWidth = (int) ($newHeight * $ratio);
    } else {
        $newWidth = MAX_THUMB_WIDTH;
        $newHeight = (int) ($newWidth / $ratio);
    }
}

$thumb = imagecreatetruecolor($newWidth, $newHeight);

if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
    imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
}

imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

$uniqueFilename = uniqid('thumb_', true);
$thumbnailPath = THUMBNAIL_DIR . $uniqueFilename;

switch ($mimeType) {
    case 'image/jpeg':
        $thumbnailPath .= '.jpeg';
        if (!imagejpeg($thumb, $thumbnailPath, 90)) {
            sendJsonResponse(false, 'Failed to save JPEG thumbnail.');
        }
        break;
    case 'image/png':
        $thumbnailPath .= '.png';
        if (!imagepng($thumb, $thumbnailPath)) {
            sendJsonResponse(false, 'Failed to save PNG thumbnail.');
        }
        break;
    case 'image/gif':
        $thumbnailPath .= '.gif';
        if (!imagegif($thumb, $thumbnailPath)) {
            sendJsonResponse(false, 'Failed to save GIF thumbnail.');
        }
        break;
}

imagedestroy($image);
imagedestroy($thumb);

$thumbnailUrl = '/' . THUMBNAIL_DIR . basename($thumbnailPath);

sendJsonResponse(true, 'Thumbnail generated successfully.', ['thumbnail_url' => $thumbnailUrl]);

?>