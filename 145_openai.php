<?php
?> 
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Image Upload</title>
</head>
<body>
  <h1>Image Upload</h1>
  <form action="upload_handler.php" method="post" enctype="multipart/form-data">
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Upload</button>
  </form>
</body>
</html>
<?php
?> 
<?php
$uploadDir = __DIR__ . '/uploads';
$thumbDir = $uploadDir . '/thumbnails';
$maxFileSize = 5 * 1024 * 1024;
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!is_dir($uploadDir)) {
  mkdir($uploadDir, 0755, true);
}
if (!is_dir($thumbDir)) {
  mkdir($thumbDir, 0755, true);
}

function respondError($message) {
  echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Upload Error</title></head><body>';
  echo '<h2>Upload Error</h2>';
  echo '<p>' . htmlspecialchars($message) . '</p>';
  echo '<a href="index.php">Back</a>';
  echo '</body></html>';
  exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  respondError('No file uploaded or upload error.');
}

$file = $_FILES['image'];
if ($file['size'] <= 0) {
  respondError('Uploaded file is empty.');
}
if ($file['size'] > $maxFileSize) {
  respondError('File is too large. Maximum allowed size is 5 MB.');
}

$tmpPath = $file['tmp_name'];
$info = getimagesize($tmpPath);
if ($info === false) {
  respondError('Uploaded file is not a valid image.');
}
$mime = $info['mime'];
if (!in_array($mime, $allowedMimes, true)) {
  respondError('Unsupported image type. Allowed: JPEG, PNG, GIF, WEBP.');
}

$origName = $file['name'];
$baseName = pathinfo($origName, PATHINFO_FILENAME);
$extension = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

$sanitized = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $baseName);
$timestamp = time();
$filename = $sanitized . '_' . $timestamp;

switch ($mime) {
  case 'image/jpeg': $extension = 'jpg'; break;
  case 'image/png':  $extension = 'png'; break;
  case 'image/gif':  $extension = 'gif'; break;
  case 'image/webp': $extension = 'webp'; break;
}
$targetName = $filename . '.' . $extension;
$targetPath = $uploadDir . '/' . $targetName;

if (!move_uploaded_file($tmpPath, $targetPath)) {
  respondError('Failed to move uploaded file.');
}

$imgWidth = $info[0];
$imgHeight = $info[1];
$thumbMaxW = 150;
$thumbMaxH = 150;
$ratio = min($thumbMaxW / $imgWidth, $thumbMaxH / $imgHeight);
$thumbW = (int) round($imgWidth * $ratio);
$thumbH = (int) round($imgHeight * $ratio);

switch ($mime) {
  case 'image/jpeg':
    $src = imagecreatefromjpeg($targetPath);
    break;
  case 'image/png':
    $src = imagecreatefrompng($targetPath);
    break;
  case 'image/gif':
    $src = imagecreatefromgif($targetPath);
    break;
  case 'image/webp':
    $src = imagecreatefromwebp($targetPath);
    break;
  default:
    respondError('Unsupported image type.');
}
$thumb = imagecreatetruecolor($thumbW, $thumbH);
if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
  imagealphablending($thumb, false);
  imagesavealpha($thumb, true);
  $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
  imagefilledrectangle($thumb, 0, 0, $thumbW, $thumbH, $transparent);
}
imagecopyresampled($thumb, $src, 0, 0, 0, 0, $thumbW, $thumbH, $imgWidth, $imgHeight);
$thumbName = $filename . '_thumb.jpg';
$thumbPath = $thumbDir . '/' . $thumbName;

imagejpeg($thumb, $thumbPath, 90);
imagedestroy($src);
imagedestroy($thumb);

$thumbUrl = 'uploads/thumbnails/' . $thumbName;
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Upload Successful</title></head><body>';
echo '<h2>Upload Successful</h2>';
echo '<p>Original file: ' . htmlspecialchars($origName) . '</p>';
echo '<p>Thumbnail:</p>';
echo '<img src="' . htmlspecialchars($thumbUrl) . '" alt="Thumbnail">';
echo '<p><a href="index.php">Upload another image</a></p>';
echo '</body></html>';
?>