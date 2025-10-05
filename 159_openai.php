<?php
declare(strict_types=1);

$LOG_FILE = __DIR__ . '/logs/upload.log';
$THUMB_DIR = __DIR__ . '/thumbnails';
$ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif'];
$MAX_FILE_SIZE = 5 * 1024 * 1024;
$MAX_DIMENSION = 10000;

function logEvent(string $message, string $logFile): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    $dir = dirname($logFile);
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    @file_put_contents($logFile, $line, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'error' => 'Server error'];
    try {
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            logEvent('FAILURE: No file uploaded from ' . $ip, $LOG_FILE);
            throw new Exception('No file uploaded');
        }
        $file = $_FILES['image'];
        if ($file['size'] <= 0) { throw new Exception('Invalid file size'); }
        if ($file['size'] > $MAX_FILE_SIZE) { throw new Exception('File too large'); }

        $tmpName = $file['tmp_name'];
        if (!is_uploaded_file($tmpName)) { throw new Exception('Invalid upload'); }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $tmpName);
        finfo_close($finfo);
        if (!in_array($mime, $ALLOWED_MIME, true)) { throw new Exception('Unsupported file type'); }

        $imageInfo = getimagesize($tmpName);
        if ($imageInfo === false) { throw new Exception('Invalid image'); }
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        if ($width <= 0 || $height <= 0) { throw new Exception('Invalid image dimensions'); }
        if ($width > $MAX_DIMENSION || $height > $MAX_DIMENSION) { throw new Exception('Image dimensions too large'); }

        if (!file_exists(__DIR__ . '/vendor/autoload.php')) { throw new Exception('Autoloader not found'); }
        require __DIR__ . '/vendor/autoload.php';

        if (!is_dir($THUMB_DIR)) {
            if (!mkdir($THUMB_DIR, 0755, true)) { throw new Exception('Cannot create thumbnail directory'); }
        }

        $originalName = $file['name'] ?? 'upload';
        $basename = basename($originalName);
        $basename = preg_replace('/[^A-Za-z0-9._-]/', '_', $basename);
        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        $ext = in_array($ext, ['jpg','jpeg','png','gif'], true) ? $ext : 'jpg';
        $thumbFilename = 'thumb_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $thumbPath = $THUMB_DIR . DIRECTORY_SEPARATOR . $thumbFilename;
        $maxW = 200;
        $maxH = 200;

        $image = \Intervention\Image\ImageManagerStatic::make($tmpName);
        $image->resize($maxW, $maxH, function($constraint){
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $image->save($thumbPath);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $thumbUrl = $protocol . '://' . $host . '/thumbnails/' . $thumbFilename;

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        logEvent('SUCCESS: Uploaded image from ' . $ip . ' -> ' . $thumbFilename, $LOG_FILE);

        $response = ['success' => true, 'thumbnail_url' => $thumbUrl];
    } catch (Exception $e) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        logEvent('FAILURE: ' . $e->getMessage() . ' from ' . $ip, $LOG_FILE);
        $response = ['success' => false, 'error' => 'Failed to process image'];
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Profile Picture</title>
</head>
<body>
<h1>Upload Profile Picture</h1>
<form id="uploadForm" enctype="multipart/form-data" method="post" action="upload_handler.php">
    <input type="file" name="image" accept="image/*" required />
    <button type="submit">Upload</button>
</form>
<div id="result"></div>
<script>
document.getElementById('uploadForm').addEventListener('submit', function(e){
    e.preventDefault();
    var form = e.target;
    var formData = new FormData(form);
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: { 'Accept': 'application/json' }
    })
    .then(function(resp){ return resp.json(); })
    .then(function(data){
        var result = document.getElementById('result');
        result.innerHTML = '';
        if (data.success) {
            var a = document.createElement('a');
            a.href = data.thumbnail_url;
            a.textContent = 'Thumbnail created: ' + data.thumbnail_url;
            a.target = '_blank';
            result.appendChild(a);
            var br = document.createElement('br');
            result.appendChild(br);
            var img = document.createElement('img');
            img.src = data.thumbnail_url;
            img.alt = 'Thumbnail';
            img.style.maxWidth = '200px';
            img.style.maxHeight = '200px';
            result.appendChild(img);
        } else {
            var span = document.createElement('span');
            span.textContent = 'Error: ' + (data.error || 'Upload failed');
            result.appendChild(span);
        }
    })
    .catch(function(){
        var result = document.getElementById('result');
        result.textContent = 'Error: Upload failed';
    });
});
</script>
</body>
</html>
?>