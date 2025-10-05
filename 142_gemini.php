<?php // classes/ImageProcessor.php

class ImageProcessor
{
    private const ALLOWED_IMAGE_TYPES = [
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_GIF,
        IMAGETYPE_WEBP
    ];

    public function __construct()
    {
        if (!extension_loaded('gd')) {
            throw new Exception('GD library is not enabled. Image processing is not possible.');
        }
    }

    public function saveOriginal(array $file, string $filename): ?string
    {
        $targetDir = UPLOAD_ORIGINALS_DIR;
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return null;
            }
        }

        $targetPath = $targetDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return null;
        }

        return $targetPath;
    }

    public function generateThumbnail(string $originalImagePath, string $thumbnailFilename, int $maxWidth, int $maxHeight): ?string
    {
        $imageInfo = getimagesize($originalImagePath);
        if ($imageInfo === false) {
            return null;
        }

        $imageType = $imageInfo[2];

        if (!in_array($imageType, self::ALLOWED_IMAGE_TYPES)) {
            return null;
        }

        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];

        $ratioW = $maxWidth / $originalWidth;
        $ratioH = $maxHeight / $originalHeight;
        $ratio = min($ratioW, $ratioH);

        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        $srcImage = $this->createImageFrom($originalImagePath, $imageType);
        if ($srcImage === false) {
            return null;
        }

        $thumbImage = imagecreatetruecolor($newWidth, $newHeight);
        if ($imageType == IMAGETYPE_PNG || $imageType == IMAGETYPE_GIF || $imageType == IMAGETYPE_WEBP) {
            imagealphablending($thumbImage, false);
            imagesavealpha($thumbImage, true);
            $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($thumbImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        $targetDir = UPLOAD_THUMBNAILS_DIR;
        if (!is_dir($targetDir)) {
            if (!mkdir($targetDir, 0755, true)) {
                return null;
            }
        }

        $thumbnailPath = $targetDir . '/' . $thumbnailFilename;

        $saveResult = $this->saveImageTo($thumbImage, $thumbnailPath, $imageType);

        imagedestroy($srcImage);
        imagedestroy($thumbImage);

        return $saveResult ? $thumbnailPath : null;
    }

    private function createImageFrom(string $path, int $imageType)
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp($path);
            default:
                return false;
        }
    }

    private function saveImageTo($image, string $path, int $imageType): bool
    {
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagejpeg($image, $path, 90);
            case IMAGETYPE_PNG:
                return imagepng($image, $path, 9);
            case IMAGETYPE_GIF:
                return imagegif($image, $path);
            case IMAGETYPE_WEBP:
                return imagewebp($image, $path, 90);
            default:
                return false;
        }
    }
}
<?php // handlers/upload_image.php

const THUMBNAIL_WIDTH = 150;
const THUMBNAIL_HEIGHT = 150;
const MAX_FILE_SIZE = 5 * 1024 * 1024;

require_once BASE_PATH . '/classes/ImageProcessor.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    try {
        $file = $_FILES['profile_picture'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessage = '';
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errorMessage = 'The uploaded file exceeds the maximum file size allowed by the server configuration.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errorMessage = 'The uploaded file was only partially uploaded.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errorMessage = 'No file was uploaded.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $errorMessage = 'Missing a temporary folder for uploads.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $errorMessage = 'Failed to write file to disk. Check permissions.';
                    break;
                case UPLOAD_ERR_EXTENSION:
                    $errorMessage = 'A PHP extension stopped the file upload. Check your php.ini.';
                    break;
                default:
                    $errorMessage = 'An unknown file upload error occurred.';
                    break;
            }
            throw new Exception($errorMessage);
        }

        if ($file['size'] == 0 || empty($file['tmp_name'])) {
            throw new Exception('Uploaded file is empty or invalid.');
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File size exceeds the allowed limit ('.(MAX_FILE_SIZE / (1024*1024)).' MB).');
        }

        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Uploaded file is not a valid image.');
        }

        $imageType = $imageInfo[2];
        $allowedImageTypes = [
            IMAGETYPE_JPEG,
            IMAGETYPE_PNG,
            IMAGETYPE_GIF,
            IMAGETYPE_WEBP
        ];

        if (!in_array($imageType, $allowedImageTypes)) {
            throw new Exception('Only JPEG, PNG, GIF, and WebP images are allowed.');
        }

        $fileExtension = image_type_to_extension($imageType, false);
        $uniqueFilename = uniqid('img_', true) . '.' . $fileExtension;
        $thumbnailFilename = 'thumb_' . $uniqueFilename;

        $processor = new ImageProcessor();

        $originalPath = $processor->saveOriginal($file, $uniqueFilename);

        if ($originalPath === null) {
            throw new Exception('Failed to save the original image.');
        }

        $thumbnailPath = $processor->generateThumbnail($originalPath, $thumbnailFilename, THUMBNAIL_WIDTH, THUMBNAIL_HEIGHT);

        if ($thumbnailPath === null) {
            if (file_exists($originalPath)) {
                unlink($originalPath);
            }
            throw new Exception('Failed to generate thumbnail.');
        }

        $_SESSION['upload_message'] = 'Image uploaded and thumbnail generated successfully!';
        $_SESSION['upload_status'] = 'success';
        $_SESSION['original_image_url'] = '/uploads/originals/' . $uniqueFilename;
        $_SESSION['thumbnail_image_url'] = '/uploads/thumbnails/' . $thumbnailFilename;

    } catch (Exception $e) {
        $_SESSION['upload_message'] = 'Upload failed: ' . $e->getMessage();
        $_SESSION['upload_status'] = 'error';
    }
} else {
    $_SESSION['upload_message'] = 'Invalid request or no file uploaded.';
    $_SESSION['upload_status'] = 'error';
}

header('Location: /upload_form.php');
exit;
<?php // public/process_upload.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_BASE_DIR', BASE_PATH . '/public/uploads');
define('UPLOAD_ORIGINALS_DIR', UPLOAD_BASE_DIR . '/originals');
define('UPLOAD_THUMBNAILS_DIR', UPLOAD_BASE_DIR . '/thumbnails');

require_once BASE_PATH . '/handlers/upload_image.php';
<?php // public/upload_form.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
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

    <?php
    if (isset($_SESSION['upload_message'])) {
        $message = htmlspecialchars($_SESSION['upload_message']);
        $status = $_SESSION['upload_status'] ?? 'info';
        echo '<div class="' . $status . '">' . $message . '</div>';
        unset($_SESSION['upload_message']);
        unset($_SESSION['upload_status']);

        if ($status === 'success') {
            if (isset($_SESSION['original_image_url'])) {
                echo '<h2>Original Image</h2>';
                echo '<img src="' . htmlspecialchars($_SESSION['original_image_url']) . '" alt="Original Profile Picture">';
                unset($_SESSION['original_image_url']);
            }
            if (isset($_SESSION['thumbnail_image_url'])) {
                echo '<h2>Thumbnail Image</h2>';
                echo '<img src="' . htmlspecialchars($_SESSION['thumbnail_image_url']) . '" alt="Thumbnail Profile Picture">';
                unset($_SESSION['thumbnail_image_url']);
            }
        }
    }
    ?>

    <form action="/process_upload.php" method="POST" enctype="multipart/form-data">
        <label for="profile_picture">Choose Profile Picture:</label>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif, image/webp" required>
        <br><br>
        <button type="submit">Upload Image</button>
    </form>
</body>
</html>
?>