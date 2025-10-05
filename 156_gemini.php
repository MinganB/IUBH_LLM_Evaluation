<?php
// index.php - Frontend HTML form and JavaScript for image upload

// This file serves the HTML form for image upload and handles the submission via JavaScript.
// It will send the image to upload_handler.php and display the result.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Thumbnail Generator</title>
</head>
<body>
    <h1>Upload Image to Generate Thumbnail</h1>
    <form id="uploadForm" enctype="multipart/form-data">
        <input type="file" name="image" id="imageInput" accept="image/jpeg, image/png, image/gif" required>
        <button type="submit">Generate Thumbnail</button>
    </form>
    <div id="result"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = '<p>Uploading and generating thumbnail...</p>';

            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    resultDiv.innerHTML = `
                        <p>Thumbnail generated successfully:</p>
                        <img src="${data.thumbnail_url}" alt="Thumbnail" style="max-width: 200px; height: auto; border: 1px solid #ccc;">
                        <p>URL: <a href="${data.thumbnail_url}" target="_blank">${data.thumbnail_url}</a></p>
                    `;
                } else {
                    resultDiv.innerHTML = `<p style="color: red;">Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<p style="color: red;">An unexpected error occurred: ${error.message}</p>`;
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>

<?php
// upload_handler.php - Backend script to handle image upload and thumbnail generation

// Ensure Composer autoloader is included for Intervention Image.
// This assumes `composer install` has been run and `intervention/image` is installed.
// If this file is not in the web root, adjust the path to `vendor/autoload.php` accordingly.
require __DIR__ . '/vendor/autoload.php';

use Intervention\Image\ImageManagerStatic as Image;

// --- Configuration Constants ---
// Base directory for uploads and thumbnails. Adjust as needed.
// '__DIR__' refers to the directory where upload_handler.php resides.
const UPLOADS_BASE_DIR = __DIR__;
const THUMBNAILS_SUBDIR = '/thumbnails'; // Subdirectory within UPLOADS_BASE_DIR for thumbnails
const LOG_SUBDIR = '/logs'; // Subdirectory within UPLOADS_BASE_DIR for logs
const LOG_FILE_NAME = '/upload_activity.log'; // Name of the log file

const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB (in bytes)
const MAX_THUMB_WIDTH = 200; // Maximum width for the thumbnail
const MAX_THUMB_HEIGHT = 200; // Maximum height for the thumbnail

// Whitelist of allowed MIME types for security
const ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
];

// --- Directory Setup ---
$thumbnailDir = UPLOADS_BASE_DIR . THUMBNAILS_SUBDIR;
$logDir = UPLOADS_BASE_DIR . LOG_SUBDIR;
$logFilePath = $logDir . LOG_FILE_NAME;

// Create necessary directories if they don't exist
foreach ([$thumbnailDir, $logDir] as $dir) {
    if (!is_dir($dir)) {
        // Use 0755 permissions: owner can read/write/execute, group/others can read/execute
        // Recursive parameter ensures parent directories are also created if needed
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            // Log this critical error. For security, we return a generic message to the client.
            error_log(sprintf("[%s] [CRITICAL] Could not create directory: %s", date('Y-m-d H:i:s'), $dir));
            send_json_response('error', 'Server error: Necessary directories could not be created.');
        }
    }
}

// --- Logging Function ---
// Logs upload attempts and outcomes to a secure file.
function log_upload_activity(string $level, string $message, array $context = []): void
{
    global $logFilePath; // Access the global log file path
    $timestamp = date('Y-m-d H:i:s');
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'; // Get user's IP address
    $logMessage = sprintf(
        "[%s] [%s] [%s] %s %s\n",
        $timestamp,
        strtoupper($level), // Level (e.g., INFO, WARNING, ERROR, CRITICAL)
        $ipAddress,
        $message,
        !empty($context) ? json_encode($context) : '' // Add context data as JSON
    );
    // Use FILE_APPEND to add to the end of the file, LOCK_EX to prevent race conditions during writes
    file_put_contents($logFilePath, $logMessage, FILE_APPEND | LOCK_EX);
}

// --- JSON Response Function ---
// Sets the Content-Type header and outputs a JSON response, then exits.
function send_json_response(string $status, string $message, array $data = []): void
{
    header('Content-Type: application/json');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit;
}

// --- Main Script Logic ---

// 1. Validate Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_upload_activity('warning', 'Invalid request method.', ['method' => $_SERVER['REQUEST_METHOD']]);
    send_json_response('error', 'Invalid request method.');
}

