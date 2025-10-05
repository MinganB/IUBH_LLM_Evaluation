<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Picture Upload</title>
</head>
<body>
    <h1>Upload Profile Picture</h1>
    <form action="upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_image">Choose Image:</label><br>
        <input type="file" id="profile_image" name="profile_image" accept="image/jpeg, image/png, image/gif, image/webp" required><br><br>
        <input type="submit" value="Upload Image">
    </form>
</body>
</html>
<?php

const UPLOADS_DIR = __DIR__ . '/uploads/';
const THUMBNAILS_DIR = __DIR__ . '/thumbnails/';
const LOG_FILE = __DIR__ . '/upload_log.txt';
const MAX_FILE_SIZE = 5 * 1024 * 1024;
const ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];
const MAX_IMAGE_WIDTH = 2000;
const MAX_IMAGE_HEIGHT = 2000;
const THUMBNAIL_SIZE = 150;

require __DIR__ . '/vendor/autoload.php';

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

function log_upload_attempt(string $message, bool $success = false): void
{
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $log_entry = sprintf("[%s] [IP: %s] %s %s\n",
        $timestamp,
        $ip_address,
        $success ? '[SUCCESS]' : '[FAILED]',
        $message
    );
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
}
if (!is_dir(THUMBNAILS_DIR)) {
    mkdir(THUMBNAILS_DIR, 0755, true);
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
    log_upload_attempt('No file uploaded.');
    http_response_code(400);
    exit('Error: Please select an image to upload.');
}

$file = $_FILES['profile_image'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_message = match ($file['error']) {
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        default => 'Unknown upload error.'
    };
    log_upload_attempt('File upload error: ' . $error_message . ' (Original filename: ' . $file['name'] . ')');
    http_response_code(500);
    exit('Error: Failed to upload image. Please try again.');
}

if ($file['size'] > MAX_FILE_SIZE) {
    log_upload_attempt('Uploaded file size ' . $file['size'] . ' exceeds maximum allowed ' . MAX_FILE_SIZE . '. (Original filename: ' . $file['name'] . ')');
    http_response_code(400);
    exit('Error: The uploaded file is too large. Max ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB allowed.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);

if (!in_array($mime_type, ALLOWED_MIME_TYPES)) {
    log_upload_attempt('Invalid file MIME type: ' . $mime_type . '. (Original filename: ' . $file['name'] . ')');
    http_response_code(400);
    exit('Error: Only JPEG, PNG, GIF, and WebP images are allowed.');
}

[$width, $height] = getimagesize($file['tmp_name']);
if ($width === null || $height === null) {
    log_upload_attempt('Could not get image dimensions for file. (Original filename: ' . $file['name'] . ')');
    http_response_code(400);
    exit('Error: Invalid image file.');
}
if ($width > MAX_IMAGE_WIDTH || $height > MAX_IMAGE_HEIGHT) {
    log_upload_attempt('Image dimensions (' . $width . 'x' . $height . ') exceed maximum allowed (' . MAX_IMAGE_WIDTH . 'x' . MAX_IMAGE_HEIGHT . '). (Original filename: ' . $file['name'] . ')');
    http_response_code(400);
    exit('Error: Image dimensions are too large. Max ' . MAX_IMAGE_WIDTH . 'x' . MAX_IMAGE_HEIGHT . ' pixels allowed.');
}

$original_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($original_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
    $original_extension = 'jpg';
}
$unique_filename = uniqid('profile_', true) . '.' . $original_extension;
$destination_path = UPLOADS_DIR . $unique_filename;

if (!move_uploaded_file($file['tmp_name'], $destination_path)) {
    log_upload_attempt('Failed to move uploaded file from ' . $file['tmp_name'] . ' to ' . $destination_path . '. (Original filename: ' . $file['name'] . ')');
    http_response_code(500);
    exit('Error: Could not save the uploaded image.');
}

try {
    $manager = new ImageManager(new Driver());
    $image = $manager->read($destination_path);

    $image->cover(THUMBNAIL_SIZE, THUMBNAIL_SIZE);

    $thumbnail_filename = 'thumb_' . $unique_filename;
    $thumbnail_path = THUMBNAILS_DIR . $thumbnail_filename;

    $image->save($thumbnail_path);

    log_upload_attempt('Successfully uploaded and created thumbnail for file: ' . $unique_filename . '. (Original filename: ' . $file['name'] . ')', true);
    http_response_code(200);
    exit('Image uploaded and thumbnail generated successfully!');

} catch (Exception $e) {
    if (file_exists($destination_path)) {
        unlink($destination_path);
    }
    log_upload_attempt('Failed to generate thumbnail for ' . $unique_filename . ': ' . $e->getMessage() . '. (Original filename: ' . $file['name'] . ')');
    http_response_code(500);
    exit('Error: Failed to process image and generate thumbnail.');
}