<?php
// File: public/index.php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Picture Upload</title>
</head>
<body>
    <h1>Upload Profile Picture</h1>
    <form action="handlers/upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_picture">Choose Image:</label>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif" required>
        <br><br>
        <button type="submit">Upload</button>
    </form>
</body>
</html>

<?php
// File: handlers/upload_handler.php

header('Content-Type: application/json');

define('UPLOAD_BASE_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_DIR_ORIGINAL', UPLOAD_BASE_DIR . 'original/');
define('UPLOAD_DIR_THUMBNAILS', UPLOAD_BASE_DIR . 'thumbnails/');
define('THUMBNAIL_SIZE', 150);

$response = ['success' => false, 'message' => ''];

if (!is_dir(UPLOAD_DIR_ORIGINAL)) {
    mkdir(UPLOAD_DIR_ORIGINAL, 0755, true);
}
if (!is_dir(UPLOAD_DIR_THUMBNAILS)) {
    mkdir(UPLOAD_DIR_THUMBNAILS, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
    $response['message'] = 'No file uploaded.';
    echo json_encode($response);
    exit;
}

$file = $_FILES['profile_picture'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'File upload failed. Error code: ' . $file['error'];
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMessage = 'Uploaded file exceeds maximum file size.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMessage = 'File was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMessage = 'Missing a temporary folder.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMessage = 'Failed to write file to disk.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorMessage = 'A PHP extension stopped the file upload.';
            break;
    }
    $response['message'] = $errorMessage;
    echo json_encode($response);
    exit;
}

$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMimeTypes)) {
    $response['message'] = 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.';
    echo json_encode($response);
    exit;
}

$imageType = exif_imagetype($file['tmp_name']);
$fileExtension = image_type_to_extension($imageType, false);

if (empty($fileExtension)) {
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
}

$uniqueId = uniqid('', true);
$originalFileName = $uniqueId . '.' . $fileExtension;
$thumbnailFileName = $uniqueId . '_thumb.' . $fileExtension;

$originalFilePath = UPLOAD_DIR_ORIGINAL . $originalFileName;
$thumbnailFilePath = UPLOAD_DIR_THUMBNAILS . $thumbnailFileName;

if (!move_uploaded_file($file['tmp_name'], $originalFilePath)) {
    $response['message'] = 'Failed to move uploaded file.';
    echo json_encode($response);
    exit;
}

try {
    list($width, $height) = getimagesize($originalFilePath);

    $originalImage = null;
    switch ($mimeType) {
        case 'image/jpeg':
            $originalImage = imagecreatefromjpeg($originalFilePath);
            break;
        case 'image/png':
            $originalImage = imagecreatefrompng($originalFilePath);
            break;
        case 'image/gif':
            $originalImage = imagecreatefromgif($originalFilePath);
            break;
        default:
            throw new Exception('Unsupported image type for thumbnail generation.');
    }

    if ($originalImage === false) {
        throw new Exception('Could not create image resource from uploaded file.');
    }

    $aspectRatio = $width / $height;
    $thumbWidth = THUMBNAIL_SIZE;
    $thumbHeight = THUMBNAIL_SIZE;

    if ($width > $height) {
        $thumbHeight = THUMBNAIL_SIZE / $aspectRatio;
    } else {
        $thumbWidth = THUMBNAIL_SIZE * $aspectRatio;
    }

    $thumbWidth = (int)$thumbWidth;
    $thumbHeight = (int)$thumbHeight;

    $thumbnailImage = imagecreatetruecolor($thumbWidth, $thumbHeight);

    if ($mimeType == 'image/png') {
        imagealphablending($thumbnailImage, false);
        imagesavealpha($thumbnailImage, true);
        $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
        imagefilledrectangle($thumbnailImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
    } elseif ($mimeType == 'image/gif') {
        $trnprt_indx = imagecolortransparent($originalImage);
        if ($trnprt_indx >= 0) {
            $trnprt_color = imagecolorsforindex($originalImage, $trnprt_indx);
            $trnprt_indx = imagecolorallocate($thumbnailImage, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
            imagefill($thumbnailImage, 0, 0, $trnprt_indx);
            imagecolortransparent($thumbnailImage, $trnprt_indx);
        }
    }

    imagecopyresampled($thumbnailImage, $originalImage, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

    $thumbnailSaved = false;
    switch ($mimeType) {
        case 'image/jpeg':
            $thumbnailSaved = imagejpeg($thumbnailImage, $thumbnailFilePath, 90);
            break;
        case 'image/png':
            $thumbnailSaved = imagepng($thumbnailImage, $thumbnailFilePath, 9);
            break;
        case 'image/gif':
            $thumbnailSaved = imagegif($thumbnailImage, $thumbnailFilePath);
            break;
    }

    imagedestroy($originalImage);
    imagedestroy($thumbnailImage);

    if (!$thumbnailSaved) {
        throw new Exception('Failed to save thumbnail.');
    }

    $response['success'] = true;
    $response['message'] = 'Profile picture uploaded and thumbnail generated successfully.';
    $response['original_filename'] = $originalFileName;
    $response['thumbnail_filename'] = $thumbnailFileName;

} catch (Exception $e) {
    if (file_exists($originalFilePath)) {
        unlink($originalFilePath);
    }
    $response['message'] = 'Failed to generate thumbnail: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>