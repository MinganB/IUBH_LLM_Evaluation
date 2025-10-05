<?php
// Frontend: index.php
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Image Upload and Thumbnail</title>
</head>
<body>
  <h1>Upload an image</h1>
  <form id="uploadForm" action="upload_handler.php" method="POST" enctype="multipart/form-data">
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Upload</button>
  </form>
  <div id="result"></div>
  <script>
    document.getElementById('uploadForm').addEventListener('submit', async function(e){
      e.preventDefault();
      const formData = new FormData(this);
      const resp = await fetch(this.action, {
        method: 'POST',
        body: formData
      });
      const data = await resp.json().catch(()=>({success:false, error:'Invalid response'}));
      const resultDiv = document.getElementById('result');
      resultDiv.innerHTML = '';
      if(data.success){
        const img = document.createElement('img');
        img.src = data.thumbnail;
        img.alt = 'Thumbnail';
        resultDiv.appendChild(img);
      } else {
        const p = document.createElement('p');
        p.textContent = data.error || 'Upload failed';
        resultDiv.appendChild(p);
      }
    });
  </script>
</body>
</html>

<?php
// Backend: upload_handler.php
ini_set('display_errors', '0');
error_reporting(0);

$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0775, true);
}
$logFile = $logDir . '/upload.log';
function logEvent($message, $logFile) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $entry = date('Y-m-d H:i:s') . " | $ip | " . $message . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

header('Content-Type: application/json');
$response = ['success' => false, 'error' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        logEvent('Upload error code ' . $file['error'], $logFile);
        echo json_encode(['success' => false, 'error' => 'Invalid file upload']);
        exit;
    }

    $tmpPath = $file['tmp_name'];
    if (!is_uploaded_file($tmpPath) || !is_file($tmpPath)) {
        logEvent('Invalid upload temp path', $logFile);
        echo json_encode(['success' => false, 'error' => 'Invalid upload']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);
    } else {
        $mime = mime_content_type($tmpPath);
    }

    $allowedMimes = ['image/jpeg','image/png','image/gif'];
    if (!in_array($mime, $allowedMimes)) {
        logEvent('Unsupported mime type: ' . $mime, $logFile);
        echo json_encode(['success' => false, 'error' => 'Unsupported file type']);
        exit;
    }

    $size = (int)$file['size'];
    $maxSize = 5 * 1024 * 1024;
    if ($size <= 0 || $size > $maxSize) {
        logEvent('Invalid file size: ' . $size, $logFile);
        echo json_encode(['success' => false, 'error' => 'File size is invalid']);
        exit;
    }

    $dimensions = @getimagesize($tmpPath);
    if ($dimensions === false) {
        logEvent('Unable to read image dimensions', $logFile);
        echo json_encode(['success' => false, 'error' => 'Invalid image file']);
        exit;
    }
    $origWidth = $dimensions[0];
    $origHeight = $dimensions[1];
    $maxW = 5000;
    $maxH = 5000;
    if ($origWidth > $maxW || $origHeight > $maxH) {
        logEvent("Image dimensions too large: ${origWidth}x${origHeight}", $logFile);
        echo json_encode(['success' => false, 'error' => 'Image dimensions are too large']);
        exit;
    }

    $autoload = __DIR__ . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        logEvent('Composer autoload not found', $logFile);
        echo json_encode(['success' => false, 'error' => 'Server not configured']);
        exit;
    }
    require $autoload;
    use Intervention\Image\ImageManagerStatic as Image;

    $originalName = $file['name'] ?? '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExts = ['jpg','jpeg','png','gif'];
    if (!in_array($extension, $allowedExts)) {
        if ($mime === 'image/jpeg') $extension = 'jpg';
        elseif ($mime === 'image/png') $extension = 'png';
        elseif ($mime === 'image/gif') $extension = 'gif';
        else $extension = 'jpg';
    }

    $thumbDir = __DIR__ . '/thumbnails';
    if (!is_dir($thumbDir)) {
        if (!mkdir($thumbDir, 0775, true)) {
            logEvent('Failed to create thumbnails directory', $logFile);
            echo json_encode(['success' => false, 'error' => 'Server error']);
            exit;
        }
    }

    $thumbName = uniqid('thumb_', true) . '.' . $extension;
    $thumbPath = $thumbDir . '/' . $thumbName;

    try {
        $image = Image::make($tmpPath);
        $image->resize(200, 200, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $image->save($thumbPath);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $protocol . '://' . $host;
        $thumbUrl = $base . '/thumbnails/' . $thumbName;
        logEvent('Upload success: ' . $thumbName, $logFile);
        echo json_encode(['success' => true, 'thumbnail' => $thumbUrl]);
        exit;
    } catch (Exception $e) {
        logEvent('Thumbnail creation failed: ' . $e->getMessage(), $logFile);
        echo json_encode(['success' => false, 'error' => 'Failed to create thumbnail']);
        exit;
    }
} else {
    logEvent('No file uploaded', $logFile);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}
?>