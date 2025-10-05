<?php
// config.php
// This file should be located in the root directory, e.g., /config.php

define('UPLOAD_BASE_DIR', __DIR__ . '/uploads/');
define('UPLOAD_DIR_ORIGINALS', UPLOAD_BASE_DIR . 'originals/');
define('UPLOAD_DIR_THUMBNAILS', UPLOAD_BASE_DIR . 'thumbnails/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('THUMBNAIL_WIDTH', 150);
define('THUMBNAIL_QUALITY', 80);

if (!is_dir(UPLOAD_DIR_ORIGINALS)) {
    mkdir(UPLOAD_DIR_ORIGINALS, 0755, true);
}
if (!is_dir(UPLOAD_DIR_THUMBNAILS)) {
    mkdir(UPLOAD_DIR_THUMBNAILS, 0755, true);
}
?>


<?php
// classes/ImageProcessor.php
// This file should be located at /classes/ImageProcessor.php

class ImageProcessor {
    private string $uploadDirOriginals;
    private string $uploadDirThumbnails;
    private int $maxFileSize;
    private int $thumbnailWidth;
    private int $thumbnailQuality;
    private array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif'
    ];

    public function __construct(
        string $uploadDirOriginals,
        string $uploadDirThumbnails,
        int $maxFileSize,
        int $thumbnailWidth,
        int $thumbnailQuality
    ) {
        if (!extension_loaded('gd')) {
            throw new Exception('GD extension is not loaded. Image processing requires GD.');
        }
        $this->uploadDirOriginals = $uploadDirOriginals;
        $this->uploadDirThumbnails = $uploadDirThumbnails;
        $this->maxFileSize = $maxFileSize;
        $this->thumbnailWidth = $thumbnailWidth;
        $this->thumbnailQuality = $thumbnailQuality;
    }

    public function processUploadedImage(array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception($this->getFileUploadErrorMessage($file['error']));
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size exceeds the allowed limit.');
        }

        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Uploaded file is not a valid image or is corrupted.');
        }

        $mimeType = $imageInfo['mime'];
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new Exception('Unsupported image type. Only JPEG, PNG, and GIF are allowed.');
        }

        $extension = image_type_to_extension($imageInfo[2], false);
        $uniqueFilename = uniqid('img_', true) . '.' . $extension;
        $originalFilePath = $this->uploadDirOriginals . $uniqueFilename;
        $thumbnailFilePath = $this->uploadDirThumbnails . 'thumb_' . $uniqueFilename;

        if (!move_uploaded_file($file['tmp_name'], $originalFilePath)) {
            throw new Exception('Failed to move uploaded file.');
        }

        $this->createThumbnail($originalFilePath, $thumbnailFilePath, $this->thumbnailWidth, $mimeType);

        return [
            'original_filename' => $uniqueFilename,
            'thumbnail_filename' => 'thumb_' . $uniqueFilename
        ];
    }

    private function createThumbnail(string $sourcePath, string $destinationPath, int $targetWidth, string $mimeType): void {
        list($originalWidth, $originalHeight) = getimagesize($sourcePath);

        $targetHeight = (int) round($originalHeight * ($targetWidth / $originalWidth));

        $sourceImage = $this->loadImage($sourcePath, $mimeType);
        $thumbnailImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($mimeType === 'image/png') {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
            imagefilledrectangle($thumbnailImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        } elseif ($mimeType === 'image/gif') {
            $transparentIndex = imagecolortransparent($sourceImage);
            if ($transparentIndex >= 0) {
                $transparentColor = imagecolorsforindex($sourceImage, $transparentIndex);
                $transparentIndex = imagecolorallocate($thumbnailImage, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
                imagefill($thumbnailImage, 0, 0, $transparentIndex);
                imagecolortransparent($thumbnailImage, $transparentIndex);
            }
        }

        imagecopyresampled(
            $thumbnailImage,
            $sourceImage,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            $originalWidth, $originalHeight
        );

        $this->saveImage($thumbnailImage, $destinationPath, $mimeType);

        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
    }

    private function loadImage(string $filePath, string $mimeType): GdImage {
        switch ($mimeType) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filePath);
            case 'image/png':
                return imagecreatefrompng($filePath);
            case 'image/gif':
                return imagecreatefromgif($filePath);
            default:
                throw new Exception('Unsupported image type for loading.');
        }
    }

    private function saveImage(GdImage $image, string $filePath, string $mimeType): void {
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($image, $filePath, $this->thumbnailQuality);
                break;
            case 'image/png':
                $pngQuality = (int) round((9 * $this->thumbnailQuality) / 100);
                imagepng($image, $filePath, $pngQuality);
                break;
            case 'image/gif':
                imagegif($image, $filePath);
                break;
            default:
                throw new Exception('Unsupported image type for saving.');
        }
    }

    private function getFileUploadErrorMessage(int $errorCode): string {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error.',
        };
    }
}
?>


<?php
// handlers/upload_profile_picture.php
// This file should be located at /handlers/upload_profile_picture.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/ImageProcessor.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

header('Content-Type: application/json');

try {
    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception('No file uploaded.');
    }

    $imageProcessor = new ImageProcessor(
        UPLOAD_DIR_ORIGINALS,
        UPLOAD_DIR_THUMBNAILS,
        MAX_FILE_SIZE,
        THUMBNAIL_WIDTH,
        THUMBNAIL_QUALITY
    );
    $result = $imageProcessor->processUploadedImage($_FILES['profile_picture']);

    echo json_encode([
        'success' => true,
        'message' => 'Image uploaded and thumbnail generated successfully.',
        'original_filename' => $result['original_filename'],
        'thumbnail_filename' => $result['thumbnail_filename'],
        'original_url' => '/uploads/originals/' . $result['original_filename'],
        'thumbnail_url' => '/uploads/thumbnails/' . $result['thumbnail_filename']
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


<?php
// public/upload_form.php
// This file should be located at /public/upload_form.php

require_once __DIR__ . '/../config.php';
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

    <form action="/handlers/upload_profile_picture.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_FILE_SIZE; ?>">
        <label for="profile_picture">Choose Image:</label>
        <input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif" required>
        <br><br>
        <button type="submit">Upload Picture</button>
    </form>

    <div id="uploadStatus"></div>
    <div id="uploadedImages"></div>

    <script>
        document.querySelector('form').addEventListener('submit', async function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const uploadStatusDiv = document.getElementById('uploadStatus');
            const uploadedImagesDiv = document.getElementById('uploadedImages');

            uploadStatusDiv.innerHTML = 'Uploading...';
            uploadedImagesDiv.innerHTML = '';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (response.ok) {
                    uploadStatusDiv.innerHTML = '<p style="color: green;">' + result.message + '</p>';
                    if (result.thumbnail_url) {
                        uploadedImagesDiv.innerHTML = `
                            <h2>Uploaded Images:</h2>
                            <p>Original: <a href="${result.original_url}" target="_blank">${result.original_filename}</a></p>
                            <p>Thumbnail:</p>
                            <img src="${result.thumbnail_url}" alt="Thumbnail" style="max-width: <?php echo THUMBNAIL_WIDTH; ?>px; border: 1px solid #ccc;">
                        `;
                    }
                } else {
                    uploadStatusDiv.innerHTML = '<p style="color: red;">Error: ' + result.message + '</p>';
                }
            } catch (error) {
                console.error('Fetch error:', error);
                uploadStatusDiv.innerHTML = '<p style="color: red;">An unexpected error occurred during upload.</p>';
            }
        });
    </script>
</body>
</html>
?>