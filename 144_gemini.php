<?php

// --- classes/Logger.php ---

class Logger
{
    private string $logFile;

    public function __construct(string $logFilePath)
    {
        $this->logFile = $logFilePath;
        $this->ensureLogFileExists();
    }

    private function ensureLogFileExists(): void
    {
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0644);
        }
    }

    public function log(string $message, string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $logEntry = sprintf("[%s] [%s] [%s] %s%s", $timestamp, $ipAddress, $level, $message, PHP_EOL);
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>
<?php

// --- classes/FileValidator.php ---

class FileValidator
{
    public function validateMimeType(array $file, array $allowedMimeTypes): bool
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        return in_array($mime, $allowedMimeTypes, true);
    }

    public function validateFileSize(array $file, int $maxFileSize): bool
    {
        return $file['size'] <= $maxFileSize;
    }

    public function validateImageDimensions(array $file, int $minWidth, int $minHeight, int $maxWidth, int $maxHeight): bool
    {
        list($width, $height) = getimagesize($file['tmp_name']);
        if (!$width || !$height) {
            return false;
        }
        return ($width >= $minWidth && $height >= $minHeight && $width <= $maxWidth && $height <= $maxHeight);
    }

    public function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9.\-]/', '', $filename);
        $filename = preg_replace('/\.+/', '.', $filename);
        $filename = trim($filename, '.-');
        $filename = str_replace(['../', './'], '', $filename);
        if (strpos($filename, '.') === false) {
             return '';
        }
        return $filename;
    }
}
?>
<?php

