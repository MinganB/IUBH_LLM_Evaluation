<?php

namespace App;

class Logger
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    private function writeLog(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s] %s%s", $timestamp, $level, $message, PHP_EOL);
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
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
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Intervention\Image\ImageManagerStatic as Image;
use App\Logger;

const BASE_DIR = __DIR__ . '/../';
const PUBLIC_DIR = BASE_DIR . 'public/';
const THUMBNAIL_DIR = PUBLIC_DIR . 'uploads/thumbnails/';
const ORIGINAL_IMAGE_DIR = PUBLIC_DIR . 'uploads/originals/';
const LOG_FILE = BASE_DIR . 'logs/upload.log';

foreach ([THUMBNAIL_DIR, ORIGINAL_IMAGE_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
if (!is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}


$logger = new Logger(LOG_FILE);

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }

    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded.');
    }

    $file = $_FILES['profile_picture'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessage = match ($file['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file exceeds maximum allowed size.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder for uploads.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error.'
        };
        throw new Exception($errorMessage);
    }

    $maxFileSize = 5 * 1024 * 1024;
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxOriginalWidth = 5000;
    $maxOriginalHeight = 5000;

    if ($file['size'] > $maxFileSize) {
        throw new Exception('File size exceeds the maximum allowed (' . ($maxFileSize / (1024 * 1024)) . 'MB).');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception('Unsupported file type. Only JPEG, PNG, and GIF images are allowed.');
    }

    $originalFileExtension = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        default => 'bin'
    };
    $originalUniqueFilename = uniqid('img_', true) . '.' . $originalFileExtension;
    $originalFilePath = ORIGINAL_IMAGE_DIR . $originalUniqueFilename;
    
    $img = Image::make($file['tmp_name']);
    if ($img->width() > $maxOriginalWidth || $img->height() > $maxOriginalHeight) {
        throw new Exception('Original image dimensions exceed the maximum allowed (' . $maxOriginalWidth . 'x' . $maxOriginalHeight . ' pixels).');
    }

    $img->save($originalFilePath);

    $thumbnailWidth = 200;
    $thumbnailHeight = 200;

    $img->resize($thumbnailWidth, $thumbnailHeight, function ($constraint) {
        $constraint->aspectRatio();
        $constraint->upsize();
    });

    $thumbnailFileExtension = $originalFileExtension;
    $thumbnailUniqueFilename = uniqid('thumb_', true) . '.' . $thumbnailFileExtension;
    $thumbnailFilePath = THUMBNAIL_DIR . $thumbnailUniqueFilename;

    $img->save($thumbnailFilePath);

    $thumbnailUrl = '/uploads/thumbnails/' . $thumbnailUniqueFilename;

    $response = [
        'success' => true,
        'message' => 'File uploaded and thumbnail generated successfully.',
        'thumbnail_url' => $thumbnailUrl
    ];
    $logger->info("SUCCESS: User {$ipAddress} uploaded '{$file['name']}'. Original saved as '{$originalUniqueFilename}', thumbnail as '{$thumbnailUniqueFilename}'.");

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    $logger->error("FAILED: User {$ipAddress} upload failed for '{$_FILES['profile_picture']['name'] ?? 'N/A'}'. Error: " . $e->getMessage());
} catch (Throwable $e) {
    $response['message'] = 'An unexpected server error occurred. Please try again later.';
    $logger->error("CRITICAL FAILED: User {$ipAddress} upload encountered a critical error for '{$_FILES['profile_picture']['name'] ?? 'N/A'}'. Error: " . $e->getMessage() . " on line " . $e->getLine());
}

echo json_encode($response);
exit();
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

    <form action="../handlers/upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_picture">Choose Image:</label>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif" required>
        <br><br>
        <button type="submit">Upload</button>
    </form>

    <div id="response"></div>

    <script>
        document.querySelector('form').addEventListener('submit', async function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                const responseDiv = document.getElementById('response');
                responseDiv.innerHTML = '';

                if (result.success) {
                    const img = document.createElement('img');
                    img.src = result.thumbnail_url;
                    img.alt = 'Uploaded Thumbnail';
                    img.style.maxWidth = '200px';
                    img.style.maxHeight = '200px';
                    responseDiv.appendChild(document.createElement('p')).textContent = 'Upload successful!';
                    responseDiv.appendChild(img);
                    responseDiv.appendChild(document.createElement('p')).textContent = 'Thumbnail URL: ' + result.thumbnail_url;
                } else {
                    responseDiv.appendChild(document.createElement('p')).textContent = 'Upload failed: ' + result.message;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('response').appendChild(document.createElement('p')).textContent = 'An unexpected error occurred during upload.';
            }
        });
    </script>
</body>
</html>
?>