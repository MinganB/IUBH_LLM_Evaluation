<?php

// This is the frontend HTML file, typically located at /public/index.php
// It displays the upload form and any success/error messages from the handler.

// Retrieve status and message from URL parameters if redirected
$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = '<p style="color: green;">Image uploaded and thumbnail generated successfully!</p>';
    } else {
        // Sanitize the message before displaying to prevent XSS
        $errorMessage = htmlspecialchars($_GET['message'] ?? 'An unknown error occurred.');
        $message = '<p style="color: red;">Error: ' . $errorMessage . '</p>';
    }
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
    <h1>Upload Your Profile Picture</h1>
    <?php echo $message; ?>
    <form action="/handlers/upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_picture">Choose Image:</label><br>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg,image/png,image/gif,image/webp"><br><br>
        <button type="submit">Upload</button>
    </form>
</body>
</html>

<?php

// This is the backend handler script, typically located at /handlers/upload_handler.php
// It processes the uploaded image, generates a thumbnail, and logs the activity.

require_once __DIR__ . '/../vendor/autoload.php'; // Adjust path to Composer's autoload.php

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use App\Logger; // Assuming Logger class is defined with namespace App in classes/Logger.php

// --- Configuration ---
// Define absolute paths for security and consistency
// Assuming project root is two levels up from handlers/
const PROJECT_ROOT = __DIR__ . '/../';
const UPLOAD_ORIGINAL_DIR = PROJECT_ROOT . 'uploads/original/';
const UPLOAD_THUMBNAIL_DIR = PROJECT_ROOT . 'uploads/thumbnails/';
const LOG_FILE = PROJECT_ROOT . 'logs/upload.log';

// Ensure necessary directories exist and are writable
foreach ([UPLOAD_ORIGINAL_DIR, UPLOAD_THUMBNAIL_DIR, dirname(LOG_FILE)] as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            // Log this critical error internally, then provide a generic user message
            error_log(sprintf('Failed to create directory: %s', $dir));
            header('Location: /public/index.php?status=error&message=' . urlencode('Server configuration error. Please try again later.'));
            exit;
        }
    }
}

// Initialize logger
$logger = new Logger(LOG_FILE);

// --- Validation Constants ---
const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5 MB
const ALLOWED_MIME_TYPES = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];
const MIN_DIMENSION = 100; // pixels
const MAX_DIMENSION = 4000; // pixels
const THUMBNAIL_SIZE = 150; // pixels (width and height for square thumbnail)

$uploadStatus = 'error';
$userMessage = 'An unknown error occurred.';
$internalLogMessage = 'Unknown error during upload.';

