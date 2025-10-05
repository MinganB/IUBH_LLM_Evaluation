<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <h1>Upload Your Profile Picture</h1>
    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_picture">Choose Image:</label><br>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/png,image/gif" required><br><br>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
<?php

$uploadDir = __DIR__ . '/uploads/';
$thumbnailDir = __DIR__ . '/thumbnails/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!is_dir($thumbnailDir)) {
    mkdir($thumbnailDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                echo "Error: File is too large.";
                break;
            case UPLOAD_ERR_PARTIAL:
                echo "Error: The uploaded file was only partially uploaded.";
                break;
            case UPLOAD_ERR_NO_FILE:
                echo "Error: No file was uploaded.";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                echo "Error: Missing a temporary folder.";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                echo "Error: Failed to write file to disk.";
                break;
            case UPLOAD_ERR_EXTENSION:
                echo "Error: A PHP extension stopped the file upload.";
                break;
            default:
                echo "Error: Unknown upload error.";
                break;
        }
        exit;
    }

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxFileSize = 5 * 1024 * 1024;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimeTypes)) {
        echo "Error: Only JPG, PNG, and GIF images are allowed.";
        exit;
    }

    if ($file['size'] > $maxFileSize) {
        echo "Error: File size exceeds the maximum limit of 5 MB.";
        exit;
    }

    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        echo "Error: The uploaded file is not a valid image.";
        exit;
    }

    $extension = image_type_to_extension($imageInfo[2], false);
    $uniqueId = uniqid();
    $originalFilename = $uniqueId . '.' . $extension;
    $targetFilePath = $uploadDir . $originalFilename;

    if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
        $thumbnailFilename = 'thumb_' . $uniqueId . '.' . $extension;
        $thumbnailFilePath = $thumbnailDir . $thumbnailFilename;

        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $imageType = $imageInfo[2];

        $sourceImage = null;
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($targetFilePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($targetFilePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($targetFilePath);
                break;
            default:
                echo "Error: Unsupported image type for thumbnail generation.";
                unlink($targetFilePath);
                exit;
        }

        if (!$sourceImage) {
            echo "Error: Could not load source image for thumbnail generation.";
            unlink($targetFilePath);
            exit;
        }

        $thumbWidth = 150;
        $thumbHeight = 150;

        $newWidth = $thumbWidth;
        $newHeight = $thumbHeight;

        if ($width > $height) {
            $newHeight = floor($height * ($thumbWidth / $width));
        } else {
            $newWidth = floor($width * ($thumbHeight / $height));
        }

        $thumbImage = imagecreatetruecolor($newWidth, $newHeight);

        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled(
            $thumbImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );

        $thumbnailSaved = false;
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $thumbnailSaved = imagejpeg($thumbImage, $thumbnailFilePath, 80);
                break;
            case IMAGETYPE_PNG:
                $thumbnailSaved = imagepng($thumbImage, $thumbnailFilePath);
                break;
            case IMAGETYPE_GIF:
                $thumbnailSaved = imagegif($thumbImage, $thumbnailFilePath);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbImage);

        if ($thumbnailSaved) {
            echo "Profile picture and thumbnail uploaded successfully!";
        } else {
            echo "Error: Failed to save thumbnail image.";
            unlink($targetFilePath);
        }

    } else {
        echo "Error: Failed to move uploaded file.";
    }
} else {
    echo "Error: Invalid request.";
}

?>