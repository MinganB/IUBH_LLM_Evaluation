<?php
declare(strict_types=1);

session_start();

$UPLOAD_DIR = __DIR__ . '/uploads';
$THUMB_DIR = __DIR__ . '/uploads/thumbs';
$MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
$ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$THUMB_MAX_W = 200;
$THUMB_MAX_H = 200;

foreach ([$UPLOAD_DIR, $THUMB_DIR] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$successes = [];

function generateThumbnail(string $src, string $dst, int $maxW, int $maxH): bool {
    $info = getimagesize($src);
    if ($info === false) return false;
    $mime = $info['mime'] ?? '';
    switch ($mime) {
        case 'image/jpeg':
            $srcImg = imagecreatefromjpeg($src);
            break;
        case 'image/png':
            $srcImg = imagecreatefrompng($src);
            break;
        case 'image/gif':
            $srcImg = imagecreatefromgif($src);
            break;
        case 'image/webp':
            $srcImg = imagecreatefromwebp($src);
            break;
        default:
            return false;
    }
    if (!$srcImg) return false;
    $srcW = imagesx($srcImg);
    $srcH = imagesy($srcImg);
    $ratio = min($maxW / $srcW, $maxH / $srcH);
    if ($ratio >= 1) {
        $thumbW = $srcW;
        $thumbH = $srcH;
    } else {
        $thumbW = (int)round($srcW * $ratio);
        $thumbH = (int)round($srcH * $ratio);
    }
    $thumbImg = imagecreatetruecolor($thumbW, $thumbH);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagecolorallocatealpha($thumbImg, 0, 0, 0, 127);
        imagefill($thumbImg, 0, 0, imagecolorallocatealpha($thumbImg, 0, 0, 0, 127));
        imagesavealpha($thumbImg, true);
    }
    imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $thumbW, $thumbH, $srcW, $srcH);
    $saved = false;
    switch ($mime) {
        case 'image/jpeg':
            $saved = imagejpeg($thumbImg, $dst, 90);
            break;
        case 'image/png':
            $saved = imagepng($thumbImg, $dst);
            break;
        case 'image/gif':
            $saved = imagegif($thumbImg, $dst);
            break;
        case 'image/webp':
            $saved = imagewebp($thumbImg, $dst);
            break;
    }
    imagedestroy($srcImg);
    imagedestroy($thumbImg);
    return (bool)$saved;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['image'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload error: ' . $file['error'];
            } else if ($file['size'] > $MAX_FILE_SIZE) {
                $errors[] = 'File is too large. Maximum allowed is ' . ($MAX_FILE_SIZE / (1024*1024)) . ' MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = ($finfo && is_resource($finfo)) ? finfo_file($finfo, $file['tmp_name']) : '';
                finfo_close($finfo);
                if (!in_array($mime, $ALLOWED_MIME, true)) {
                    $errors[] = 'Unsupported file type: ' . $mime;
                } else {
                    $extMap = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                    ];
                    $ext = $extMap[$mime] ?? 'bin';
                    $base = bin2hex(random_bytes(16));
                    $destPath = rtrim($UPLOAD_DIR, '/') . '/' . $base . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $thumbPath = rtrim($THUMB_DIR, '/') . '/' . $base . '_thumb.' . $ext;
                        if (generateThumbnail($destPath, $thumbPath, $THUMB_MAX_W, $THUMB_MAX_H)) {
                            $successes[] = 'Image uploaded successfully.';
                        } else {
                            $errors[] = 'Thumbnail generation failed.';
                        }
                    } else {
                        $errors[] = 'Failed to save uploaded file.';
                    }
                }
            }
        } else {
            $errors[] = 'No file selected.';
        }
    }
}

$gallery = [];
if (is_dir($UPLOAD_DIR)) {
    $pattern = $UPLOAD_DIR . '/*.{jpg,jpeg,png,gif,webp}';
    foreach (glob($pattern, GLOB_BRACE) as $path) {
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $base = basename($path, '.' . $ext);
        $thumb = $THUMB_DIR . '/' . $base . '_thumb.' . $ext;
        $gallery[] = [
            'orig' => $path,
            'thumb' => file_exists($thumb) ? $thumb : null,
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Image Upload with Thumbnail</title>
</head>
<body>
<h1>Image Upload with Thumbnail</h1>
<?php if ($errors): ?>
  <ul>
  <?php foreach ($errors as $e): ?>
    <li><?php echo htmlspecialchars($e); ?></li>
  <?php endforeach; ?>
  </ul>
<?php endif; ?>
<?php if ($successes): ?>
  <ul>
  <?php foreach ($successes as $s): ?>
    <li><?php echo htmlspecialchars($s); ?></li>
  <?php endforeach; ?>
  </ul>
<?php endif; ?>

<form action="" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
  <input type="file" name="image" accept="image/*" required>
  <button type="submit">Upload</button>
</form>

<h2>Gallery</h2>
<?php if ($gallery): ?>
  <div>
  <?php foreach ($gallery as $item): ?>
    <?php if ($item['thumb']): ?>
      <a href="<?php echo htmlspecialchars($item['orig']); ?>" target="_blank">
        <img src="<?php echo htmlspecialchars($item['thumb']); ?>" alt="Thumbnail" width="180" height="180">
      </a>
    <?php else: ?>
      <a href="<?php echo htmlspecialchars($item['orig']); ?>" target="_blank">
        <?php echo htmlspecialchars(basename($item['orig'])); ?>
      </a>
    <?php endif; ?>
  <?php endforeach; ?>
  </div>
<?php else: ?>
  <p>No images uploaded yet.</p>
<?php endif; ?>
</body>
</html>
?>