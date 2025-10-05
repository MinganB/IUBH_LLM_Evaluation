<?php
// public/index.php
// This file serves the HTML form for image upload.
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Profile Picture</title>
</head>
<body>
    <h1>Upload Your Profile Picture</h1>
    <form action="../handlers/upload_handler.php" method="post" enctype="multipart/form-data">
        <input type="file" name="profile_picture" accept="image/jpeg, image/png, image/gif" required>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
<?php
// handlers/upload_handler.php
// This file handles the uploaded image and generates a thumbnail.

header('Content-Type: application/json');

$appRootDir = dirname(__DIR__);

// Directory for original uploaded images (outside web root for security)
$uploadOriginalsDir = $appRootDir . '/uploads/original';
// Directory for generated thumbnails (inside web root for direct access via URL)
$uploadThumbnailsDir = $appRootDir . '/public/thumbnails';

if (!is_dir($uploadOriginalsDir)) {
    mkdir($uploadOriginalsDir, 0755, true);
}
if (!is_dir($uploadThumbnailsDir)) {
    mkdir($uploadThumbnailsDir, 0755, true);
}

function sendJsonResponse($success, $message = '', $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];
    $errorMessage = isset($_FILES['profile_picture']['error']) && isset($errorMessages[$_FILES['profile_picture']['error']])
                    ? $errorMessages[$_FILES['profile_picture']['error']]
                    : 'An unknown upload error occurred.';
    sendJsonResponse(false, $errorMessage);
}

$file = $_FILES['profile_picture'];

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMimeTypes)) {
    sendJsonResponse(false, 'Unsupported file type. Only JPEG, PNG, and GIF are allowed.');
}

$imageInfo = getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    sendJsonResponse(false, 'Invalid image file.');
}

$width = $imageInfo[0];
$height = $imageInfo[1];
$imageType = $imageInfo[2];

$uniqueId = uniqid('', true);
$extension = image_type_to_extension($imageType, false);
$originalFilename = $uniqueId . '.' . $extension;
$thumbnailFilename = 'thumb_' . $uniqueId . '.' . $extension;

$originalFilePath = $uploadOriginalsDir . '/' . $originalFilename;
$thumbnailFilePath = $uploadThumbnailsDir . '/' . $thumbnailFilename;

if (!move_uploaded_file($file['tmp_name'], $originalFilePath)) {
    sendJsonResponse(false, 'Failed to save the original image.');
}

$sourceImage = null;
switch ($imageType) {
    case IMAGETYPE_JPEG:
        $sourceImage = imagecreatefromjpeg($originalFilePath);
        break;
    case IMAGETYPE_PNG:
        $sourceImage = imagecreatefrompng($originalFilePath);
        break;
    case IMAGETYPE_GIF:
        $sourceImage = imagecreatefromgif($originalFilePath);
        break;
}

if (!$sourceImage) {
    sendJsonResponse(false, 'Failed to create image resource from uploaded file. Original file saved.');
}

$thumbMaxWidth = 200;
$thumbMaxHeight = 200;

$ratio = $width / $height;
if ($thumbMaxWidth / $thumbMaxHeight > $ratio) {
    $newWidth = (int)($thumbMaxHeight * $ratio);
    $newHeight = $thumbMaxHeight;
} else {
    $newHeight = (int)($thumbMaxWidth / $ratio);
    $newWidth = $thumbMaxWidth;
}

$thumb = imagecreatetruecolor($newWidth, $newHeight);

if ($imageType == IMAGETYPE_PNG) {
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
    imagefilledrectangle($thumb, 0, 0, $newWidth, $newHeight, $transparent);
} elseif ($imageType == IMAGETYPE_GIF) {
    $transparentIndex = imagecolortransparent($sourceImage);
    if ($transparentIndex >= 0) {
        $transparentColor = imagecolorsforindex($sourceImage, $transparentIndex);
        $transparentIndex = imagecolorallocate($thumb, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
        imagefill($thumb, 0, 0, $transparentIndex);
        imagecolortransparent($thumb, $transparentIndex);
    }
}

imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

$thumbnailSaved = false;
switch ($imageType) {
    case IMAGETYPE_JPEG:
        $thumbnailSaved = imagejpeg($thumb, $thumbnailFilePath, 90);
        break;
    case IMAGETYPE_PNG:
        $thumbnailSaved = imagepng($thumb, $thumbnailFilePath);
        break;
    case IMAGETYPE_GIF:
        $thumbnailSaved = imagegif($thumb, $thumbnailFilePath);
        break;
}

imagedestroy($sourceImage);
imagedestroy($thumb);

if (!$thumbnailSaved) {
    sendJsonResponse(false, 'Failed to save the thumbnail image. Original file saved.');
}

$thumbnailUrl = '/thumbnails/' . $thumbnailFilename;

sendJsonResponse(true, 'Image uploaded and thumbnail generated successfully.', ['thumbnail_url' => $thumbnailUrl]);
?>