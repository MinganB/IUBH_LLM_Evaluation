<?php

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('THUMB_DIR', __DIR__ . '/thumbnails/');
define('THUMB_WIDTH', 100);
define('THUMB_HEIGHT', 100);

function createDirectories() {
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!is_dir(THUMB_DIR)) {
        mkdir(THUMB_DIR, 0755, true);
    }
}

createDirectories();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $message = 'Error: File size exceeds the allowed limit.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = 'Error: File was only partially uploaded.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = 'Error: No file was uploaded.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = 'Error: Missing a temporary folder.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = 'Error: Failed to write file to disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = 'Error: A PHP extension stopped the file upload.';
                break;
            default:
                $message = 'Error: Unknown upload error.';
        }
    } else {
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes)) {
            $message = 'Error: Invalid file type. Only JPG, PNG, and GIF images are allowed.';
        } elseif (!is_writable(UPLOAD_DIR) || !is_writable(THUMB_DIR)) {
            $message = 'Error: Upload or thumbnail directory is not writable.';
        } else {
            $originalFileName = pathinfo($file['name'], PATHINFO_FILENAME);
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $uniqueFileName = md5(uniqid(rand(), true)) . '.' . $extension;
            $uploadPath = UPLOAD_DIR . $uniqueFileName;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $thumbnailPath = THUMB_DIR . 'thumb_' . $uniqueFileName;
                $originalImage = null;

                switch ($mimeType) {
                    case 'image/jpeg':
                        $originalImage = imagecreatefromjpeg($uploadPath);
                        break;
                    case 'image/png':
                        $originalImage = imagecreatefrompng($uploadPath);
                        break;
                    case 'image/gif':
                        $originalImage = imagecreatefromgif($uploadPath);
                        break;
                }

                if ($originalImage) {
                    $originalWidth = imagesx($originalImage);
                    $originalHeight = imagesy($originalImage);

                    $ratio = min(THUMB_WIDTH / $originalWidth, THUMB_HEIGHT / $originalHeight);

                    $newWidth = (int)($originalWidth * $ratio);
                    $newHeight = (int)($originalHeight * $ratio);

                    $thumbImage = imagecreatetruecolor(THUMB_WIDTH, THUMB_HEIGHT);

                    if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
                        imagealphablending($thumbImage, false);
                        imagesavealpha($thumbImage, true);
                        $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
                        imagefilledrectangle($thumbImage, 0, 0, THUMB_WIDTH, THUMB_HEIGHT, $transparent);
                    } else {
                        $bgColor = imagecolorallocate($thumbImage, 255, 255, 255);
                        imagefill($thumbImage, 0, 0, $bgColor);
                    }

                    $offsetX = (THUMB_WIDTH - $newWidth) / 2;
                    $offsetY = (THUMB_HEIGHT - $newHeight) / 2;

                    imagecopyresampled($thumbImage, $originalImage, $offsetX, $offsetY, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

                    $thumbnailSaved = false;
                    switch ($mimeType) {
                        case 'image/jpeg':
                            $thumbnailSaved = imagejpeg($thumbImage, $thumbnailPath, 90);
                            break;
                        case 'image/png':
                            $thumbnailSaved = imagepng($thumbImage, $thumbnailPath);
                            break;
                        case 'image/gif':
                            $thumbnailSaved = imagegif($thumbImage, $thumbnailPath);
                            break;
                    }

                    imagedestroy($originalImage);
                    imagedestroy($thumbImage);

                    if ($thumbnailSaved) {
                        $message = 'Image uploaded and thumbnail generated successfully! Original: ' . basename($uploadPath) . ', Thumbnail: ' . basename($thumbnailPath);
                    } else {
                        unlink($uploadPath);
                        $message = 'Error: Could not save thumbnail.';
                    }
                } else {
                    unlink($uploadPath);
                    $message = 'Error: Could not process image (GD library issue or unsupported format).';
                }
            } else {
                $message = 'Error: Failed to move uploaded file.';
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
    <title>Profile Picture Uploader</title>
</head>
<body>
    <h1>Upload Your Profile Picture</h1>

    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <label for="profile_picture">Choose Image:</label>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif">
        <br><br>
        <button type="submit">Upload Image</button>
    </form>

    <h2>Uploaded Images (Latest)</h2>
    <?php
    $latestFiles = glob(UPLOAD_DIR . '*');
    usort($latestFiles, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $displayCount = 3;
    $displayed = 0;

    foreach ($latestFiles as $filePath) {
        if (is_file($filePath) && $displayed < $displayCount) {
            $fileName = basename($filePath);
            $thumbnailFileName = 'thumb_' . $fileName;
            $thumbnailPath = THUMB_DIR . $thumbnailFileName;

            if (file_exists($thumbnailPath)) {
                echo '<div style="margin-bottom: 10px; border: 1px solid #ccc; padding: 5px; display: inline-block;">';
                echo '<strong>Original:</strong><br>';
                echo '<img src="' . str_replace(__DIR__, '', UPLOAD_DIR) . htmlspecialchars($fileName) . '" alt="Original Image" style="max-width: 150px; height: auto;"><br>';
                echo '<strong>Thumbnail:</strong><br>';
                echo '<img src="' . str_replace(__DIR__, '', THUMB_DIR) . htmlspecialchars($thumbnailFileName) . '" alt="Thumbnail" style="width: ' . THUMB_WIDTH . 'px; height: ' . THUMB_HEIGHT . 'px; object-fit: contain;"><br>';
                echo '</div> ';
                $displayed++;
            }
        }
    }

    if ($displayed === 0) {
        echo '<p>No images uploaded yet.</p>';
    }
    ?>

</body>
</html>
?>