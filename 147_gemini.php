<?php
session_start();

$message = '';
$thumbnail_url = '';
if (isset($_SESSION['upload_message'])) {
    $message = $_SESSION['upload_message'];
    unset($_SESSION['upload_message']);
}
if (isset($_SESSION['thumbnail_url'])) {
    $thumbnail_url = $_SESSION['thumbnail_url'];
    unset($_SESSION['thumbnail_url']);
}

echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '    <meta charset="UTF-8">';
echo '    <meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '    <title>Image Thumbnail Generator</title>';
echo '</head>';
echo '<body>';
echo '    <h1>Upload Image to Generate Thumbnail</h1>';

if ($message) {
    echo '<p>' . htmlspecialchars($message) . '</p>';
}

echo '    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">';
echo '        <label for="image_file">Select Image:</label>';
echo '        <input type="file" name="image_file" id="image_file" accept="image/jpeg, image/png, image/gif, image/webp">';
echo '        <br><br>';
echo '        <button type="submit">Upload & Generate Thumbnail</button>';
echo '    </form>';

if ($thumbnail_url) {
    echo '    <h2>Generated Thumbnail:</h2>';
    echo '    <img src="uploads/thumbnails/' . htmlspecialchars($thumbnail_url) . '" alt="Generated Thumbnail">';
    echo '    <p>Path: <code>uploads/thumbnails/' . htmlspecialchars($thumbnail_url) . '</code></p>';
}

echo '</body>';
echo '</html>';

?>
<?php

define('UPLOAD_THUMBNAIL_DIR', __DIR__ . '/uploads/thumbnails/');
define('LOG_FILE', __DIR__ . '/var/logs/upload_log.log');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_MIME_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
]);
define('MAX_IMAGE_WIDTH', 4000);
define('MAX_IMAGE_HEIGHT', 4000);
define('THUMBNAIL_WIDTH', 200);
define('THUMBNAIL_HEIGHT', 200);

function log_event($message, $type = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = sprintf("[%s] [%s] [%s] %s\n", $timestamp, $ip_address, $type, $message);
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

if (!is_dir(UPLOAD_THUMBNAIL_DIR)) {
    mkdir(UPLOAD_THUMBNAIL_DIR, 0755, true);
}
if (!is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

require_once __DIR__ . '/vendor/autoload.php';

use Intervention\Image\ImageManagerStatic as Image;

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['image_file']['error'] ?? 'UNKNOWN';
        log_event("File upload failed with error code: {$error_code}", 'ERROR');
        $_SESSION['upload_message'] = 'An unexpected error occurred during file upload. Please try again.';
        header('Location: index.php');
        exit;
    }

    $file_tmp_name = $_FILES['image_file']['tmp_name'];
    $file_size = $_FILES['image_file']['size'];
    $original_filename = $_FILES['image_file']['name'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_tmp_name);
    finfo_close($finfo);

    if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
        log_event("Attempted upload with disallowed MIME type: {$mime_type} (Original: {$original_filename})", 'WARNING');
        $_SESSION['upload_message'] = 'Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.';
        header('Location: index.php');
        exit;
    }

    if ($file_size > MAX_FILE_SIZE) {
        log_event("Attempted upload with file size exceeding limit: {$file_size} bytes (Original: {$original_filename})", 'WARNING');
        $_SESSION['upload_message'] = 'File size exceeds the maximum limit of ' . (MAX_FILE_SIZE / (1024 * 1024)) . ' MB.';
        header('Location: index.php');
        exit;
    }

    $image_info = getimagesize($file_tmp_name);
    if ($image_info === false) {
        log_event("Failed to get image dimensions for uploaded file (Original: {$original_filename})", 'WARNING');
        $_SESSION['upload_message'] = 'Could not process image dimensions. Please upload a valid image.';
        header('Location: index.php');
        exit;
    }
    $width = $image_info[0];
    $height = $image_info[1];

    if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT) {
        log_event("Image dimensions too large: {$width}x{$height} (Max: " . MAX_IMAGE_WIDTH . "x" . MAX_IMAGE_HEIGHT . ") (Original: {$original_filename})", 'WARNING');
        $_SESSION['upload_message'] = 'Image dimensions exceed the maximum allowed size.';
        header('Location: index.php');
        exit;
    }

    $extension_map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $extension = $extension_map[$mime_type] ?? pathinfo($original_filename, PATHINFO_EXTENSION);
    $extension = strtolower(preg_replace('/[^a-z0-9]/i', '', $extension));

    if (empty($extension)) {
         $extension = 'jpg';
    }

    $unique_filename = uniqid('thumb_', true) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $target_path = UPLOAD_THUMBNAIL_DIR . $unique_filename;

    try {
        Image::make($file_tmp_name)
            ->fit(THUMBNAIL_WIDTH, THUMBNAIL_HEIGHT)
            ->save($target_path);

        log_event("Successfully generated thumbnail: {$unique_filename} (Original: {$original_filename})", 'INFO');
        $_SESSION['upload_message'] = 'Image uploaded and thumbnail generated successfully!';
        $_SESSION['thumbnail_url'] = basename($target_path);
    } catch (\Exception $e) {
        log_event("Error generating thumbnail for {$original_filename}: " . $e->getMessage(), 'ERROR');
        $_SESSION['upload_message'] = 'An error occurred during thumbnail generation. Please try again.';
    }

    header('Location: index.php');
    exit;

} else {
    header('Location: index.php');
    exit;
}
?>