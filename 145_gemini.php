<?php
// index.php
$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload</title>
</head>
<body>
    <h1>Upload Image for Thumbnail</h1>

    <?php if ($status && $message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="upload_handler.php" method="post" enctype="multipart/form-data">
        <label for="image">Select Image:</label>
        <input type="file" name="image" id="image" accept="image/*" required>
        <br><br>
        <button type="submit">Upload Image</button>
    </form>
</body>
</html>
<?php
// upload_handler.php

$thumbnailDir = __DIR__ . '/thumbnails/';
$maxThumbnailWidth = 150;
$maxThumbnailHeight = 150;
$jpegQuality = 80;

if (!is_dir($thumbnailDir)) {
    mkdir($thumbnailDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['image']['tmp_name'];
    $fileName = $_FILES['image']['name'];

    $imageInfo = getimagesize($fileTmpPath);

    if ($imageInfo === false) {
        header('Location: index.php?status=error&message=' . urlencode('Uploaded file is not a valid image.'));
        exit;
    }

    $mime = $imageInfo['mime'];
    $originalWidth = $imageInfo[0];
    $originalHeight = $imageInfo[1];

    $sourceImage = null;
    switch ($mime) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($fileTmpPath);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($fileTmpPath);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($fileTmpPath);
            break;
        default:
            header('Location: index.php?status=error&message=' . urlencode('Unsupported image type. Only JPEG, PNG, GIF are allowed.'));
            exit;
    }

    if ($sourceImage === false) {
        header('Location: index.php?status=error&message=' . urlencode('Could not process image.'));
        exit;
    }

    $scale = min($maxThumbnailWidth / $originalWidth, $maxThumbnailHeight / $originalHeight);
    $newWidth = (int)($originalWidth * $scale);
    $newHeight = (int)($originalHeight * $scale);

    $thumbnailImage = imagecreatetruecolor($newWidth, $newHeight);

    if ($mime === 'image/png' || $mime === 'image/gif') {
        imagealphablending($thumbnailImage, false);
        imagesavealpha($thumbnailImage, true);
        $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
        imagefilledrectangle($thumbnailImage, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($thumbnailImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    $uniqueFileName = uniqid('thumb_', true) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
    $thumbnailFilePath = $thumbnailDir . $uniqueFileName;

    $saveSuccess = false;
    switch ($mime) {
        case 'image/jpeg':
            $saveSuccess = imagejpeg($thumbnailImage, $thumbnailFilePath, $jpegQuality);
            break;
        case 'image/png':
            $saveSuccess = imagepng($thumbnailImage, $thumbnailFilePath);
            break;
        case 'image/gif':
            $saveSuccess = imagegif($thumbnailImage, $thumbnailFilePath);
            break;
    }

    imagedestroy($sourceImage);
    imagedestroy($thumbnailImage);

    if ($saveSuccess) {
        header('Location: index.php?status=success&message=' . urlencode('Thumbnail generated and saved successfully: ') . urlencode(basename($thumbnailFilePath)));
        exit;
    } else {
        header('Location: index.php?status=error&message=' . urlencode('Failed to save thumbnail.'));
        exit;
    }

} else {
    $errorMessage = 'No file uploaded or an upload error occurred.';
    if (isset($_FILES['image']['error'])) {
        switch ($_FILES['image']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = 'Uploaded file exceeds maximum file size.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = 'File was only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = 'No file was uploaded.';
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
    }
    header('Location: index.php?status=error&message=' . urlencode($errorMessage));
    exit;
}
?>