<?php

const UPLOAD_DIR = __DIR__ . '/uploads/';
const THUMB_DIR = __DIR__ . '/thumbnails/';
const LOG_DIR = __DIR__ . '/logs/';
const LOG_FILE = LOG_DIR . 'upload_log.txt';

const MAX_FILE_SIZE = 5 * 1024 * 1024;
const ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
];

const MAX_IMAGE_WIDTH = 1920;
const MAX_IMAGE_HEIGHT = 1080;

const THUMB_WIDTH = 150;
const THUMB_HEIGHT = 150;

function create_dir_if_not_exists(string $path): bool
{
    if (!is_dir($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

if (!create_dir_if_not_exists(UPLOAD_DIR) || !create_dir_if_not_exists(THUMB_DIR) || !create_dir_if_not_exists(LOG_DIR)) {
    error_log("CRITICAL: Failed to create necessary directories. Check permissions for " . __DIR__);
    die("Server error: Unable to setup storage. Please try again later.");
}

require_once __DIR__ . '/vendor/autoload.php';

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

$imageManager = new ImageManager(new Driver());

$uploadMessage = '';
$uploadSuccess = false;

function log_event(string $message, bool $success = false): void
{
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = sprintf("[%s] [IP:%s] [%s] %s" . PHP_EOL, $timestamp, $ip_address, ($success ? 'SUCCESS' : 'FAILURE'), $message);
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $uploadMessage = 'Uploaded file exceeds maximum allowed size.';
                log_event("Upload failed: File size exceeded. PHP error code: " . $file['error']);
                break;
            case UPLOAD_ERR_PARTIAL:
                $uploadMessage = 'File was only partially uploaded.';
                log_event("Upload failed: Partial upload. PHP error code: " . $file['error']);
                break;
            case UPLOAD_ERR_NO_FILE:
                $uploadMessage = 'No file was uploaded.';
                log_event("Upload failed: No file uploaded. PHP error code: " . $file['error']);
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $uploadMessage = 'Server error: Missing a temporary folder.';
                log_event("Upload failed: Missing temporary directory. PHP error code: " . $file['error']);
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $uploadMessage = 'Server error: Failed to write file to disk.';
                log_event("Upload failed: Failed to write file. PHP error code: " . $file['error']);
                break;
            case UPLOAD_ERR_EXTENSION:
                $uploadMessage = 'Server error: A PHP extension stopped the file upload.';
                log_event("Upload failed: PHP extension error. PHP error code: " . $file['error']);
                break;
            default:
                $uploadMessage = 'An unknown upload error occurred.';
                log_event("Upload failed: Unknown PHP upload error. PHP error code: " . $file['error']);
                break;
        }
    } else {
        if ($file['size'] > MAX_FILE_SIZE) {
            $uploadMessage = 'The uploaded file is too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB.';
            log_event("Upload failed: File size ({$file['size']} bytes) exceeds maximum allowed " . MAX_FILE_SIZE . " bytes. Original filename: {$file['name']}");
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file['tmp_name']);

            if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
                $uploadMessage = 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.';
                log_event("Upload failed: Invalid MIME type detected: {$mime_type}. Original filename: {$file['name']}");
            } else {
                try {
                    $image = $imageManager->read($file['tmp_name']);

                    if ($image->width() > MAX_IMAGE_WIDTH || $image->height() > MAX_IMAGE_HEIGHT) {
                        $uploadMessage = 'Image dimensions are too large. Maximum allowed are ' . MAX_IMAGE_WIDTH . 'x' . MAX_IMAGE_HEIGHT . ' pixels.';
                        log_event("Upload failed: Image dimensions ({$image->width()}x{$image->height()}) exceed maximum allowed. Original filename: {$file['name']}");
                    } else {
                        $original_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $unique_filename_base = bin2hex(random_bytes(16));
                        $filename = $unique_filename_base . '.' . $original_ext;
                        $target_path = UPLOAD_DIR . $filename;

                        if (move_uploaded_file($file['tmp_name'], $target_path)) {
                            $thumbnail_filename = 'thumb_' . $filename;
                            $thumbnail_path = THUMB_DIR . $thumbnail_filename;

                            $image->resize(THUMB_WIDTH, THUMB_HEIGHT)->save($thumbnail_path);

                            $uploadMessage = 'Image uploaded and thumbnail generated successfully!';
                            $uploadSuccess = true;
                            log_event("Upload successful: Original '{$filename}', Thumbnail '{$thumbnail_filename}'.", true);

                        } else {
                            $uploadMessage = 'Server error: Failed to move uploaded file.';
                            log_event("Upload failed: Could not move uploaded file from '{$file['tmp_name']}' to '{$target_path}'. Original filename: {$file['name']}");
                        }
                    }
                } catch (\Exception $e) {
                    $uploadMessage = 'Server error: Image processing failed.';
                    log_event("Upload failed: Image processing exception for '{$file['name']}'. Error: " . $e->getMessage());
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
    <title>Image Upload and Thumbnail Generation</title>
</head>
<body>
    <h1>Upload Image</h1>

    <?php if ($uploadMessage): ?>
        <p style="color: <?php echo $uploadSuccess ? 'green' : 'red'; ?>; font-weight: bold;">
            <?php echo htmlspecialchars($uploadMessage); ?>
        </p>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <label for="image">Choose Image:</label><br>
        <input type="file" name="image" id="image" accept="image/jpeg, image/png, image/gif, image/webp"><br><br>
        <button type="submit">Upload Image</button>
    </form>

    <h2>Uploaded Images (for demonstration)</h2>
    <div style="display: flex; flex-wrap: wrap; gap: 10px; border: 1px solid #ccc; padding: 10px;">
        <?php
        $files = glob(THUMB_DIR . 'thumb_*');
        if ($files) {
            usort($files, function($a, $b) {
                return filemtime($b) <=> filemtime($a);
            });
            foreach ($files as $file) {
                $filename = basename($file);
                $original_filename = substr($filename, 6);
                echo '<div style="text-align: center; border: 1px solid #eee; padding: 5px;">';
                echo '<a href="uploads/' . htmlspecialchars($original_filename) . '" target="_blank">';
                echo '<img src="thumbnails/' . htmlspecialchars($filename) . '" alt="Thumbnail" style="max-width: 150px; height: auto; display: block; margin: 0 auto;">';
                echo '</a>';
                echo '<small>' . htmlspecialchars($original_filename) . '</small>';
                echo '</div>';
            }
        } else {
            echo '<p>No images uploaded yet.</p>';
        }
        ?>
    </div>
</body>
</html>
?>