<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Image Upload</title>
</head>
<body>
<h1>Upload Image</h1>
<form action="upload_handler.php" method="POST" enctype="multipart/form-data">
<input type="hidden" name="MAX_FILE_SIZE" value="5242880">
<input type="file" name="image" accept="image/*" required>
<button type="submit">Upload</button>
</form>
</body>
</html>

<?php
// upload_handler.php

$errors = [];

$UPLOAD_DIR = __DIR__ . '/uploads/original/';
$THUMB_DIR = __DIR__ . '/uploads/thumbs/';
$MAX_SIZE = 5 * 1024 * 1024;
$ALLOWED_MIME = ['image/jpeg','image/png','image/gif','image/webp'];
$ALLOWED_EXT = ['jpg','jpeg','png','gif','webp'];

if (!isset($_FILES['image'])) {
  $errors[] = 'No file uploaded';
} else {
  $file = $_FILES['image'];
  if ($file['error'] !== UPLOAD_ERR_OK) {
     $errors[] = 'Upload error';
  } else {
     if ($file['size'] > $MAX_SIZE) $errors[] = 'File too large';
     $finfo = finfo_open(FILEINFO_MIME_TYPE);
     $mime = finfo_file($finfo, $file['tmp_name']);
     finfo_close($finfo);
     if (!in_array($mime, $ALLOWED_MIME)) $errors[] = 'Unsupported file type';
     $originalName = basename($file['name']);
     $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
     if (!in_array($ext, $ALLOWED_EXT)) $errors[] = 'Unsupported extension';
     $imginfo = @getimagesize($file['tmp_name']);
     if ($imginfo === false) $errors[] = 'Invalid image';
  }
}

if (!empty($errors)) {
  echo '<h2>Upload Failed</h2><ul>';
  foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>';
  echo '</ul>';
  echo '<a href="index.php">Go back</a>';
  exit;
}

// Ensure directories
if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);
if (!is_dir($THUMB_DIR)) mkdir($THUMB_DIR, 0755, true);

// Save original
$baseName = bin2hex(random_bytes(8));
$filename = $baseName . '.' . $ext;
$targetOriginal = $UPLOAD_DIR . $filename;
if (!move_uploaded_file($file['tmp_name'], $targetOriginal)) {
  echo '<h2>Upload Failed</h2><p>Could not save file.</p>';
  exit;
}

// Create thumbnail
$thumbMaxW = 200;
$thumbMaxH = 200;

switch ($mime) {
  case 'image/jpeg':
    $src = imagecreatefromjpeg($targetOriginal);
    break;
  case 'image/png':
    $src = imagecreatefrompng($targetOriginal);
    break;
  case 'image/gif':
    $src = imagecreatefromgif($targetOriginal);
    break;
  case 'image/webp':
    $src = imagecreatefromwebp($targetOriginal);
    break;
  default:
    $src = null;
}
if ($src === null) {
  echo '<h2>Upload Failed</h2><p>Unsupported image type.</p>';
  exit;
}
$srcW = imagesx($src);
$srcH = imagesy($src);
$ratio = min($thumbMaxW / $srcW, $thumbMaxH / $srcH);
$dstW = (int)round($srcW * $ratio);
$dstH = (int)round($srcH * $ratio);
$thumb = imagecreatetruecolor($dstW, $dstH);

// Preserve transparency
if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
  imagealphablending($thumb, false);
  imagesavealpha($thumb, true);
  $bg = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
  imagefill($thumb, 0, 0, $bg);
} else {
  // For JPEG no transparency handling required
}
imagecopyresampled($thumb, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

$thumbFilename = pathinfo($filename, PATHINFO_FILENAME) . '_thumb.jpg';
$thumbPath = $THUMB_DIR . $thumbFilename;
imagejpeg($thumb, $thumbPath, 90);

imagedestroy($src);
imagedestroy($thumb);

$relativeOriginal = 'uploads/original/' . $filename;
$relativeThumb = 'uploads/thumbs/' . $thumbFilename;

echo '<h2>Upload Successful</h2>';
echo '<p>Original: <a href="' . htmlspecialchars($relativeOriginal) . '" target="_blank">' . htmlspecialchars($filename) . '</a></p>';
echo '<p>Thumbnail: <a href="' . htmlspecialchars($relativeThumb) . '" target="_blank">View Thumbnail</a></p>';
echo '<p><a href="index.php">Upload another image</a></p>';
?>