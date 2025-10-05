<?php
echo <<<HTML
<!DOCTYPE html>
<html>
<head><title>Image Upload</title></head>
<body>
<form action="upload_handler.php" method="POST" enctype="multipart/form-data">
  <label>Select image to upload:</label>
  <input type="file" name="image" accept="image/*" required>
  <button type="submit">Upload</button>
</form>
</body>
</html>
HTML;
?><?php
require __DIR__ . '/vendor/autoload.php';
use Intervention\Image\ImageManagerStatic as Image;

$UPLOAD_DIR = __DIR__ . '/uploads';
$ORIG_DIR = $UPLOAD_DIR . '/orig';
$THUMB_DIR = $UPLOAD_DIR . '/thumbs';
$LOG_DIR = __DIR__ . '/logs';
$LOG_FILE = $LOG_DIR . '/upload.log';

$THUMB_WIDTH = 200;
$THUMB_HEIGHT = 200;
$MAX_FILE_SIZE = 5 * 1024 * 1024;
$MIN_WIDTH = 100;
$MIN_HEIGHT = 100;
$MAX_WIDTH = 1920;
$MAX_HEIGHT = 1080;

$ALLOWED_MIME = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'image/bmp' => 'bmp',
];

function ensure_dirs($dirs) {
  foreach ($dirs as $d) {
    if (!is_dir($d)) {
      mkdir($d, 0755, true);
    }
  }
}
ensure_dirs([$UPLOAD_DIR, $ORIG_DIR, $THUMB_DIR, $LOG_DIR]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo 'Invalid request';
  exit;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$log = function($message) use ($LOG_FILE, $clientIp) {
  $line = date('Y-m-d H:i:s') . " [INFO] {$message} [IP: {$clientIp}]" . PHP_EOL;
  file_put_contents($LOG_FILE, $line, FILE_APPEND);
};

$fail = function($message) use ($LOG_FILE, $clientIp) {
  $line = date('Y-m-d H:i:s') . " [ERROR] {$message} [IP: {$clientIp}]" . PHP_EOL;
  file_put_contents($LOG_FILE, $line, FILE_APPEND);
  http_response_code(400);
  echo 'An error occurred while processing the upload.';
  exit;
};

if (!isset($_FILES['image'])) { $fail('No file uploaded.'); }

$upload = $_FILES['image'];
if ($upload['error'] !== UPLOAD_ERR_OK) {
  $fail('Upload error code: ' . $upload['error']);
}

if ($upload['size'] <= 0 || $upload['size'] > $MAX_FILE_SIZE) {
  $fail('Invalid file size.');
}

$tmpPath = $upload['tmp_name'];
if (!is_uploaded_file($tmpPath)) {
  $fail('Invalid upload source.');
}

$info = @getimagesize($tmpPath);
if ($info === false) {
  $fail('Uploaded file is not a valid image.');
}
$width = $info[0];
$height = $info[1];
$mime = $info['mime'] ?? '';

if (!isset($ALLOWED_MIME[$mime])) {
  $fail('Unsupported image type.');
}
if ($width < $MIN_WIDTH || $height < $MIN_HEIGHT || $width > $MAX_WIDTH || $height > $MAX_HEIGHT) {
  $fail('Image dimensions are not allowed.');
}

$originalName = $upload['name'] ?? 'image';
$basename = basename($originalName);
$basename = preg_replace('/[^A-Za-z0-9._-]/', '_', $basename);
$ext = $ALLOWED_MIME[$mime];
$nameBase = pathinfo($basename, PATHINFO_FILENAME);
$nameBase = preg_replace('/[^A-Za-z0-9-_]+/', '_', $nameBase);
$filename = $nameBase . '_' . uniqid() . '.' . $ext;

$targetPath = $ORIG_DIR . '/' . $filename;
if (!move_uploaded_file($tmpPath, $targetPath)) {
  $fail('Failed to save uploaded file.');
}

try {
  $thumbPath = $THUMB_DIR . '/' . $filename;
  $image = Image::make($targetPath)->fit($THUMB_WIDTH, $THUMB_HEIGHT);
  $image->save($thumbPath, 90);
  $log('Uploaded and thumbnail created: ' . $filename);
} catch (\Exception $e) {
  $fail('Image processing failed.');
}

$webThumbPath = '/uploads/thumbs/' . $filename;
echo '<!DOCTYPE html><html><body>';
echo '<p>Upload successful.</p>';
echo '<p>Thumbnail: <a href="' . htmlspecialchars($webThumbPath) . '">View</a></p>';
echo '</body></html>';
?>