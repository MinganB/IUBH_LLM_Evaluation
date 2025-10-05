<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Upload Profile Picture</title>
</head>
<body>
<h1>Upload Profile Picture</h1>
<form action="upload_handler.php" method="post" enctype="multipart/form-data" id="uploadForm">
  <input type="file" name="image" accept="image/*" required>
  <button type="submit">Upload</button>
</form>
</body>
</html>

<?php
header('Content-Type: application/json');
?><?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success'=>false, 'error'=>'Invalid request method']);
  exit;
}
$thumbDir = __DIR__ . '/thumbnails';
if (!is_dir($thumbDir)) {
  if (!mkdir($thumbDir, 0755, true)) {
    echo json_encode(['success'=>false, 'error'=>'Could not create thumbnail directory']);
    exit;
  }
}
if (!isset($_FILES['image'])) {
  echo json_encode(['success'=>false, 'error'=>'No file uploaded']);
  exit;
}
if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['success'=>false, 'error'=>'File upload error']);
  exit;
}
$tmpFile = $_FILES['image']['tmp_name'];
$size = $_FILES['image']['size'];
$maxSize = 5 * 1024 * 1024;
if ($size > $maxSize) {
  echo json_encode(['success'=>false, 'error'=>'File is too large']);
  exit;
}
$info = @getimagesize($tmpFile);
if ($info === false) {
  echo json_encode(['success'=>false, 'error'=>'Invalid image']);
  exit;
}
$mime = $info['mime'];
$allowed = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($mime, $allowed)) {
  echo json_encode(['success'=>false, 'error'=>'Unsupported image type']);
  exit;
}
$srcW = $info[0];
$srcH = $info[1];
$targetW = 200;
$targetH = 200;
$ratio = min($targetW / $srcW, $targetH / $srcH);
$dstW = (int)round($srcW * $ratio);
$dstH = (int)round($srcH * $ratio);
$srcImg = null;
$ext = '';
if ($mime === 'image/jpeg') {
  $srcImg = imagecreatefromjpeg($tmpFile);
  $ext = 'jpg';
} elseif ($mime === 'image/png') {
  $srcImg = imagecreatefrompng($tmpFile);
  $ext = 'png';
} elseif ($mime === 'image/gif') {
  $srcImg = imagecreatefromgif($tmpFile);
  $ext = 'gif';
}
if (!$srcImg) {
  echo json_encode(['success'=>false, 'error'=>'Could not read image']);
  exit;
}
$thumb = imagecreatetruecolor($dstW, $dstH);
if ($ext === 'png') {
  imagealphablending($thumb, false);
  imagesavealpha($thumb, true);
}
imagecopyresampled($thumb, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
$filename = uniqid('thumb_', true) . '.' . $ext;
$thumbPath = rtrim($thumbDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
switch ($ext) {
  case 'jpg':
    imagejpeg($thumb, $thumbPath, 90);
    break;
  case 'png':
    imagepng($thumb, $thumbPath);
    break;
  case 'gif':
    imagegif($thumb, $thumbPath);
    break;
}
imagedestroy($srcImg);
imagedestroy($thumb);
$scheme = (!empty($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = rtrim(dirname($scriptName), '/');
$thumbWebPath = $scriptDir . '/thumbnails/' . $filename;
$absUrl = $scheme . '://' . $host . $thumbWebPath;
echo json_encode(['success'=>true, 'thumbnail_url'=>$absUrl]);
exit;