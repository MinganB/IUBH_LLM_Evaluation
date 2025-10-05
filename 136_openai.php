<?php
declare(strict_types=1);

$BASE_DIR = __DIR__ . '/uploads';
$ORIG_DIR = $BASE_DIR . '/originals';
$THUMB_DIR = $BASE_DIR . '/thumbs';
$MAX_SIZE = 5 * 1024 * 1024;
$THUMB_W = 200;
$THUMB_H = 200;

function ensureDirectory(string $dir): void {
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}
ensureDirectory($ORIG_DIR);
ensureDirectory($THUMB_DIR);

function createThumbnail(string $srcPath, string $destPath, int $thumbWidth, int $thumbHeight): bool {
    $info = @getimagesize($srcPath);
    if ($info === false) return false;
    $mime = $info['mime'] ?? '';

    switch ($mime) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            $src = imagecreatefrompng($srcPath);
            break;
        case 'image/gif':
            $src = imagecreatefromgif($srcPath);
            break;
        case 'image/webp':
            $src = imagecreatefromwebp($srcPath);
            break;
        default:
            $src = false;
    }

    if (!$src) return false;

    $srcW = imagesx($src);
    $srcH = imagesy($src);

    $ratioSrc = $srcW / $srcH;
    $ratioThumb = $thumbWidth / $thumbHeight;

    if ($ratioSrc > $ratioThumb) {
        $newW = $thumbWidth;
        $newH = (int) round($thumbWidth / $ratioSrc);
    } else {
        $newH = $thumbHeight;
        $newW = (int) round($thumbHeight * $ratioSrc);
    }

    $dst = imagecreatetruecolor($thumbWidth, $thumbHeight);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    $dstX = (int) floor(($thumbWidth - $newW) / 2);
    $dstY = (int) floor(($thumbHeight - $newH) / 2);

    $ok = imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
    if (!$ok) {
        imagedestroy($src);
        imagedestroy($dst);
        return false;
    }

    $result = false;
    switch ($mime) {
        case 'image/jpeg':
            $result = imagejpeg($dst, $destPath, 90);
            break;
        case 'image/png':
            $result = imagepng($dst, $destPath);
            break;
        case 'image/gif':
            $result = imagegif($dst, $destPath);
            break;
        case 'image/webp':
            $result = imagewebp($dst, $destPath);
            break;
    }

    imagedestroy($src);
    imagedestroy($dst);
    return (bool)$result;
}

function toUrl(string $path): string {
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = rtrim(str_replace('\\','/', $_SERVER['DOCUMENT_ROOT']), '/');
        $p = str_replace('\\','/', $path);
        if (strpos($p, $docRoot) === 0) {
            $rel = substr($p, strlen($docRoot));
            return $rel ? $rel : '/';
        }
    }
    return '/' . ltrim(str_replace('\\','/', $path), '/');
}

$uploadedPath = '';
$thumbnailPath = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'No file uploaded or upload error.';
    } else {
        $file = $_FILES['image'];
        if ($file['size'] > $MAX_SIZE) {
            $error = 'File is too large. Maximum size is 5 MB.';
        } else {
            $tmp = $file['tmp_name'];
            $info = @getimagesize($tmp);
            if ($info === false) {
                $error = 'Uploaded file is not a valid image.';
            } else {
                $mime = $info['mime'] ?? '';
                $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
                if (!in_array($mime, $allowed, true)) {
                    $error = 'Unsupported image type. Allowed: JPG, PNG, GIF, WEBP.';
                } else {
                    $extMap = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                    ];
                    $ext = $extMap[$mime] ?? 'jpg';
                    $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
                    $slug = preg_replace('/[^A-Za-z0-9_-]+/', '_', $baseName);
                    $unique = uniqid('img_', true);
                    $filename = $slug . '_' . $unique . '.' . $ext;
                    $destPath = $ORIG_DIR . '/' . $filename;

                    if (move_uploaded_file($tmp, $destPath)) {
                        $uploadedPath = $destPath;
                        $thumbFilename = pathinfo($filename, PATHINFO_FILENAME) . '_thumb.' . $ext;
                        $thumbPath = $THUMB_DIR . '/' . $thumbFilename;
                        $created = createThumbnail($destPath, $thumbPath, $THUMB_W, $THUMB_H);
                        if ($created) {
                            $thumbnailPath = $thumbPath;
                        } else {
                            @unlink($destPath);
                            $error = 'Failed to create thumbnail.';
                        }
                    } else {
                        $error = 'Failed to save uploaded file.';
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Image Upload and Thumbnail Generator</title>
</head>
<body>
<h1>Image Upload and Thumbnail Generator</h1>

<form method="post" enctype="multipart/form-data" action="">
  <input type="file" name="image" accept="image/*" />
  <button type="submit">Upload</button>
</form>

<?php if ($error): ?>
  <p><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<?php if ($thumbnailPath): ?>
  <h2>Thumbnail</h2>
  <p>Original: <?php echo htmlspecialchars(basename($uploadedPath)); ?></p>
  <img src="<?php echo htmlspecialchars(toUrl($thumbnailPath)); ?>" alt="Thumbnail" />
<?php endif; ?>

</body>
</html>
?>