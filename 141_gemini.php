<?php

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('THUMB_DIR', __DIR__ . '/thumbnails/');
define('LOG_FILE', __DIR__ . '/logs/upload.log');

define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
]);
define('THUMB_WIDTH', 150);
define('THUMB_HEIGHT', 150);
define('MAX_IMAGE_WIDTH', 5000);
define('MAX_IMAGE_HEIGHT', 5000);

function create_and_check_dir($path) {
    if (!is_dir($path)) {
        if (!mkdir($path, 0755, true)) {
            return false;
        }
    }
    if (!is_writable($path)) {
        return false;
    }
    return true;
}

if (!create_and_check_dir(UPLOAD_DIR) || !create_and_check_dir(THUMB_DIR) || !create_and_check_dir(dirname(LOG_FILE))) {
    http_response_code(500);
    exit;
}

session_start();

require __DIR__ . '/vendor/autoload.php';

use Intervention\Image\ImageManagerStatic as Image;

function log_message($message, $type = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = "[{$timestamp}] [{$ip_address}] [{$type}] {$message}" . PHP_EOL;
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_message = '';
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message = 'Uploaded file exceeds maximum allowed size.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message = 'File upload was not completed.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message = 'No file was uploaded.';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_message = 'Server missing a temporary folder.';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_message = 'Failed to write file to disk.';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_message = 'A PHP extension stopped the file upload.';
                break;
            default:
                $error_message = 'Unknown upload error.';
        }
        log_message("Upload failed for {$file['name']}: {$error_message}", 'error');
        $_SESSION['message'] = 'An error occurred during upload. Please try again.';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    try {
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception("File size ({$file['size']} bytes) exceeds maximum allowed size (" . MAX_FILE_SIZE . " bytes).");
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($file['tmp_name']);

        if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
            throw new Exception("Invalid file type: '{$mime_type}'. Only JPEG, PNG, GIF, WEBP images are allowed.");
        }

        $image_info = getimagesize($file['tmp_name']);
        if ($image_info === false) {
            throw new Exception("Could not get image dimensions or file is not a valid image.");
        }
        $width = $image_info[0];
        $height = $image_info[1];

        if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT) {
            throw new Exception("Image dimensions ({$width}x{$height}) exceed maximum allowed (" . MAX_IMAGE_WIDTH . "x" . MAX_IMAGE_HEIGHT . ").");
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $base_name = md5_file($file['tmp_name']);
        $unique_filename = $base_name . '.' . strtolower($extension);

        $upload_path = UPLOAD_DIR . $unique_filename;
        $thumbnail_path = THUMB_DIR . 'thumb_' . $unique_filename;

        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception("Failed to move uploaded file.");
        }

        $img = Image::make($upload_path);

        $img->fit(THUMB_WIDTH, THUMB_HEIGHT, function ($constraint) {
            $constraint->upsize();
        })->save($thumbnail_path);

        log_message("File '{$unique_filename}' uploaded and thumbnail created successfully.", 'success');
        $_SESSION['message'] = 'Profile picture uploaded and thumbnail generated successfully!';
        $_SESSION['uploaded_file'] = basename($upload_path);
        $_SESSION['thumbnail_file'] = basename($thumbnail_path);

    } catch (Exception $e) {
        log_message("Upload failed for {$file['name']}: " . $e->getMessage(), 'error');
        $_SESSION['message'] = 'Failed to upload image. Please ensure it is a valid image, within size and dimension limits, and try again.';
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
        if (isset($thumbnail_path) && file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$uploaded_file_name = $_SESSION['uploaded_file'] ?? null;
$thumbnail_file_name = $_SESSION['thumbnail_file'] ?? null;
unset($_SESSION['uploaded_file'], $_SESSION['thumbnail_file']);
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

    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <label for="profile_picture">Choose Profile Picture:</label>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif, image/webp" required>
        <br><br>
        <button type="submit">Upload</button>
    </form>

    <?php if ($uploaded_file_name): ?>
        <h2>Uploaded Image:</h2>
        <img src="<?php echo 'uploads/' . htmlspecialchars($uploaded_file_name); ?>" alt="Uploaded Profile Picture" style="max-width: 300px; height: auto;">
        <p>Original file: <?php echo htmlspecialchars($uploaded_file_name); ?></p>
    <?php endif; ?>

    <?php if ($thumbnail_file_name): ?>
        <h2>Thumbnail:</h2>
        <img src="<?php echo 'thumbnails/' . htmlspecialchars($thumbnail_file_name); ?>" alt="Profile Thumbnail" style="border: 1px solid #ccc;">
        <p>Thumbnail file: <?php echo htmlspecialchars($thumbnail_file_name); ?></p>
    <?php endif; ?>
</body>
</html>
?>