<?php
// File: /classes/ImageProcessor.php

class ImageProcessor {

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif'
    ];
    private const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
    private const THUMBNAIL_WIDTH = 150;
    private const THUMBNAIL_HEIGHT = 150;

    /**
     * Processes an uploaded image file, saves the original, and generates a thumbnail.
     *
     * @param array $file The entry from $_FILES representing the uploaded file.
     * @param string $originalDir The directory path to save original images.
     * @param string $thumbnailDir The directory path to save thumbnail images.
     * @return array|null An array containing paths and filenames on success, or null on failure.
     */
    public static function processImageUpload(array $file, string $originalDir, string $thumbnailDir): ?array {
        if (!self::validateUpload($file)) {
            return null;
        }

        if (!is_dir($originalDir)) {
            mkdir($originalDir, 0755, true);
        }
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false || !in_array($imageInfo['mime'], self::ALLOWED_MIME_TYPES)) {
            return null;
        }

        $extension = image_type_to_extension($imageInfo[2], false);
        $uniqueId = uniqid('', true) . bin2hex(random_bytes(8));
        $originalFileName = $uniqueId . '.' . $extension;
        $thumbnailFileName = $uniqueId . '_thumb.' . $extension;

        $originalFilePath = rtrim($originalDir, '/') . '/' . $originalFileName;
        $thumbnailFilePath = rtrim($thumbnailDir, '/') . '/' . $thumbnailFileName;

        if (!move_uploaded_file($file['tmp_name'], $originalFilePath)) {
            return null;
        }

        if (!self::createThumbnail($originalFilePath, $thumbnailFilePath, $imageInfo['mime'])) {
            unlink($originalFilePath);
            return null;
        }

        return [
            'original_path' => $originalFilePath,
            'thumbnail_path' => $thumbnailFilePath,
            'original_filename' => $originalFileName,
            'thumbnail_filename' => $thumbnailFileName,
        ];
    }

    /**
     * Validates the uploaded file based on PHP upload errors, size, and type.
     *
     * @param array $file The entry from $_FILES.
     * @return bool True if validation passes, false otherwise.
     */
    private static function validateUpload(array $file): bool {
        if (!isset($file['error']) || is_array($file['error'])) {
            return false;
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return false;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return false;
            default:
                return false;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return false;
        }

        return true;
    }

    /**
     * Creates a square thumbnail from a source image, cropping to fit.
     *
     * @param string $sourcePath Path to the original image.
     * @param string $destinationPath Path to save the thumbnail.
     * @param string $mimeType MIME type of the image.
     * @return bool True on success, false on failure.
     */
    private static function createThumbnail(string $sourcePath, string $destinationPath, string $mimeType): bool {
        list($sourceWidth, $sourceHeight) = getimagesize($sourcePath);

        $sourceImage = null;
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                return false;
        }

        if ($sourceImage === false) {
            return false;
        }

        $thumbWidth = self::THUMBNAIL_WIDTH;
        $thumbHeight = self::THUMBNAIL_HEIGHT;

        $ratio = max($thumbWidth / $sourceWidth, $thumbHeight / $sourceHeight);
        $newWidth = $sourceWidth * $ratio;
        $newHeight = $sourceHeight * $ratio;

        $thumbnail = imagecreatetruecolor($thumbWidth, $thumbHeight);
        if ($thumbnail === false) {
            imagedestroy($sourceImage);
            return false;
        }

        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $thumbWidth, $thumbHeight, $transparent);
        }

        imagecopyresampled(
            $thumbnail,
            $sourceImage,
            ($thumbWidth - $newWidth) / 2,
            ($thumbHeight - $newHeight) / 2,
            0,
            0,
            $newWidth,
            $newHeight,
            $sourceWidth,
            $sourceHeight
        );

        $saved = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $saved = imagejpeg($thumbnail, $destinationPath, 90);
                break;
            case 'image/png':
                $saved = imagepng($thumbnail, $destinationPath, 9);
                break;
            case 'image/gif':
                $saved = imagegif($thumbnail, $destinationPath);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        return $saved;
    }
}

<?php
// File: /handlers/upload_handler.php

require_once __DIR__ . '/../classes/ImageProcessor.php';

// Define upload directories relative to the project root.
// Assuming a project structure like:
// PROJECT_ROOT/
// |-- classes/
// |-- handlers/
// |-- public/
// |-- uploads/
//     |-- originals/
//     |-- thumbnails/
define('UPLOAD_BASE_DIR', __DIR__ . '/../uploads');
define('ORIGINAL_IMAGES_DIR', UPLOAD_BASE_DIR . '/originals');
define('THUMBNAILS_DIR', UPLOAD_BASE_DIR . '/thumbnails');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /public/upload_form.php?status=error&message=' . urlencode('Invalid request method.'));
    exit;
}

if (empty($_FILES['profile_picture'])) {
    header('Location: /public/upload_form.php?status=error&message=' . urlencode('No file uploaded or invalid file input name.'));
    exit;
}

$uploadResult = ImageProcessor::processImageUpload(
    $_FILES['profile_picture'],
    ORIGINAL_IMAGES_DIR,
    THUMBNAILS_DIR
);

if ($uploadResult) {
    $originalFilename = basename($uploadResult['original_path']);
    $thumbnailFilename = basename($uploadResult['thumbnail_path']);

    header('Location: /public/upload_form.php?status=success&original=' . urlencode($originalFilename) . '&thumbnail=' . urlencode($thumbnailFilename));
    exit;
} else {
    header('Location: /public/upload_form.php?status=error&message=' . urlencode('Image upload and thumbnail generation failed. Please check file type and size.'));
    exit;
}

<?php
// File: /public/upload_form.php

$status = $_GET['status'] ?? '';
$message = $_GET['message'] ?? '';
$original_filename = $_GET['original'] ?? '';
$thumbnail_filename = $_GET['thumbnail'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <h1>Upload Profile Picture</h1>

    <?php if ($status === 'success'): ?>
        <p style="color: green;">Image uploaded successfully!</p>
        <?php if ($original_filename && $thumbnail_filename): ?>
            <p>Original file: <?php echo htmlspecialchars($original_filename); ?></p>
            <p>Thumbnail file: <?php echo htmlspecialchars($thumbnail_filename); ?></p>
            <p>
                <img src="/uploads/thumbnails/<?php echo htmlspecialchars($thumbnail_filename); ?>" alt="Thumbnail" style="max-width: 150px; border: 1px solid #ccc;">
            </p>
        <?php endif; ?>
    <?php elseif ($status === 'error'): ?>
        <p style="color: red;">Error: <?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="/handlers/upload_handler.php" method="post" enctype="multipart/form-data">
        <label for="profile_picture">Choose Profile Picture:</label>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif" required>
        <br><br>
        <button type="submit">Upload Image</button>
    </form>
</body>
</html>
?>