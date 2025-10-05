<?php
<!DOCTYPE html>
<html>
<head>
    <title>Upload Profile Picture</title>
</head>
<body>
    <form action="upload_handler.php" method="post" enctype="multipart/form-data">
        <label for="profile_image">Select Profile Picture:</label>
        <input type="file" name="profile_image" id="profile_image" accept="image/*" required>
        <button type="submit">Upload</button>
    </form>

    <div id="result"></div>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('result');
                if (data.success) {
                    resultDiv.innerHTML = '<p>Upload successful!</p><img src="' + data.thumbnail_url + '" alt="Thumbnail">';
                } else {
                    resultDiv.innerHTML = '<p>Error: ' + data.error + '</p>';
                }
            })
            .catch(error => {
                document.getElementById('result').innerHTML = '<p>Upload failed</p>';
            });
        });
    </script>
</body>
</html>


<?php
require_once 'vendor/autoload.php';

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

header('Content-Type: application/json');

function logUploadAttempt($message, $success = false) {
    $logFile = __DIR__ . '/logs/upload.log';
    $logDir = dirname($logFile);
    
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = "[{$timestamp}] [{$status}] IP: {$ip} - {$message}\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function sanitizeFilename($filename) {
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    return $filename;
}

function generateUniqueFilename($originalName) {
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $uniqueId = uniqid() . '_' . bin2hex(random_bytes(8));
    return $uniqueId . '.' . $extension;
}

function validateImageFile($file) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
    $maxFileSize = 10 * 1024 * 1024;
    $maxWidth = 4000;
    $maxHeight = 4000;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return 'Upload failed';
    }
    
    if ($file['size'] > $maxFileSize) {
        return 'File too large';
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return 'Invalid file type';
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return 'Invalid file extension';
    }
    
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return 'Invalid image file';
    }
    
    if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
        return 'Image dimensions too large';
    }
    
    return true;
}

try {
    if (!isset($_FILES['profile_image'])) {
        throw new Exception('No file uploaded');
    }
    
    $uploadedFile = $_FILES['profile_image'];
    
    $validation = validateImageFile($uploadedFile);
    if ($validation !== true) {
        throw new Exception($validation);
    }
    
    $thumbnailDir = __DIR__ . '/thumbnails';
    if (!file_exists($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }
    
    $sanitizedOriginalName = sanitizeFilename($uploadedFile['name']);
    $uniqueFilename = generateUniqueFilename($sanitizedOriginalName);
    $thumbnailPath = $thumbnailDir . '/' . $uniqueFilename;
    
    $manager = new ImageManager(new Driver());
    $image = $manager->read($uploadedFile['tmp_name']);
    
    $image->scaleDown(200, 200);
    
    $image->save($thumbnailPath);
    
    $thumbnailUrl = '/thumbnails/' . $uniqueFilename;
    
    logUploadAttempt("Thumbnail created: {$uniqueFilename}", true);
    
    echo json_encode([
        'success' => true,
        'thumbnail_url' => $thumbnailUrl
    ]);
    
} catch (Exception $e) {
    logUploadAttempt("Upload failed: " . $e->getMessage(), false);
    
    echo json_encode([
        'success' => false,
        'error' => 'Upload failed'
    ]);
}
?>


json
{
    "require": {
        "intervention/image": "^3.0"
    }
}
?>