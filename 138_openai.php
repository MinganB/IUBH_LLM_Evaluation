<?php
session_start();

$vendorAutoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorAutoload)) {
    http_response_code(500);
    echo "Composer autoloader missing.";
    exit;
}
require $vendorAutoload;

use Intervention\Image\ImageManagerStatic as Image;

$UPLOAD_ROOT = __DIR__ . '/uploads';
$FULL_DIR = $UPLOAD_ROOT . '/full';
$THUMB_DIR = $UPLOAD_ROOT . '/thumbs';
$LOG_FILE = __DIR__ . '/logs/upload.log';

$MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
$MIN_WIDTH = 100;
$MIN_HEIGHT = 100;
$MAX_WIDTH = 4000;
$MAX_HEIGHT = 4000;
$THUMB_WIDTH = 200;
$THUMB_HEIGHT = 200;

$ALLOWED_MIME = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif'
];

$EXT_MAP = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

function logUpload($status, $filename, $ip, $message = '') {
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] IP=$ip STATUS=$status FILENAME=\"$filename\"";
    if ($message !== '') {
        $line .= " MESSAGE=\"$message\"";
    }
    $line .= PHP_EOL;

    $logDir = dirname(__FILE__) . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0770, true);
    }
    @file_put_contents(__DIR__ . '/logs/upload.log', $line, FILE_APPEND | LOCK_EX);
}

function sanitizeFilename($name) {
    $base = basename($name);
    $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
    $sanitized = ltrim($sanitized, '.');
    if ($sanitized === '') {
        $sanitized = 'file';
    }
    return $sanitized;
}

function ensureDirs($fullDir, $thumbDir) {
    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0755, true);
    }
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }
}

function getMimeTypeFromFile($tmpName) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) return null;
    $mime = finfo_file($finfo, $tmpName);
    finfo_close($finfo);
    return $mime;
}

function processUpload($file) {
    global $ALLOWED_MIME, $EXT_MAP, $MIN_WIDTH, $MIN_HEIGHT, $MAX_WIDTH, $MAX_HEIGHT;
    global $THUMB_WIDTH, $THUMB_HEIGHT;
    global $FULL_DIR, $THUMB_DIR;

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        logUpload('FAIL', 'unknown', $ip, 'Upload error or missing file');
        return ['success' => false, 'filename' => '', 'thumbnail' => ''];
    }

    if ($file['size'] > (5 * 1024 * 1024)) {
        logUpload('FAIL', $file['name'], $ip, 'File size exceeds limit');
        return ['success' => false, 'filename' => '', 'thumbnail' => ''];
    }

    $tmpName = $file['tmp_name'];
    $mime = getMimeTypeFromFile($tmpName);
    if (!$mime || !in_array($mime, $ALLOWED_MIME)) {
        logUpload('FAIL', $file['name'], $ip, 'Unsupported MIME type');
        return ['success' => false, 'filename' => '', 'thumbnail' => ''];
    }

    try {
        $img = Image::make($tmpName);
        $width = $img->width();
        $height = $img->height();
    } catch (Exception $e) {
        logUpload('FAIL', $file['name'], $ip, 'Invalid image file');
        return ['success' => false, 'filename' => '', 'thumbnail' => ''];
    }

    if ($width < $MIN_WIDTH || $height < $MIN_HEIGHT || $width > $MAX_WIDTH || $height > $MAX_HEIGHT) {
        logUpload('FAIL', $file['name'], $ip, 'Image dimensions out of allowed range');
        return ['success' => false, 'filename' => '', 'thumbnail' => ''];
    }

    $sanitized = sanitizeFilename($file['name']);
    $ext = $EXT_MAP[$mime] ?? 'jpg';
    $baseName = pathinfo($sanitized, PATHINFO_FILENAME);
    $destName = $baseName . '.' . $ext;

    ensureDirs($FULL_DIR, $THUMB_DIR);

    $destFullPath = $FULL_DIR . '/' . $destName;
    if (file_exists($destFullPath)) {
        $destName = $baseName . '_' . uniqid() . '.' . $ext;
        $destFullPath = $FULL_DIR . '/' . $destName;
    }

    if (!move_uploaded_file($tmpName, $destFullPath)) {
        logUpload('FAIL', $file['name'], $ip, 'Failed to move uploaded file');
        return ['success' => false, 'filename' => '', 'thumbnail' => ''];
    }

    $thumbFileName = 'thumb_' . $destName;
    $thumbFullPath = $THUMB_DIR . '/' . $thumbFileName;

    try {
        $imgThumb = Image::make($destFullPath)->fit($THUMB_WIDTH, $THUMB_HEIGHT);
        $imgThumb->save($thumbFullPath, 90);
    } catch (Exception $e) {
        logUpload('FAIL', $destName, $ip, 'Thumbnail generation failed');
        // Cleanup uploaded file since thumbnail failed
        @unlink($destFullPath);
        return ['success' => false, 'filename' => '', 'thumbnail' => ''];
    }

    logUpload('SUCCESS', $destName, $ip, 'Uploaded and thumbnail created');
    return ['success' => true, 'filename' => $destName, 'thumbnail' => $thumbFileName];
}

// CSRF token management
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$messages = [];
$uploaded = null;
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $messages[] = 'Submission could not be processed.';
        logUpload('FAIL', 'N/A', $ipAddress, 'CSRF validation failed');
    } else {
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $result = processUpload($_FILES['image']);
            if ($result['success']) {
                $uploaded = $result;
                $messages[] = 'Upload completed successfully.';
            } else {
                $messages[] = 'Upload failed. Please try again.';
            }
        } else {
            $messages[] = 'No file selected for upload.';
            logUpload('FAIL', 'N/A', $ipAddress, 'No file selected');
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Image Upload and Thumbnail</title>
</head>
<body>
    <?php if (!empty($messages)): ?>
        <ul>
            <?php foreach ($messages as $m): ?>
                <li><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($uploaded && $uploaded['success']): ?>
        <h3>Thumbnail</h3>
        <img src="uploads/thumbs/<?= htmlspecialchars($uploaded['thumbnail'], ENT_QUOTES, 'UTF-8') ?>" alt="Thumbnail" />
        <p>Original: <a href="uploads/full/<?= htmlspecialchars($uploaded['filename'], ENT_QUOTES, 'UTF-8') ?>" target="_blank">View original</a></p>
    <?php endif; ?>

    <h2>Upload Image</h2>
    <form method="post" enctype="multipart/form-data" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="file" name="image" accept="image/*" required>
        <button type="submit">Upload</button>
    </form>
    <p>Max file size: 5 MB. Supported types: JPEG, PNG, WEBP, GIF. Images will be resized to a 200x200 thumbnail.</p>
</body>
</html>
?>