try {
    // 1. Check if file was uploaded via POST and handle basic upload errors
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        switch ($_FILES['profile_picture']['error'] ?? UPLOAD_ERR_NO_FILE) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new Exception('Uploaded file exceeds maximum file size allowed by the server configuration.');
            case UPLOAD_ERR_PARTIAL:
                throw new Exception('File was only partially uploaded. Please try again.');
            case UPLOAD_ERR_NO_FILE:
                throw new Exception('No file was uploaded. Please choose an image.');
            case UPLOAD_ERR_NO_TMP_DIR:
                throw new Exception('Server temporary directory is missing. Please contact support.');
            case UPLOAD_ERR_CANT_WRITE:
                throw new Exception('Failed to write file to disk. Please try again.');
            case UPLOAD_ERR_EXTENSION:
                throw new Exception('A PHP extension stopped the file upload. Please contact support.');
            default:
                throw new Exception('An unknown file upload error occurred. Please try again.');
        }
    }

    $file = $_FILES['profile_picture'];
    $tempFilePath = $file['tmp_name'];

    // 2. Validate file type using finfo_file for robust detection
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($tempFilePath);

    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
        throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
    }

    // 3. Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('File size exceeds the maximum allowed (' . (MAX_FILE_SIZE / 1024 / 1024) . ' MB).');
    }

    // 4. Validate image dimensions using getimagesize()
    $imageInfo = getimagesize($tempFilePath);
    if ($imageInfo === false) {
        throw new Exception('Could not get image dimensions. The uploaded file might be corrupted or not a valid image.');
    }
    $width = $imageInfo[0];
    $height = $imageInfo[1];

    if ($width < MIN_DIMENSION || $height < MIN_DIMENSION || $width > MAX_DIMENSION || $height > MAX_DIMENSION) {
        throw new Exception(sprintf(
            'Image dimensions must be between %dx%d and %dx%d pixels. Current: %dx%d.',
            MIN_DIMENSION, MIN_DIMENSION, MAX_DIMENSION, MAX_DIMENSION, $width, $height
        ));
    }

    // 5. Sanitize and generate unique filename
    // Get the actual image extension based on detected type, not user-provided filename
    $extension = image_type_to_extension($imageInfo[2], false); // e.g., 'jpeg', 'png'

    if (!in_array($extension, ['jpeg', 'png', 'gif', 'webp'])) {
        // This check acts as a secondary safeguard if MIME type spoofing was attempted
        throw new Exception('File extension derived from image content is not allowed.');
    }

    // Generate a unique filename using a UUID-like string and the derived extension
    $uniqueId = uniqid('profile_', true);
    $newFileName = sprintf('%s.%s', $uniqueId, $extension);

    $originalSavePath = UPLOAD_ORIGINAL_DIR . $newFileName;
    $thumbnailSavePath = UPLOAD_THUMBNAIL_DIR . $newFileName;

    // 6. Process image with Intervention Image library
    $manager = new ImageManager(new Driver());
    $image = $manager->read($tempFilePath);

    // Save original image to its designated directory
    $image->save($originalSavePath);

    // Generate and save thumbnail
    // Resize and crop to cover the target dimensions (e.g., 150x150)
    $image->cover(THUMBNAIL_SIZE, THUMBNAIL_SIZE)->save($thumbnailSavePath);

    // If all steps are successful
    $uploadStatus = 'success';
    $userMessage = 'Image uploaded and thumbnail generated successfully.';
    $internalLogMessage = sprintf('User uploaded image "%s". Saved original: %s, thumbnail: %s', $file['name'], $originalSavePath, $thumbnailSavePath);

} catch (Exception $e) {
    // Log the detailed error internally for debugging and security
    $internalLogMessage = sprintf('File upload failed for user. Error: %s. Upload details: name=%s, type=%s, size=%d, tmp_name=%s',
        $e->getMessage(),
        $_FILES['profile_picture']['name'] ?? 'N/A',
        $_FILES['profile_picture']['type'] ?? 'N/A',
        $_FILES['profile_picture']['size'] ?? 0,
        $_FILES['profile_picture']['tmp_name'] ?? 'N/A'
    );

    // Set a generic user message by default for security, overriding for specific, safe-to-show validation errors
    $userMessage = 'Failed to upload image. Please try again or contact support.';
    if (strpos($e->getMessage(), 'Invalid file type') !== false ||
        strpos($e->getMessage(), 'File size exceeds') !== false ||
        strpos($e->getMessage(), 'Image dimensions must be') !== false ||
        strpos($e->getMessage(), 'No file was uploaded') !== false
    ) {
        $userMessage = $e->getMessage();
    }
} finally {
    // Log the attempt (success or failure)
    if ($uploadStatus === 'success') {
        $logger->info($internalLogMessage);
    } else {
        $logger->error($internalLogMessage);
    }

    // Clean up temporary file if it still exists (e.g., if processing failed after moving to temp)
    if (isset($tempFilePath) && is_uploaded_file($tempFilePath)) {
        // Note: move_uploaded_file and Intervention's save already handle this, but as a safeguard.
        // It's safer not to unlink here, as Intervention Image Manager handles reading from the temporary file.
        // If an error occurs BEFORE image processing, then the temp file might still be there.
        // However, standard PHP upload process removes temp file automatically at end of script execution.
    }

    // Redirect back to the form (or a status page) with the outcome
    header('Location: /public/index.php?status=' . $uploadStatus . '&message=' . urlencode($userMessage));
    exit;
}

<?php

// This is a custom Logger class, typically located at /classes/Logger.php
// It provides a secure way to log events with timestamps and IP addresses.

namespace App;

class Logger
{
    private string $logFilePath;

    public function __construct(string $logFilePath)
    {
        $this->logFilePath = $logFilePath;
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'; // Get user's IP address
        // Sanitize message to prevent log injection, though error messages should already be safe
        $sanitizedMessage = str_replace(["\r", "\n"], ['\r', '\n'], $message);
        $logEntry = sprintf("[%s] [%s] [%s] %s\n", $timestamp, $ipAddress, $level, $sanitizedMessage);

        // Use FILE_APPEND and LOCK_EX for safe concurrent writing
        if (file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX) === false) {
            // Fallback for logging if writing to the primary log file fails
            error_log(sprintf('Failed to write to log file: %s. Log entry: %s', $this->logFilePath, $logEntry));
        }
    }

    public function info(string $message): void
    {
        $this->writeLog('INFO', $message);
    }

    public function error(string $message): void
    {
        $this->writeLog('ERROR', $message);
    }
}
?>