// 2. Check for Uploaded File and Initial Upload Errors
if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = 'No file uploaded or an upload error occurred.';
    $errorCode = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMessage = 'The uploaded file exceeds the maximum allowed size configured on the server.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMessage = 'The uploaded file was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMessage = 'No file was uploaded.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorMessage = 'Server error: Missing a temporary folder for uploads.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorMessage = 'Server error: Failed to write file to disk.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorMessage = 'Server error: A PHP extension stopped the file upload.';
            break;
    }
    log_upload_activity('error', $errorMessage, ['file_error_code' => $errorCode]);
    send_json_response('error', 'File upload failed. Please try again or check file size.');
}

$uploadedFile = $_FILES['image'];

// 3. Validate File Size
if ($uploadedFile['size'] > MAX_FILE_SIZE) {
    log_upload_activity('error', 'Uploaded file size exceeds limit.', [
        'original_name' => $uploadedFile['name'],
        'size' => $uploadedFile['size'],
        'max_size' => MAX_FILE_SIZE
    ]);
    send_json_response('error', 'The uploaded file is too large (max ' . (MAX_FILE_SIZE / (1024 * 1024)) . 'MB).');
}

// 4. Validate File MIME Type using `finfo_file` for security
// This checks the actual content of the file, not just the client-provided type.
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    log_upload_activity('critical', 'Failed to open fileinfo database.', ['file' => $uploadedFile['name']]);
    send_json_response('error', 'Server configuration error preventing file validation.');
}
$mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
    log_upload_activity('error', 'Unsupported file type detected.', [
        'original_name' => $uploadedFile['name'],
        'detected_mime' => $mimeType,
        'allowed_mimes' => ALLOWED_MIME_TYPES
    ]);
    send_json_response('error', 'Unsupported file type. Only JPEG, PNG, and GIF images are allowed.');
}

// 5. Get Image Dimensions and further validate it's a real image
// `getimagesize()` will fail on non-image files, providing an extra layer of validation.
$image_info = getimagesize($uploadedFile['tmp_name']);
if ($image_info === false) {
    log_upload_activity('error', 'Could not get image dimensions or invalid image file content.', [
        'original_name' => $uploadedFile['name'],
        'detected_mime' => $mimeType
    ]);
    send_json_response('error', 'Invalid image file content.');
}
list($originalWidth, $originalHeight) = $image_info;

// Determine file extension from the detected MIME type
$extension = '';
switch ($mimeType) {
    case 'image/jpeg': $extension = 'jpg'; break;
    case 'image/png': $extension = 'png'; break;
    case 'image/gif': $extension = 'gif'; break;
    // No default needed as validation ensures it's one of these.
}

try {
    // 6. Generate a Unique Filename for the Thumbnail
    // Uses uniqid() for a base and random_bytes for more entropy, preventing collisions and guessing.
    $uniqueFilename = uniqid('thumb_', true) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $thumbnailFullPath = $thumbnailDir . '/' . $uniqueFilename;

    // 7. Process Image and Generate Thumbnail using Intervention Image
    $img = Image::make($uploadedFile['tmp_name']);

    // Resize the image, maintaining aspect ratio.
    // The `upsize()` method ensures the image is not enlarged if it's smaller than the target dimensions.
    $img->resize(MAX_THUMB_WIDTH, MAX_THUMB_HEIGHT, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });

    // Save the thumbnail to the specified directory
    $img->save($thumbnailFullPath);

    // 8. Generate the Web URL for the Thumbnail
    // This assumes the `THUMBNAILS_SUBDIR` is directly web-accessible from the domain root.
    // Adjust this path calculation based on your actual web server configuration.
    $thumbnailWebPath = THUMBNAILS_SUBDIR . '/' . $uniqueFilename;

    // 9. Log Success
    log_upload_activity('info', 'Thumbnail generated successfully.', [
        'original_name' => $uploadedFile['name'],
        'thumbnail_name' => $uniqueFilename,
        'thumbnail_url' => $thumbnailWebPath,
        'original_size' => $uploadedFile['size'],
        'thumbnail_size' => filesize($thumbnailFullPath),
        'original_dimensions' => "{$originalWidth}x{$originalHeight}",
        'thumbnail_dimensions' => "{$img->width()}x{$img->height()}",
        'mime_type' => $mimeType
    ]);

    // 10. Return JSON Success Response
    send_json_response('success', 'Thumbnail generated successfully.', ['thumbnail_url' => $thumbnailWebPath]);

} catch (Exception $e) {
    // 11. Catch and Log Image Processing Errors
    log_upload_activity('critical', 'Image processing failed.', [
        'original_name' => $uploadedFile['name'],
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine()
    ]);
    // Return a generic error message to the client for security
    send_json_response('error', 'Failed to process image. Please try again.');
}
?>