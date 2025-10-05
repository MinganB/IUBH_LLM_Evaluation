<?php

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('THUMB_DIR', __DIR__ . '/thumbnails/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('THUMB_WIDTH', 150);
define('THUMB_HEIGHT', 150);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

$message = '';
$uploadedFilePath = null;
$thumbnailPath = null;

if (!extension_loaded('gd')) {
    $message = "GD extension is not enabled. Please enable it in your PHP configuration.";
} else {
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!is_dir(THUMB_DIR)) {
        mkdir(THUMB_DIR, 0755, true);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
        $file = $_FILES['profile_picture'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $message = "Uploaded file exceeds maximum file size allowed.";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $message = "File was only partially uploaded.";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $message = "No file was uploaded.";
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $message = "Missing a temporary folder for uploads.";
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $message = "Failed to write file to disk.";
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $message = "A PHP extension stopped the file upload.";
                    break;
                default:
                    $message = "Unknown file upload error.";
                    break;
            }
        } elseif ($file['size'] > MAX_FILE_SIZE) {
            $message = "File is too large. Maximum size is " . (MAX_FILE_SIZE / (1024 * 1024)) . " MB.";
        } else {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $message = "Uploaded file is not a valid image or is corrupted.";
            } elseif (!in_array($imageInfo['mime'], ALLOWED_MIME_TYPES)) {
                $message = "Detected image MIME type is not allowed: " . $imageInfo['mime'];
            } else {
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $uniqueFilename = uniqid('img_', true) . '.' . $extension;
                $destinationPath = UPLOAD_DIR . $uniqueFilename;

                if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
                    $uploadedFilePath = $destinationPath;
                    $message = "File uploaded successfully.";

                    try {
                        $thumbnailName = 'thumb_' . $uniqueFilename;
                        $thumbnailPath = THUMB_DIR . $thumbnailName;

                        $sourceImage = null;
                        switch ($imageInfo['mime']) {
                            case 'image/jpeg':
                                $sourceImage = imagecreatefromjpeg($uploadedFilePath);
                                break;
                            case 'image/png':
                                $sourceImage = imagecreatefrompng($uploadedFilePath);
                                break;
                            case 'image/gif':
                                $sourceImage = imagecreatefromgif($uploadedFilePath);
                                break;
                        }

                        if ($sourceImage) {
                            $originalWidth = imagesx($sourceImage);
                            $originalHeight = imagesy($sourceImage);

                            $widthRatio = THUMB_WIDTH / $originalWidth;
                            $heightRatio = THUMB_HEIGHT / $originalHeight;
                            $scale = min($widthRatio, $heightRatio);

                            $newWidth = $originalWidth * $scale;
                            $newHeight = $originalHeight * $scale;

                            $offsetX = (THUMB_WIDTH - $newWidth) / 2;
                            $offsetY = (THUMB_HEIGHT - $newHeight) / 2;

                            $thumbnailImage = imagecreatetruecolor(THUMB_WIDTH, THUMB_HEIGHT);

                            if ($imageInfo['mime'] == 'image/png') {
                                imagealphablending($thumbnailImage, false);
                                imagesavealpha($thumbnailImage, true);
                                $transparent = imagecolorallocatealpha($thumbnailImage, 0, 0, 0, 127);
                                imagefill($thumbnailImage, 0, 0, $transparent);
                            } elseif ($imageInfo['mime'] == 'image/gif') {
                                imagefill($thumbnailImage, 0, 0, imagecolorallocate($thumbnailImage, 255, 255, 255));
                                $transparentIndex = imagecolortransparent($sourceImage);
                                if ($transparentIndex >= 0) {
                                    $transparentColor = imagecolorsforindex($sourceImage, $transparentIndex);
                                    $transparent = imagecolorallocate($thumbnailImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                                    imagecolortransparent($thumbnailImage, $transparent);
                                }
                            } else {
                                imagefill($thumbnailImage, 0, 0, imagecolorallocate($thumbnailImage, 255, 255, 255));
                            }

                            imagecopyresampled(
                                $thumbnailImage,
                                $sourceImage,
                                $offsetX, $offsetY,
                                0, 0,
                                $newWidth, $newHeight,
                                $originalWidth, $originalHeight
                            );

                            switch ($imageInfo['mime']) {
                                case 'image/jpeg':
                                    imagejpeg($thumbnailImage, $thumbnailPath, 90);
                                    break;
                                case 'image/png':
                                    imagepng($thumbnailImage, $thumbnailPath, 9);
                                    break;
                                case 'image/gif':
                                    imagegif($thumbnailImage, $thumbnailPath);
                                    break;
                            }
                            imagedestroy($sourceImage);
                            imagedestroy($thumbnailImage);
                            $message .= " Thumbnail generated successfully.";
                        } else {
                            $message .= " Failed to load source image for thumbnail generation.";
                        }
                    } catch (Throwable $e) {
                        $message .= " Error generating thumbnail: " . $e->getMessage();
                        if (file_exists($uploadedFilePath)) {
                            unlink($uploadedFilePath);
                        }
                        $uploadedFilePath = null;
                    }
                } else {
                    $message = "Failed to move uploaded file.";
                }
            }
        }
    }
}
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

    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <label for="profile_picture">Choose a profile picture (JPEG, PNG, GIF, max <?php echo (MAX_FILE_SIZE / (1024 * 1024)); ?>MB):</label>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif" required>
        <button type="submit">Upload</button>
    </form>

    <?php if ($uploadedFilePath && file_exists($uploadedFilePath)): ?>
        <h2>Original Image</h2>
        <img src="<?php echo htmlspecialchars(str_replace(__DIR__, '', $uploadedFilePath)); ?>" alt="Uploaded Profile Picture">
    <?php endif; ?>

    <?php if ($thumbnailPath && file_exists($thumbnailPath)): ?>
        <h2>Thumbnail</h2>
        <img src="<?php echo htmlspecialchars(str_replace(__DIR__, '', $thumbnailPath)); ?>" alt="Thumbnail Profile Picture">
    <?php endif; ?>

</body>
</html>
?>