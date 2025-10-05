<?php
echo <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Upload Profile Picture</title>
</head>
<body>
<form action="/handlers/upload_handler.php" method="POST" enctype="multipart/form-data">
<input type="file" name="image" accept="image/*" required>
<button type="submit">Upload</button>
</form>
</body>
</html>
HTML;
?>


<?php
class ImageProcessor {
  public static function createThumbnail($sourcePath, $destinationPath, $width, $height, $format = 'jpg') {
    $image = \Intervention\Image\ImageManagerStatic::make($sourcePath)->resize($width, $height, function($constraint){
      $constraint->aspectRatio();
      $constraint->upsize();
    });
    $fmt = strtolower($format);
    if (!in_array($fmt, ['jpg','jpeg','png','gif'])) {
      $fmt = 'jpg';
    }
    $image->encode($fmt, 90);
    $image->save($destinationPath);
    return true;
  }
}
?>


<?php
http_response_code(200);
header('Content-Type: application/json');

$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
$logFile = $logDir . '/upload.log';
$thumbDir = __DIR__ . '/../thumbnails';
if (!is_dir($thumbDir)) { mkdir($thumbDir, 0755, true); }

$maxSize = 5 * 1024 * 1024;

function logEvent($success, $message, $logFile) {
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $entry = date('Y-m-d H:i:s') . " [IP: $ip] " . ($success ? 'SUCCESS' : 'FAIL') . " - " . $message . PHP_EOL;
  @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

try {
  if (!isset($_FILES['image']) || !isset($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    throw new Exception('No file uploaded.');
  }

  $tmpName = $_FILES['image']['tmp_name'];
  $fileSize = $_FILES['image']['size'] ?? 0;
  if ($fileSize <= 0 || $fileSize > $maxSize) {
    throw new Exception('Invalid file size.');
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($finfo, $tmpName);
  finfo_close($finfo);

  $allowed = ['image/jpeg','image/png','image/gif'];
  if (!in_array($mime, $allowed)) {
    throw new Exception('Unsupported file type.');
  }

  $dimensions = getimagesize($tmpName);
  if ($dimensions === false) {
    throw new Exception('Invalid image file.');
  }
  $width = $dimensions[0];
  $height = $dimensions[1];
  if ($width <= 0 || $height <= 0) {
    throw new Exception('Invalid image dimensions.');
  }

  $ext = 'jpg';
  if ($mime === 'image/png') $ext = 'png';
  elseif ($mime === 'image/gif') $ext = 'gif';

  $filename = uniqid('thumb_', true) . ".$ext";
  $thumbPath = $thumbDir . '/' . $filename;

  if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    throw new Exception('Server misconfiguration.');
  }
  require_once __DIR__ . '/../vendor/autoload.php';
  require_once __DIR__ . '/../classes/ImageProcessor.php';

  ImageProcessor::createThumbnail($tmpName, $thumbPath, 200, 200, $ext);

  $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'];
  $thumbUrl = $protocol . '://' . $host . '/thumbnails/' . $filename;

  logEvent(true, 'Thumbnail created: ' . $filename, $logFile);

  echo json_encode(['success' => true, 'thumbnail' => $thumbUrl]);
} catch (Throwable $e) {
  logEvent(false, $e->getMessage(), $logFile);
  http_response_code(400);
  echo json_encode(['success' => false, 'error' => 'Upload failed.']);
}
?>