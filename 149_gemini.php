<?php
// index.php - This file provides the HTML form for image upload.
// It uses PHP's echo statements to ensure the entire output is PHP code, as requested.

echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>Profile Picture Upload</title>';
echo '</head>';
echo '<body>';
echo '<h1>Upload Profile Picture</h1>';
echo '<form action="upload_handler.php" method="POST" enctype="multipart/form-data">';
echo '<label for="profile_picture">Choose Image:</label>';
echo '<input type="file" name="profile_picture" id="profile_picture" accept="image/jpeg, image/png, image/gif, image/webp" required>';
echo '<br><br>';
echo '<button type="submit">Upload & Generate Thumbnail</button>';
echo '</form>';
echo '</body>';
echo '</html>';
?>

<?php
// upload_handler.php - This file handles the uploaded image, generates a thumbnail, and saves it.

// Configuration
define('THUMBNAIL_DIR', __DIR__ . '/thumbnails/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('THUMBNAIL_WIDTH', 150);
define('THUMBNAIL_HEIGHT', 150);

// Allowed MIME types for images
$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
];

// Ensure the thumbnail directory exists and is writable
if (!is_dir(THUMBNAIL_DIR)) {
    // Attempt to create the directory with 0755 permissions (user rwx, group rx, others rx)
    // The 'true' argument allows for recursive directory creation
    if (!mkdir(THUMBNAIL_DIR, 0755, true)) {
        exit('Error: Failed to create thumbnail directory. Please check permissions.');
    }
}

// Basic security checks and request validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Redirect to the form or display an error for invalid request methods
    header('Location: index.php');
    exit('Invalid request method.');
}

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    // Handle specific file upload errors
    switch ($_FILES['profile_picture']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            exit('File is too large. Maximum size allowed: ' . (MAX_FILE_SIZE / (1024 * 1024)) . ' MB.');
        case UPLOAD_ERR_NO_FILE:
            exit('No file was uploaded. Please select an image.');
        case UPLOAD_ERR_PARTIAL:
            exit('File upload was interrupted. Please try again.');
        case UPLOAD_ERR_CANT_WRITE:
            exit('Failed to write file to disk. Check server permissions.');
        case UPLOAD_ERR_NO_TMP_DIR:
            exit('Missing a temporary folder for uploads.');
        case UPLOAD_ERR_EXTENSION:
            exit('A PHP extension stopped the file upload.');
        default:
            exit('An unknown upload error occurred.');
    }
}

$fileTmpPath = $_FILES['profile_picture']['tmp_name'];
$fileName = $_FILES['profile_picture']['name'];
$fileSize = $_FILES['profile_picture']['size'];

// File size validation
if ($fileSize > MAX_FILE_SIZE) {
    exit('File is too large. Maximum size allowed: ' . (MAX_FILE_SIZE / (1024 * 1024)) . ' MB.');
}

// MIME type validation using finfo_open for robust security against spoofed file types
$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    exit('Server error: Could not open fileinfo database.');
}
$mimeType = finfo_file($finfo, $fileTmpPath);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimeTypes)) {
    exit('Invalid file type. Only JPEG, PNG, GIF, and WebP images are allowed.');
}

// Further image validation to ensure it's a real image and not malicious code
$imageInfo = getimagesize($fileTmpPath);
if ($imageInfo === false) {
    exit('Invalid image file. The uploaded file is not a valid image.');
}

// Generate a unique filename for the thumbnail to prevent overwriting and security issues
$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
$uniqueFileName = uniqid('thumb_', true) . '.' . $fileExtension;
$thumbnailPath = THUMBNAIL_DIR . $uniqueFileName;

// Thumbnail generation logic
$sourceImage = null;
switch ($mimeType) {
    case 'image/jpeg':
        $sourceImage = imagecreatefromjpeg($fileTmpPath);
        break;
    case 'image/png':
        $sourceImage = imagecreatefrompng($fileTmpPath);
        break;
    case 'image/gif':
        $sourceImage = imagecreatefromgif($fileTmpPath);
        break;
    case 'image/webp':
        if (function_exists('imagecreatefromwebp')) {
            $sourceImage = imagecreatefromwebp($fileTmpPath);
        } else {
            exit('WebP image support is not enabled on this server (GD library).');
        }
        break;
    default:
        exit('Unsupported image type for thumbnail generation.');
}

if ($sourceImage === false) {
    exit('Failed to create image resource from the uploaded file.');
}

$originalWidth = imagesx($sourceImage);
$originalHeight = imagesy($sourceImage);

// Calculate aspect ratio to maintain proportions during thumbnail creation
$ratio = $originalWidth / $originalHeight;
$thumbWidth = THUMBNAIL_WIDTH;
$thumbHeight = THUMBNAIL_HEIGHT;

if ($thumbWidth / $thumbHeight > $ratio) {
    $thumbWidth = $thumbHeight * $ratio;
} else {
    $thumbHeight = $thumbWidth / $ratio;
}

$thumbnailImage = imagecreatetruecolor((int)$thumbWidth, (int)$thumbHeight);

// Preserve transparency for PNG and GIF images
if ($mimeType == 'image/png' || $mimeType == 'image/gif') {
    imagealphablending($thumbnailImage, false);
    imagesavealpha($thumbnailImage, true);
    $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
    imagefilledrectangle($thumbnailImage, 0, 0, (int)$thumbWidth, (int)$thumbHeight, $transparent);
}

// Resample the image to create the thumbnail
imagecopyresampled(
    $thumbnailImage,
    $sourceImage,
    0, 0, 0, 0,
    (int)$thumbWidth, (int)$thumbHeight,
    $originalWidth, $originalHeight
);

// Save the generated thumbnail
$saved = false;
switch ($mimeType) {
    case 'image/jpeg':
        $saved = imagejpeg($thumbnailImage, $thumbnailPath, 90); // Quality 0-100
        break;
    case 'image/png':
        $saved = imagepng($thumbnailImage, $thumbnailPath, 9); // Compression 0-9
        break;
    case 'image/gif':
        $saved = imagegif($thumbnailImage, $thumbnailPath);
        break;
    case 'image/webp':
        if (function_exists('imagewebp')) {
            $saved = imagewebp($thumbnailImage, $thumbnailPath, 90); // Quality 0-100
        }
        break;
}

// Free up memory by destroying the image resources
imagedestroy($sourceImage);
imagedestroy($thumbnailImage);

if ($saved) {
    // Success message for the user. In a production environment, you might
    // redirect to a profile page or return a JSON response.
    echo 'Thumbnail generated and saved successfully: ' . htmlspecialchars($uniqueFileName);
} else {
    exit('Failed to save the thumbnail image.');
}
?>