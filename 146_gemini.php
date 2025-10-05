<?php
<!DOCTYPE html>
<html>
<head>
    <title>Upload Image & Generate Thumbnail</title>
    <meta charset="UTF-8">
</head>
<body>

    <h2>Upload Image for Thumbnail Generation</h2>

    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="image">Select Image:</label><br>
        <input type="file" name="image" id="image" accept="image/jpeg, image/png, image/gif, image/webp"><br><br>
        <input type="submit" value="Upload Image">
    </form>

</body>
</html>

<?php

$thumbnailDir = 'thumbnails/';
$thumbnailMaxWidth = 200;
$thumbnailMaxHeight = 200;
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxFileSize = 5 * 1024 * 1024;

if (!is_dir($thumbnailDir)) {
    mkdir($thumbnailDir, 0755, true);
}

if (!is_writable($thumbnailDir)) {
    exit('Error: Thumbnail directory is not writable.');
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    switch ($_FILES['image']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            exit('Error: Uploaded file exceeds maximum file size.');
        case UPLOAD_ERR_PARTIAL:
            exit('Error: The uploaded file was only partially uploaded.');
        case UPLOAD_ERR_NO_FILE:
            exit('Error: No file was uploaded.');
        case UPLOAD_ERR_NO_TMP_DIR:
            exit('Error: Missing a temporary folder.');
        case UPLOAD_ERR_CANT_WRITE:
            exit('Error: Failed to write file to disk.');
        case UPLOAD_ERR_EXTENSION:
            exit('Error: A PHP extension stopped the file upload.');
        default:
            exit('Error: Unknown upload error.');
    }
}

$fileTmpPath = $_FILES['image']['tmp_name'];
$fileName = $_FILES['image']['name'];
$fileSize = $_FILES['image']['size'];

if ($fileSize > $maxFileSize) {
    exit('Error: File size exceeds the maximum allowed ' . ($maxFileSize / (1024 * 1024)) . 'MB.');
}

$imageInfo = @getimagesize($fileTmpPath);
if ($imageInfo === false) {
    exit('Error: File is not a valid image.');
}

$fileMimeType = $imageInfo['mime'];
if (!in_array($fileMimeType, $allowedMimeTypes)) {
    exit('Error: Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
}

$originalWidth = $imageInfo[0];
$originalHeight = $imageInfo[1];
$imageType = $imageInfo[2];

$sourceImage = null;
switch ($imageType) {
    case IMAGETYPE_JPEG:
        $sourceImage = imagecreatefromjpeg($fileTmpPath);
        break;
    case IMAGETYPE_PNG:
        $sourceImage = imagecreatefrompng($fileTmpPath);
        break;
    case IMAGETYPE_GIF:
        $sourceImage = imagecreatefromgif($fileTmpPath);
        break;
    case IMAGETYPE_WEBP:
        if (function_exists('imagecreatefromwebp')) {
            $sourceImage = imagecreatefromwebp($fileTmpPath);
        } else {
            exit('Error: WebP support is not enabled in GD library.');
        }
        break;
    default:
        exit('Error: Unsupported image format.');
}

if ($sourceImage === false) {
    exit('Error: Failed to create image resource from uploaded file.');
}

$fileExtension = image_type_to_extension($imageType, false);
$thumbnailFileName = uniqid('', true) . '_' . md5(basename($fileName) . microtime()) . '.' . $fileExtension;
$thumbnailFilePath = $thumbnailDir . $thumbnailFileName;

$ratio = max($originalWidth / $thumbnailMaxWidth, $originalHeight / $thumbnailMaxHeight);
$newWidth = $originalWidth / $ratio;
$newHeight = $originalHeight / $ratio;

$thumbImage = imagecreatetruecolor((int)$newWidth, (int)$newHeight);

if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
    imagealphablending($thumbImage, false);
    imagesavealpha($thumbImage, true);
    $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
    imagefilledrectangle($thumbImage, 0, 0, (int)$newWidth, (int)$newHeight, $transparent);
}

if (!imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, (int)$newWidth, (int)$newHeight, $originalWidth, $originalHeight)) {
    imagedestroy($sourceImage);
    imagedestroy($thumbImage);
    exit('Error: Failed to resample image.');
}

$saveSuccess = false;
switch ($imageType) {
    case IMAGETYPE_JPEG:
        $saveSuccess = imagejpeg($thumbImage, $thumbnailFilePath, 90);
        break;
    case IMAGETYPE_PNG:
        $saveSuccess = imagepng($thumbImage, $thumbnailFilePath, 9);
        break;
    case IMAGETYPE_GIF:
        $saveSuccess = imagegif($thumbImage, $thumbnailFilePath);
        break;
    case IMAGETYPE_WEBP:
        if (function_exists('imagewebp')) {
            $saveSuccess = imagewebp($thumbImage, $thumbnailFilePath, 80);
        } else {
            exit('Error: WebP support is not enabled in GD library.');
        }
        break;
}

imagedestroy($sourceImage);
imagedestroy($thumbImage);

if ($saveSuccess) {
    echo 'Thumbnail generated and saved successfully: ' . htmlspecialchars($thumbnailFileName);
} else {
    exit('Error: Failed to save thumbnail image.');
}
?>