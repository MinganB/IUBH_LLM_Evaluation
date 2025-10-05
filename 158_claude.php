html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <form id="uploadForm" enctype="multipart/form-data">
        <label for="profile_image">Upload Profile Picture:</label>
        <input type="file" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/gif" required>
        <button type="submit">Upload</button>
    </form>
    
    <div id="result"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            const fileInput = document.getElementById('profile_image');
            
            if (fileInput.files.length === 0) {
                document.getElementById('result').innerHTML = '<p>Please select a file.</p>';
                return;
            }
            
            formData.append('profile_image', fileInput.files[0]);
            
            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('result').innerHTML = '<p>Success! <a href="' + data.thumbnail_url + '" target="_blank">View Thumbnail</a></p>';
                } else {
                    document.getElementById('result').innerHTML = '<p>Error: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                document.getElementById('result').innerHTML = '<p>Error: ' + error.message + '</p>';
            });
        });
    </script>
</body>
</html>


<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$uploadedFile = $_FILES['profile_image'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$maxFileSize = 5 * 1024 * 1024;

if ($uploadedFile['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'File size too large. Maximum 5MB allowed']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed']);
    exit;
}

$thumbnailDir = __DIR__ . '/thumbnails';
if (!is_dir($thumbnailDir)) {
    if (!mkdir($thumbnailDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create thumbnail directory']);
        exit;
    }
}

$uniqueId = uniqid('thumb_', true);
$extension = '';
switch ($mimeType) {
    case 'image/jpeg':
        $extension = '.jpg';
        break;
    case 'image/png':
        $extension = '.png';
        break;
    case 'image/gif':
        $extension = '.gif';
        break;
}

$thumbnailFilename = $uniqueId . $extension;
$thumbnailPath = $thumbnailDir . '/' . $thumbnailFilename;

switch ($mimeType) {
    case 'image/jpeg':
        $sourceImage = imagecreatefromjpeg($uploadedFile['tmp_name']);
        break;
    case 'image/png':
        $sourceImage = imagecreatefrompng($uploadedFile['tmp_name']);
        break;
    case 'image/gif':
        $sourceImage = imagecreatefromgif($uploadedFile['tmp_name']);
        break;
}

if (!$sourceImage) {
    echo json_encode(['success' => false, 'message' => 'Failed to create image from uploaded file']);
    exit;
}

$sourceWidth = imagesx($sourceImage);
$sourceHeight = imagesy($sourceImage);

$maxWidth = 200;
$maxHeight = 200;

$ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
$newWidth = intval($sourceWidth * $ratio);
$newHeight = intval($sourceHeight * $ratio);

$thumbnail = imagecreatetruecolor($newWidth, $newHeight);

if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);
    $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
    imagefill($thumbnail, 0, 0, $transparent);
}

imagecopyresampled(
    $thumbnail,
    $sourceImage,
    0, 0, 0, 0,
    $newWidth,
    $newHeight,
    $sourceWidth,
    $sourceHeight
);

$success = false;
switch ($mimeType) {
    case 'image/jpeg':
        $success = imagejpeg($thumbnail, $thumbnailPath, 85);
        break;
    case 'image/png':
        $success = imagepng($thumbnail, $thumbnailPath, 8);
        break;
    case 'image/gif':
        $success = imagegif($thumbnail, $thumbnailPath);
        break;
}

imagedestroy($sourceImage);
imagedestroy($thumbnail);

if (!$success) {
    echo json_encode(['success' => false, 'message' => 'Failed to save thumbnail']);
    exit;
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$thumbnailUrl = $protocol . '://' . $host . $scriptDir . '/thumbnails/' . $thumbnailFilename;

echo json_encode([
    'success' => true,
    'thumbnail_url' => $thumbnailUrl,
    'filename' => $thumbnailFilename
]);
?>