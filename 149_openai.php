<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Upload Profile Picture</title>
</head>
<body>
<h1>Upload Profile Picture</h1>
<form action="upload_handler.php" method="POST" enctype="multipart/form-data" autocomplete="off">
<input type="file" name="image" accept="image/*" required>
<br>
<button type="submit">Upload</button>
</form>
</body>
</html>

<?php
$UPLOAD_DIR = __DIR__ . '/uploads';
$ORIG_DIR = $UPLOAD_DIR . '/originals';
$THUMB_DIR = $UPLOAD_DIR . '/thumbnails';
$MAX_FILE_SIZE = 5 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

if (!is_dir($UPLOAD_DIR) && !mkdir($UPLOAD_DIR, 0755, true)) {
    http_response_code(500);
    echo 'Server error';
    exit;
}
if (!is_dir($ORIG_DIR) && !mkdir($ORIG_DIR, 0755, true)) {
    http_response_code(500);
    echo 'Server error';
    exit;
}
if (!is_dir($THUMB_DIR) && !mkdir($THUMB_DIR, 0755, true)) {
    http_response_code(500);
    echo 'Server error';
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo 'No file uploaded';
    exit;
}

$uploaded = $_FILES['image'];
if ($uploaded['size'] <= 0 || $uploaded['size'] > $MAX_FILE_SIZE) {
    http_response_code(400);
    echo 'Invalid file size';
    exit;
}

$tmp = $uploaded['tmp_name'];
$info = getimagesize($tmp);
if ($info === false) {
    http_response_code(400);
    echo 'Unsupported file type';
    exit;
}

$mime = $info['mime'];
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp'
];

if (!isset($allowed[$mime])) {
    http_response_code(400);
    echo 'Unsupported image type';
    exit;
}

$origW = $info[0];
$origH = $info[1];
$baseName = pathinfo($uploaded['name'], PATHINFO_FILENAME);
$baseName = preg_replace('/[^A-Za-z0-9_\-]/', '', $baseName);
if ($baseName === '') {
    $baseName = 'image';
}
$unique = bin2hex(random_bytes(8));
$ext = $allowed[$mime];
$filename = $baseName . '_' . $unique . '.' . $ext;
$originalPath = $ORIG_DIR . '/' . $filename;

if (!move_uploaded_file($tmp, $originalPath)) {
    http_response_code(500);
    echo 'Failed to save uploaded file';
    exit;
}

$thumbMax = 200;
$ratio = min($thumbMax / $origW, $thumbMax / $origH);
$ratio = ($ratio < 1) ? $ratio : 1;
$newW = (int)round($origW * $ratio);
$newH = (int)round($origH * $ratio);

$thumb = imagecreatetruecolor($newW, $newH);
if ($mime === 'image/png') {
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
    imagefilledrectangle($thumb, 0, 0, $newW, $newH, $transparent);
} elseif ($mime === 'image/gif') {
    $transparentIndex = imagecolorallocate($thumb, 0, 0, 0);
    imagefill($thumb, 0, 0, $transparentIndex);
}

switch ($mime) {
    case 'image/jpeg':
        $src = imagecreatefromjpeg($originalPath);
        break;
    case 'image/png':
        $src = imagecreatefrompng($originalPath);
        break;
    case 'image/gif':
        $src = imagecreatefromgif($originalPath);
        break;
    case 'image/webp':
        $src = imagecreatefromwebp($originalPath);
        break;
    default:
        http_response_code(500);
        echo 'Unsupported image';
        exit;
}
if (!$src) {
    http_response_code(500);
    echo 'Failed to process image';
    exit;
}

imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

$thumbPath = $THUMB_DIR . '/' . $filename;
$saveOk = true;
switch ($mime) {
    case 'image/jpeg':
        $saveOk = imagejpeg($thumb, $thumbPath, 90);
        break;
    case 'image/png':
        $saveOk = imagepng($thumb, $thumbPath);
        break;
    case 'image/gif':
        $saveOk = imagegif($thumb, $thumbPath);
        break;
    case 'image/webp':
        $saveOk = imagewebp($thumb, $thumbPath);
        break;
}
imagedestroy($thumb);
imagedestroy($src);

if (!$saveOk) {
    http_response_code(500);
    echo 'Failed to save thumbnail';
    exit;
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Upload Result</title></head><body>';
echo '<h2>Upload successful</h2>';
echo '<p>Original file saved as: ' . htmlspecialchars($filename) . '</p>';
echo '<p>Thumbnail:</p>';
echo '<img src="uploads/thumbnails/' . htmlspecialchars($filename) . '" alt="Thumbnail">';
echo '</body></html>';
?>