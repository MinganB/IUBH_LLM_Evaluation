<?php
const UPLOAD_DIR = 'uploads/';
const THUMB_DIR = 'thumbnails/';
const MAX_FILE_SIZE = 5 * 1024 * 1024;
const THUMB_WIDTH = 200;
const THUMB_HEIGHT = 200;

$messages = [];
$uploadedImagePath = null;
$thumbnailPath = null;

foreach ([UPLOAD_DIR, THUMB_DIR] as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            $messages[] = ['type' => 'error', 'text' => "Failed to create directory: " . htmlspecialchars($dir)];
        }
    } elseif (!is_writable($dir)) {
        $messages[] = ['type' => 'error', 'text' => "Directory is not writable: " . htmlspecialchars($dir)];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($messages)) {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $messages[] = ['type' => 'error', 'text' => 'Error during file upload.'];
    } else {
        $file = $_FILES['image'];

        if ($file['size'] > MAX_FILE_SIZE) {
            $messages[] = ['type' => 'error', 'text' => 'File size exceeds the limit of ' . (MAX_FILE_SIZE / (1024 * 1024)) . ' MB.'];
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedMimeTypes)) {
            $messages[] = ['type' => 'error', 'text' => 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed (MIME check).'];
        }

        $imageType = exif_imagetype($file['tmp_name']);
        if ($imageType === false) {
             $messages[] = ['type' => 'error', 'text' => 'Could not determine image type. It might not be a valid image.'];
        } else {
            $imageTypeMap = [
                IMAGETYPE_JPEG => 'image/jpeg',
                IMAGETYPE_PNG => 'image/png',
                IMAGETYPE_GIF => 'image/gif',
                IMAGETYPE_WEBP => 'image/webp',
            ];
            if (!isset($imageTypeMap[$imageType]) || !in_array($imageTypeMap[$imageType], $allowedMimeTypes)) {
                $messages[] = ['type' => 'error', 'text' => 'Unsupported image format detected.'];
            }
        }

        if (empty($messages)) {
            $fileExtension = image_type_to_extension($imageType, false);
            $uniqueFilename = uniqid('img_', true) . '.' . $fileExtension;
            $destinationPath = UPLOAD_DIR . $uniqueFilename;

            if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
                $uploadedImagePath = $destinationPath;
                $messages[] = ['type' => 'success', 'text' => 'Image uploaded successfully.'];

                $thumbnailPath = THUMB_DIR . 'thumb_' . $uniqueFilename;
                $sourceImage = null;

                switch ($imageType) {
                    case IMAGETYPE_JPEG:
                        $sourceImage = imagecreatefromjpeg($destinationPath);
                        break;
                    case IMAGETYPE_PNG:
                        $sourceImage = imagecreatefrompng($destinationPath);
                        break;
                    case IMAGETYPE_GIF:
                        $sourceImage = imagecreatefromgif($destinationPath);
                        break;
                    case IMAGETYPE_WEBP:
                        $sourceImage = imagecreatefromwebp($destinationPath);
                        break;
                }

                if ($sourceImage) {
                    $originalWidth = imagesx($sourceImage);
                    $originalHeight = imagesy($sourceImage);

                    $ratio = min(THUMB_WIDTH / $originalWidth, THUMB_HEIGHT / $originalHeight);
                    $newWidth = (int)($originalWidth * $ratio);
                    $newHeight = (int)($originalHeight * $ratio);

                    $thumbnailImage = imagecreatetruecolor($newWidth, $newHeight);

                    if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF) {
                        imagealphablending($thumbnailImage, false);
                        imagesavealpha($thumbnailImage, true);
                        $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
                        imagefilledrectangle($thumbnailImage, 0, 0, $newWidth, $newHeight, $transparent);
                    }

                    imagecopyresampled($thumbnailImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

                    $thumbnailSaved = false;
                    switch ($imageType) {
                        case IMAGETYPE_JPEG:
                            $thumbnailSaved = imagejpeg($thumbnailImage, $thumbnailPath, 90);
                            break;
                        case IMAGETYPE_PNG:
                            $thumbnailSaved = imagepng($thumbnailImage, $thumbnailPath);
                            break;
                        case IMAGETYPE_GIF:
                            $thumbnailSaved = imagegif($thumbnailImage, $thumbnailPath);
                            break;
                        case IMAGETYPE_WEBP:
                            $thumbnailSaved = imagewebp($thumbnailImage, $thumbnailPath);
                            break;
                    }

                    if ($thumbnailSaved) {
                        $messages[] = ['type' => 'success', 'text' => 'Thumbnail generated successfully.'];
                    } else {
                        $messages[] = ['type' => 'error', 'text' => 'Failed to save thumbnail.'];
                    }

                    imagedestroy($sourceImage);
                    imagedestroy($thumbnailImage);
                } else {
                    $messages[] = ['type' => 'error', 'text' => 'Failed to load source image for thumbnail generation.'];
                }
            } else {
                $messages[] = ['type' => 'error', 'text' => 'Failed to move uploaded file. Check directory permissions.'];
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload and Thumbnail Generator</title>
</head>
<body>
    <h1>Upload Your Image</h1>

    <?php if (!empty($messages)): ?>
        <div class="messages">
            <?php foreach ($messages as $message): ?>
                <p style="color: <?= $message['type'] === 'error' ? 'red' : 'green'; ?>;"><?= htmlspecialchars($message['text']); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <label for="image">Select Image (Max <?= (MAX_FILE_SIZE / (1024 * 1024)); ?> MB, JPG, PNG, GIF, WebP):</label><br>
        <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/gif,image/webp" required><br><br>
        <button type="submit">Upload Image</button>
    </form>

    <?php if ($uploadedImagePath): ?>
        <h2>Uploaded Images</h2>
        <div>
            <h3>Original Image</h3>
            <img src="<?= htmlspecialchars($uploadedImagePath); ?>" alt="Uploaded Image" style="max-width: 400px; height: auto;"><br>
            <p><a href="<?= htmlspecialchars($uploadedImagePath); ?>" target="_blank">View Original</a></p>
        </div>
        <?php if ($thumbnailPath): ?>
        <div>
            <h3>Thumbnail</h3>
            <img src="<?= htmlspecialchars($thumbnailPath); ?>" alt="Thumbnail" style="max-width: <?= THUMB_WIDTH; ?>px; height: auto;"><br>
            <p><a href="<?= htmlspecialchars($thumbnailPath); ?>" target="_blank">View Thumbnail</a></p>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
?>