<?php
session_start();
require __DIR__ . '/vendor/autoload.php';
use Intervention\Image\ImageManagerStatic as Image;

$BASE_UPLOAD_DIR = __DIR__ . '/uploads/avatars';
$LOG_FILE = __DIR__ . '/logs/upload.log';
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('MIN_WIDTH', 128);
define('MIN_HEIGHT', 128);
define('MAX_WIDTH', 4000);
define('MAX_HEIGHT', 4000);
define('THUMBNAIL_SIZE', 150);

$ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$MIME_TO_EXT = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

function ensureDir($path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

function logUploadAttempt($userId, $ip, $filename, $success, $reason = '') {
    global $LOG_FILE;
    $payload = [
        'ts' => date('c'),
        'ip' => $ip,
        'user_id' => $userId,
        'file' => $filename,
        'success' => $success,
        'reason' => $reason
    ];
    $line = json_encode($payload);
    file_put_contents($LOG_FILE, $line . PHP_EOL, FILE_APPEND);
}

function sanitizeFilenameForMime($originalName, $extFromMime) {
    $base = basename($originalName);
    $nameOnly = pathinfo($base, PATHINFO_FILENAME);
    $nameOnly = preg_replace('/\s+/', '_', $nameOnly);
    $nameOnly = preg_replace('/[^A-Za-z0-9_\-]/', '', $nameOnly);
    if ($nameOnly === '') $nameOnly = 'upload';
    $filename = $nameOnly . '_' . uniqid() . '.' . $extFromMime;
    return $filename;
}

ensureDir(__DIR__ . '/uploads');
ensureDir(__DIR__ . '/logs');

$userId = $_SESSION['user_id'] ?? null;
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$userId) {
        $errors[] = 'Please log in to upload a profile picture.';
        logUploadAttempt('guest', $ipAddress, '', false, 'unauthenticated');
    } else {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'No file uploaded or upload error.';
            logUploadAttempt($userId, $ipAddress, '', false, 'no_file');
        } else {
            $file = $_FILES['avatar'];
            $tmpPath = $file['tmp_name'];
            if (!is_uploaded_file($tmpPath)) {
                $errors[] = 'Invalid upload source.';
                logUploadAttempt($userId, $ipAddress, $file['name'], false, 'invalid_source');
            } else {
                try {
                    $info = @getimagesize($tmpPath);
                    if (!$info) {
                        throw new \Exception('Unsupported image type');
                    }
                    $width = $info[0];
                    $height = $info[1];
                    $mime = isset($info['mime']) ? $info['mime'] : '';

                    if (!in_array($mime, $ALLOWED_MIME)) {
                        throw new \Exception('Unsupported image mime type');
                    }

                    if ($width < MIN_WIDTH || $height < MIN_HEIGHT || $width > MAX_WIDTH || $height > MAX_HEIGHT) {
                        throw new \Exception('Image dimensions out of allowed range');
                    }

                    if ($file['size'] > MAX_FILE_SIZE) {
                        throw new \Exception('File size exceeds limit');
                    }

                    $extensionFromMime = $MIME_TO_EXT[$mime] ?? 'jpg';
                    $sanitizedFilename = sanitizeFilenameForMime($file['name'], $extensionFromMime);

                    $userDir = rtrim($BASE_UPLOAD_DIR, '/\\') . '/' . $userId;
                    $thumbDir = $userDir . '/thumbs';
                    ensureDir($userDir);
                    ensureDir($thumbDir);

                    $destPath = $userDir . '/' . $sanitizedFilename;
                    $thumbPath = $thumbDir . '/' . $sanitizedFilename;

                    $image = Image::make($tmpPath);
                    $image->save($destPath);

                    $thumb = $image->clone()->fit(THUMBNAIL_SIZE, THUMBNAIL_SIZE);
                    $thumb->save($thumbPath);

                    $messages[] = 'Profile picture uploaded successfully.';
                    logUploadAttempt($userId, $ipAddress, $sanitizedFilename, true);
                } catch (\Exception $e) {
                    $errors[] = 'Failed to process image.';
                    logUploadAttempt($userId, $ipAddress, $file['name'] ?? '', false, $e->getMessage());
                } catch (\Throwable $t) {
                    $errors[] = 'An error occurred during upload.';
                    logUploadAttempt($userId, $ipAddress, $file['name'] ?? '', false, $t->getMessage());
                }
            }
        }
    }
}

$avatarUrl = '';
$thumbUrl = '';
if ($userId) {
    $userDir = __DIR__ . '/uploads/avatars/' . $userId;
    if (is_dir($userDir)) {
        $files = array_values(array_diff(scandir($userDir), ['.', '..', 'thumbs']));
        if (!empty($files)) {
            $latest = end($files);
            $avatarUrl = '/uploads/avatars/' . urlencode($userId) . '/' . urlencode($latest);
            $thumbUrl = '/uploads/avatars/' . urlencode($userId) . '/thumbs/' . urlencode($latest);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Upload Profile Picture</title></head>
<body>
<h1>Upload Profile Picture</h1>
<?php if ($avatarUrl && $thumbUrl): ?>
<p>Current profile picture:</p>
<a href="<?php echo $avatarUrl; ?>"><img src="<?php echo $thumbUrl; ?>" alt="Profile thumbnail" width="150" height="150"></a>
<?php endif; ?>

<?php foreach ($errors as $e) { echo '<p style="color:red;">' . htmlspecialchars($e) . '</p>'; } ?>
<?php foreach ($messages as $m) { echo '<p style="color:green;">' . htmlspecialchars($m) . '</p>'; } ?>

<?php if (!$userId): ?>
<p>Please log in to upload a profile picture.</p>
<?php else: ?>
<form action="" method="post" enctype="multipart/form-data">
  <label for="avatar">Choose image (JPEG, PNG, GIF, WEBP; max 5MB; min 128x128; max 4000x4000):</label><br>
  <input type="file" name="avatar" id="avatar" accept="image/*"><br><br>
  <button type="submit">Upload</button>
</form>
<?php endif; ?>
</body>
</html>
?>