// --- classes/ImageProcessor.php ---

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageProcessor
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function createThumbnail(string $sourcePath, string $destinationPath, int $width, int $height): bool
    {
        try {
            $image = $this->manager->read($sourcePath);
            $image->cover($width, $height);
            $image->save($destinationPath);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function saveOriginal(string $sourcePath, string $destinationPath): bool
    {
        try {
            $image = $this->manager->read($sourcePath);
            $image->save($destinationPath);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}
?>
<?php

// --- handlers/ImageUploadHandler.php ---

class ImageUploadHandler
{
    private Logger $logger;
    private FileValidator $validator;
    private ImageProcessor $processor;

    private string $uploadDirOriginal;
    private string $uploadDirThumbnail;
    private int $maxFileSize;
    private array $allowedMimeTypes;
    private int $thumbnailWidth;
    private int $thumbnailHeight;
    private int $minImageWidth;
    private int $minImageHeight;
    private int $maxImageWidth;
    private int $maxImageHeight;

    public function __construct(
        Logger $logger,
        FileValidator $validator,
        ImageProcessor $processor,
        array $config
    ) {
        $this->logger = $logger;
        $this->validator = $validator;
        $this->processor = $processor;

        $this->uploadDirOriginal = $config['UPLOAD_DIR_ORIGINAL'];
        $this->uploadDirThumbnail = $config['UPLOAD_DIR_THUMBNAIL'];
        $this->maxFileSize = $config['MAX_FILE_SIZE'];
        $this->allowedMimeTypes = $config['ALLOWED_MIME_TYPES'];
        $this->thumbnailWidth = $config['THUMBNAIL_WIDTH'];
        $this->thumbnailHeight = $config['THUMBNAIL_HEIGHT'];
        $this->minImageWidth = $config['MIN_IMAGE_WIDTH'];
        $this->minImageHeight = $config['MIN_IMAGE_HEIGHT'];
        $this->maxImageWidth = $config['MAX_IMAGE_WIDTH'];
        $this->maxImageHeight = $config['MAX_IMAGE_HEIGHT'];

        $this->ensureUploadDirectoriesExist();
    }

    private function ensureUploadDirectoriesExist(): void
    {
        if (!is_dir($this->uploadDirOriginal)) {
            mkdir($this->uploadDirOriginal, 0755, true);
        }
        if (!is_dir($this->uploadDirThumbnail)) {
            mkdir($this->uploadDirThumbnail, 0755, true);
        }
    }

    public function handleUpload(array $file, string $userIp): array
    {
        if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->logger->log("File upload error code: " . ($file['error'] ?? 'UNKNOWN') . " for file: {$file['name']} from IP: {$userIp}", 'ERROR');
            return ['success' => false, 'message' => 'Upload failed due to a server error.'];
        }

        if (!$this->validator->validateMimeType($file, $this->allowedMimeTypes)) {
            $this->logger->log("Invalid MIME type detected for file: {$file['name']} from IP: {$userIp}", 'WARNING');
            return ['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, GIF, WebP are allowed.'];
        }

        if (!$this->validator->validateFileSize($file, $this->maxFileSize)) {
            $this->logger->log("File size exceeded limit for file: {$file['name']} from IP: {$userIp}", 'WARNING');
            return ['success' => false, 'message' => 'File size exceeds the maximum limit of ' . ($this->maxFileSize / 1024 / 1024) . 'MB.'];
        }

        if (!$this->validator->validateImageDimensions($file, $this->minImageWidth, $this->minImageHeight, $this->maxImageWidth, $this->maxImageHeight)) {
            $this->logger->log("Invalid image dimensions for file: {$file['name']} from IP: {$userIp}", 'WARNING');
            return ['success' => false, 'message' => 'Image dimensions are not within the allowed range (min ' . $this->minImageWidth . 'x' . $this->minImageHeight . ', max ' . $this->maxImageWidth . 'x' . $this->maxImageHeight . ').'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];

        $extension = $extensionMap[$actualMime] ?? '';
        if (empty($extension)) {
            $this->logger->log("Could not map MIME type {$actualMime} to a safe extension for file: {$file['name']} from IP: {$userIp}", 'WARNING');
            return ['success' => false, 'message' => 'Failed to determine a safe file extension.'];
        }

        $originalFilenameSafe = $this->validator->sanitizeFilename($file['name']);
        $uniqueFilename = uniqid('img_', true) . '.' . $extension;

        $originalFilePath = $this->uploadDirOriginal . '/' . $uniqueFilename;
        $thumbnailFilePath = $this->uploadDirThumbnail . '/' . $uniqueFilename;

        if (!move_uploaded_file($file['tmp_name'], $originalFilePath)) {
            $this->logger->log("Failed to move uploaded file {$file['tmp_name']} to {$originalFilePath} from IP: {$userIp}", 'ERROR');
            return ['success' => false, 'message' => 'Failed to save the original image.'];
        }

        if (!$this->processor->saveOriginal($originalFilePath, $originalFilePath)) {
             unlink($originalFilePath);
             $this->logger->log("Failed to process/save original image: {$uniqueFilename} from IP: {$userIp}", 'ERROR');
             return ['success' => false, 'message' => 'Failed to process the original image.'];
        }

        if (!$this->processor->createThumbnail($originalFilePath, $thumbnailFilePath, $this->thumbnailWidth, $this->thumbnailHeight)) {
            $this->logger->log("Failed to create thumbnail for image: {$uniqueFilename} from IP: {$userIp}", 'ERROR');
            unlink($originalFilePath);
            return ['success' => false, 'message' => 'Failed to generate thumbnail.'];
        }

        $this->logger->log("Successfully uploaded and processed image: {$uniqueFilename} (original: {$originalFilenameSafe}) from IP: {$userIp}", 'INFO');
        return ['success' => true, 'message' => 'Image uploaded and thumbnail generated successfully.', 'filename' => $uniqueFilename];
    }
}
?>
<?php

// --- public/upload_api.php ---

header('Content-Type: application/json');

define('BASE_DIR', __DIR__ . '/../');
define('PUBLIC_DIR', __DIR__ . '/');
define('UPLOAD_DIR_ORIGINAL', PUBLIC_DIR . 'uploads/original');
define('UPLOAD_DIR_THUMBNAIL', PUBLIC_DIR . 'uploads/thumbnails');
define('LOG_FILE', BASE_DIR . 'logs/upload.log');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('THUMBNAIL_WIDTH', 150);
define('THUMBNAIL_HEIGHT', 150);
define('MIN_IMAGE_WIDTH', 200);
define('MIN_IMAGE_HEIGHT', 200);
define('MAX_IMAGE_WIDTH', 4000);
define('MAX_IMAGE_HEIGHT', 4000);

if (!is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

require_once BASE_DIR . 'vendor/autoload.php';
require_once BASE_DIR . 'classes/Logger.php';
require_once BASE_DIR . 'classes/FileValidator.php';
require_once BASE_DIR . 'classes/ImageProcessor.php';
require_once BASE_DIR . 'handlers/ImageUploadHandler.php';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $logger = new Logger(LOG_FILE);
    $validator = new FileValidator();
    $processor = new ImageProcessor();

    $config = [
        'UPLOAD_DIR_ORIGINAL' => UPLOAD_DIR_ORIGINAL,
        'UPLOAD_DIR_THUMBNAIL' => UPLOAD_DIR_THUMBNAIL,
        'MAX_FILE_SIZE' => MAX_FILE_SIZE,
        'ALLOWED_MIME_TYPES' => ALLOWED_MIME_TYPES,
        'THUMBNAIL_WIDTH' => THUMBNAIL_WIDTH,
        'THUMBNAIL_HEIGHT' => THUMBNAIL_HEIGHT,
        'MIN_IMAGE_WIDTH' => MIN_IMAGE_WIDTH,
        'MIN_IMAGE_HEIGHT' => MIN_IMAGE_HEIGHT,
        'MAX_IMAGE_WIDTH' => MAX_IMAGE_WIDTH,
        'MAX_IMAGE_HEIGHT' => MAX_IMAGE_HEIGHT,
    ];

    $handler = new ImageUploadHandler($logger, $validator, $processor, $config);

    $userIp = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $result = $handler->handleUpload($_FILES['profile_picture'], $userIp);

    echo json_encode($result);
} else {
    $logger = new Logger(LOG_FILE);
    $userIp = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $logger->log("Invalid request to upload_api.php (not POST or no file) from IP: {$userIp}", 'WARNING');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>
<?php

// --- public/index.php ---

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

    <form id="uploadForm" enctype="multipart/form-data">
        <label for="profile_picture">Choose an image:</label>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif, image/webp" required>
        <br><br>
        <button type="submit">Upload Image</button>
    </form>

    <div id="message"></div>
    <div id="uploadedImageContainer" style="margin-top: 20px;">
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const messageDiv = document.getElementById('message');
            const imageContainer = document.getElementById('uploadedImageContainer');

            messageDiv.textContent = 'Uploading...';
            messageDiv.style.color = 'black';
            imageContainer.innerHTML = '';

            try {
                const response = await fetch('upload_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    messageDiv.textContent = result.message;
                    messageDiv.style.color = 'green';

                    if (result.filename) {
                        const originalUrl = 'uploads/original/' + result.filename;
                        const thumbnailUrl = 'uploads/thumbnails/' + result.filename;

                        imageContainer.innerHTML = `
                            <div>
                                <h3>Original Image:</h3>
                                <img src="${originalUrl}" alt="Uploaded Original" style="max-width: 300px; border: 1px solid #ccc;">
                            </div>
                            <div style="margin-top: 10px;">
                                <h3>Thumbnail:</h3>
                                <img src="${thumbnailUrl}" alt="Uploaded Thumbnail" style="width: 150px; height: 150px; object-fit: cover; border: 1px solid #ccc;">
                            </div>
                        `;
                    }
                } else {
                    messageDiv.textContent = 'Error: ' + result.message;
                    messageDiv.style.color = 'red';
                }
            } catch (error) {
                console.error('Fetch error:', error);
                messageDiv.textContent = 'An unexpected error occurred during upload.';
                messageDiv.style.color = 'red';
            }
        });
    </script>
</body>
</html>
?>