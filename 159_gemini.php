<?php

// index.php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload Module</title>
</head>
<body>

    <h1>Upload Your Profile Picture</h1>

    <form id="uploadForm" enctype="multipart/form-data">
        <input type="file" name="image" id="imageInput" accept="image/jpeg, image/png, image/gif">
        <button type="submit">Upload Image</button>
    </form>

    <div id="responseContainer" style="margin-top: 20px;"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(event) {
            event.preventDefault();

            var formData = new FormData(this);
            var responseContainer = document.getElementById('responseContainer');
            responseContainer.innerHTML = ''; // Clear previous messages

            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    responseContainer.innerHTML = '<p>Upload successful!</p>';
                    var img = document.createElement('img');
                    img.src = data.thumbnail_url;
                    img.alt = 'Uploaded Thumbnail';
                    img.style.maxWidth = '200px';
                    img.style.maxHeight = '200px';
                    responseContainer.appendChild(img);
                } else {
                    responseContainer.innerHTML = '<p>Error: ' + data.error + '</p>';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                responseContainer.innerHTML = '<p>An unexpected error occurred. Please try again.</p>';
            });
        });
    </script>

</body>
</html>
<?php

// upload_handler.php

require __DIR__ . '/vendor/autoload.php';

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

header('Content-Type: application/json');

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('THUMB_DIR', __DIR__ . '/thumbnails');
define('LOG_DIR', __DIR__ . '/logs');
define('LOG_FILE', LOG_DIR . '/upload.log');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('THUMB_MAX_WIDTH', 200);
define('THUMB_MAX_HEIGHT', 200);

$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
];

function log_activity(string $message, string $level = 'info'): void
{
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN_IP';
    $logMessage = sprintf("[%s] [%s] [%s] %s\n", $timestamp, $ipAddress, strtoupper($level), $message);
    file_put_contents(LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
}

function send_json_response(bool $success, string $message, string $thumbnailUrl = null): void
{
    $response = ['success' => $success];
    if ($success) {
        $response['thumbnail_url'] = $thumbnailUrl;
    } else {
        $response['error'] = $message;
    }
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    log_activity('Invalid request method.', 'warning');
    send_json_response(false, 'Invalid request method.');
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];
    $errorCode = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errorMessage = $errorMessages[$errorCode] ?? 'Unknown upload error.';
    log_activity("File upload error: {$errorMessage} (Code: {$errorCode})", 'error');
    send_json_response(false, 'File upload failed. Please try again.');
}

$fileTmpPath = $_FILES['image']['tmp_name'];
$originalFileName = basename($_FILES['image']['name']);
$fileSize = $_FILES['image']['size'];

// Validate file size
if ($fileSize > MAX_FILE_SIZE) {
    log_activity("Uploaded file size ({$fileSize} bytes) exceeds limit ({MAX_FILE_SIZE} bytes) for {$originalFileName}.", 'warning');
    send_json_response(false, 'The uploaded file is too large. Maximum size is 5MB.');
}

// Validate file type using finfo
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($fileTmpPath);

if (!in_array($mimeType, $allowedMimeTypes)) {
    log_activity("Invalid MIME type '{$mimeType}' for {$originalFileName}.", 'warning');
    send_json_response(false, 'Unsupported file type. Only JPEG, PNG, and GIF images are allowed.');
}

// Ensure upload and thumbnail directories exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
if (!is_dir(THUMB_DIR)) {
    mkdir(THUMB_DIR, 0755, true);
}

// Generate unique filename
$fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
$uniqueFileName = uniqid('img_', true) . '.' . $fileExtension;
$uploadPath = UPLOAD_DIR . '/' . $uniqueFileName;
$thumbnailPath = THUMB_DIR . '/' . $uniqueFileName;
$thumbnailUrl = '/thumbnails/' . $uniqueFileName; // URL for frontend

// Move uploaded file
if (!move_uploaded_file($fileTmpPath, $uploadPath)) {
    log_activity("Failed to move uploaded file '{$originalFileName}' to '{$uploadPath}'.", 'error');
    send_json_response(false, 'Failed to save the uploaded image.');
}

try {
    $manager = new ImageManager(new Driver());
    $image = $manager->read($uploadPath);

    // Generate thumbnail
    $image->cover(THUMB_MAX_WIDTH, THUMB_MAX_HEIGHT);

    // Save thumbnail
    $image->save($thumbnailPath);

    log_activity("Successfully processed and saved thumbnail for '{$originalFileName}' as '{$uniqueFileName}'.", 'info');
    send_json_response(true, 'Image uploaded and thumbnail generated successfully.', $thumbnailUrl);

} catch (Throwable $e) {
    log_activity("Image processing failed for '{$uniqueFileName}': " . $e->getMessage(), 'error');
    // Attempt to remove the original uploaded file if thumbnail generation failed
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    send_json_response(false, 'Failed to generate image thumbnail. Please try again.');
}

?>