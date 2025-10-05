<?php
?>
<!DOCTYPE html>
<html>
<head>
<title>Upload Profile Photo</title>
</head>
<body>
<h1>Upload Profile Photo</h1>
<form id="uploadForm" action="upload_handler.php" method="POST" enctype="multipart/form-data">
  <input type="file" name="image" accept="image/*" required>
  <button type="submit">Upload</button>
</form>
<div id="result"></div>
<script>
document.getElementById('uploadForm').addEventListener('submit', async function(e) {
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);
  const resultDiv = document.getElementById('result');
  resultDiv.innerHTML = 'Uploading...';
  try {
    const resp = await fetch(form.action, {
      method: 'POST',
      body: formData
    });
    const data = await resp.json();
    if (data.success) {
      resultDiv.innerHTML = '<p>Thumbnail created:</p><img src="' + data.thumbnail_url + '" alt="Thumbnail">';
    } else {
      resultDiv.innerHTML = '<p>Error: ' + data.error + '</p>';
    }
  } catch (err) {
    resultDiv.innerHTML = '<p>Upload failed.</p>';
  }
});
</script>
</body>
</html>

<?php
?><?php
header('Content-Type: application/json');
$allowedMime = [
  'image/jpeg' => 'jpg',
  'image/png' => 'png',
  'image/gif' => 'gif'
];
$maxFileSize = 5 * 1024 * 1024;
$thumbMaxW = 200;
$thumbMaxH = 200;

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
  exit;
}
$tmpName = $_FILES['image']['tmp_name'];
$origName = $_FILES['image']['name'];
$size = $_FILES['image']['size'];

if ($size > $maxFileSize) {
  echo json_encode(['success' => false, 'error' => 'File is too large.']);
  exit;
}

$imageInfo = getimagesize($tmpName);
if ($imageInfo === false) {
  echo json_encode(['success' => false, 'error' => 'Unsupported image type.']);
  exit;
}
$mime = $imageInfo['mime'];
if (!isset($allowedMime[$mime])) {
  echo json_encode(['success' => false, 'error' => 'Unsupported image type.']);
  exit;
}
$ext = $allowedMime[$mime];

$thumbDir = __DIR__ . '/thumbnails';
if (!is_dir($thumbDir)) {
  if (!mkdir($thumbDir, 0777, true)) {
    echo json_encode(['success' => false, 'error' => 'Failed to create thumbnail directory.']);
    exit;
  }
}

switch ($mime) {
  case 'image/jpeg':
    $src = imagecreatefromjpeg($tmpName);
    break;
  case 'image/png':
    $src = imagecreatefrompng($tmpName);
    break;
  case 'image/gif':
    $src = imagecreatefromgif($tmpName);
    break;
  default:
    echo json_encode(['success' => false, 'error' => 'Unsupported image type.']);
    exit;
}
if (!$src) {
  echo json_encode(['success' => false, 'error' => 'Failed to process image.']);
  exit;
}
$srcW = imagesx($src);
$srcH = imagesy($src);

$ratioW = $thumbMaxW / $srcW;
$ratioH = $thumbMaxH / $srcH;
$ratio = min($ratioW, $ratioH, 1);
$newW = (int)round($srcW * $ratio);
$newH = (int)round($srcH * $ratio);

$dst = imagecreatetruecolor($newW, $newH);
if (!$dst) {
  imagedestroy($src);
  echo json_encode(['success' => false, 'error' => 'Failed to create thumbnail canvas.']);
  exit;
}

if ($mime === 'image/png' || $mime === 'image/gif') {
  imagealphablending($dst, false);
  imagesavealpha($dst, true);
  $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
  imagefill($dst, 0, 0, $transparent);
  imagealphablending($dst, true);
}

imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

$uniq = bin2hex(random_bytes(8));
$filename = 'thumb_' . $uniq . '.' . $ext;
$thumbPath = $thumbDir . '/' . $filename;

$saveSuccess = true;
switch ($mime) {
  case 'image/jpeg':
    $saveSuccess = imagejpeg($dst, $thumbPath, 90);
    break;
  case 'image/png':
    $saveSuccess = imagepng($dst, $thumbPath);
    break;
  case 'image/gif':
    $saveSuccess = imagegif($dst, $thumbPath);
    break;
}
imagedestroy($src);
imagedestroy($dst);

if (!$saveSuccess) {
  echo json_encode(['success' => false, 'error' => 'Failed to save thumbnail.']);
  exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$thumbUrlBase = $protocol . '://' . $host . '/thumbnails';
$thumbnailUrl = $thumbUrlBase . '/' . $filename;

echo json_encode(['success' => true, 'thumbnail_url' => $thumbnailUrl]);
exit;
?>