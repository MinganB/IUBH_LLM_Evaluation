<?php
session_start();
$status = '';
if (isset($_SESSION['upload_status'])) {
    $status = $_SESSION['upload_status'];
    unset($_SESSION['upload_status']);
}
?>
<!DOCTYPE html>
<html>
<head><title>Upload Profile Picture</title></head>
<body>
<?php
if ($status) {
    echo '<p>' . htmlspecialchars($status) . '</p>';
}
?>
<form action="upload_handler.php" method="POST" enctype="multipart/form-data">
<input type="file" name="image" accept="image/*" required />
<button type="submit">Upload</button>
</form>
</body>
</html>

<?php
require __DIR__ . '/vendor/autoload.php';
use Intervention\Image\ImageManagerStatic as Image;

define('UPLOAD_DIR', __DIR__ . '/uploads');
define('THUMB_DIR', UPLOAD_DIR . '/thumbnails');
define('LOG_DIR', __DIR__ . '/logs');
define('LOG_FILE', LOG_DIR . '/upload.log');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('MIN_WIDTH', 100);
define('MIN_HEIGHT', 100);
define('MAX_WIDTH', 5000);
define('MAX_HEIGHT', 5000);
define('THUMB_WIDTH', 200);
define('THUMB_HEIGHT', 200);

$ALLOWED_MIME = ['image/jpeg','image/png','image/gif','image/webp','image/bmp'];

function logEvent($level, $message) {
    if (!is_dir(LOG_DIR)) {
        mkdir(LOG_DIR, 0755, true);
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] [$level] IP:$ip - $message\n";
    file_put_contents(LOG_FILE, $line, FILE_APPEND);
}

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['upload_status'] = 'Invalid request';
    header('Location: index.php');
    exit;
}

if (!isset($_FILES['image'])) {
    $_SESSION['upload_status'] = 'No file selected';
    header('Location: index.php');
    exit;
}

try {
    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        logEvent('ERROR','Upload error: code '.$file['error']);
        $_SESSION['upload_status'] = 'Upload failed';
        header('Location: index.php');
        exit;
    }
    if ($file['size'] > MAX_FILE_SIZE) {
        logEvent('WARNING','File too large: '.$file['size']);
        $_SESSION['upload_status'] = 'File too large';
        header('Location: index.php');
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $ALLOWED_MIME)) {
        logEvent('WARNING','Unsupported mime: '.$mime);
        $_SESSION['upload_status'] = 'Unsupported file type';
        header('Location: index.php');
        exit;
    }

    $originalName = $file['name'];
    $sanitized = preg_replace('/[^A-Za-z0-9_\-\.]/','_', $originalName);
    $extension = strtolower(pathinfo($sanitized, PATHINFO_EXTENSION));
    if ($extension === 'jpeg') { $extension = 'jpg'; }
    $allowedExts = ['jpg','png','gif','webp','bmp'];
    if (!in_array($extension, $allowedExts)) {
        $extension = 'jpg';
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    if (!is_dir(THUMB_DIR)) {
        mkdir(THUMB_DIR, 0755, true);
    }

    $uniqueName = uniqid('pp_', true) . '.' . $extension;
    $destPath = UPLOAD_DIR . '/' . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        logEvent('ERROR','Failed to move uploaded file');
        $_SESSION['upload_status'] = 'Upload failed';
        header('Location: index.php');
        exit;
    }

    if (!class_exists('Intervention\\Image\\ImageManagerStatic')) {
        logEvent('ERROR','Image processing library not available');
        $_SESSION['upload_status'] = 'Internal server error';
        header('Location: index.php');
        exit;
    }

    $image = Image::make($destPath);
    $width = $image->width();
    $height = $image->height();

    if ($width < MIN_WIDTH || $height < MIN_HEIGHT || $width > MAX_WIDTH || $height > MAX_HEIGHT) {
        logEvent('WARNING','Invalid image dimensions: '.$width.'x'.$height);
        $_SESSION['upload_status'] = 'Invalid image dimensions';
        header('Location: index.php');
        exit;
    }

    $thumbName = pathinfo($uniqueName, PATHINFO_FILENAME) . '_thumb.' . $extension;
    $thumbPath = THUMB_DIR . '/' . $thumbName;

    $thumb = $image->fit(THUMB_WIDTH, THUMB_HEIGHT);
    $thumb->save($thumbPath);

    $_SESSION['upload_status'] = 'Upload successful';
    logEvent('INFO','Uploaded '.$destPath.'; thumbnail '.$thumbPath.'; ip: '.$_SERVER['REMOTE_ADDR']);
    header('Location: index.php');
    exit;

} catch (\Throwable $e) {
    logEvent('ERROR','Exception: '.$e->getMessage());
    $_SESSION['upload_status'] = 'Upload failed';
    header('Location: index.php');
    exit;
}
?>