<?php
session_start();
$status = $_SESSION['upload_status'] ?? null;
unset($_SESSION['upload_status']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Upload Profile Picture</title>
</head>
<body>
<?php if ($status): ?>
<p><?php echo htmlspecialchars($status); ?></p>
<?php endif; ?>
<form action="/handlers/upload_handler.php" method="post" enctype="multipart/form-data">
  <label for="image">Upload profile picture</label>
  <input type="file" name="image" id="image" accept="image/*" required />
  <button type="submit">Upload</button>
</form>
</body>
</html>
<?php
?>

<?php
namespace Classes;

class Logger {
    public static function log($message) {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $logFile = $logDir . '/upload.log';
        $entry = date('Y-m-d H:i:s') . " " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " " . $message . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
?> 
<?php
namespace Classes;

use Intervention\Image\ImageManager;

class ImageProcessor {
    private $manager;
    public function __construct($driver = 'gd') {
        $this->manager = new ImageManager(['driver' => $driver]);
    }
    public function createThumbnail($inputPath, $outputPath, $width, $height) {
        try {
            $this->manager->make($inputPath)->fit($width, $height)->save($outputPath);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
?> 
<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
use Classes\ImageProcessor;
use Classes\Logger;

const MAX_FILE_SIZE = 5 * 1024 * 1024;
const ALLOWED_MIME = ['image/jpeg','image/png','image/gif','image/webp'];
const MAX_WIDTH = 5000;
const MAX_HEIGHT = 5000;
const THUMB_WIDTH = 150;
const THUMB_HEIGHT = 150;

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$uploaded = $_FILES['image'] ?? null;

if (!$uploaded || $uploaded['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['upload_status'] = 'Upload failed';
    Logger::log('UploadError');
    header('Location: /public/index.php');
    exit;
}

$tmpName = $uploaded['tmp_name'];
$origName = $uploaded['name'] ?? 'image';
$size = $uploaded['size'] ?? 0;

if ($size > MAX_FILE_SIZE) {
    $_SESSION['upload_status'] = 'Upload failed: size limit exceeded';
    Logger::log('SizeExceeded');
    header('Location: /public/index.php');
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $tmpName);
finfo_close($finfo);
if (!in_array($mime, ALLOWED_MIME)) {
    $_SESSION['upload_status'] = 'Upload failed: invalid file type';
    Logger::log('InvalidMime');
    header('Location: /public/index.php');
    exit;
}

$dimensions = getimagesize($tmpName);
if ($dimensions === false) {
    $_SESSION['upload_status'] = 'Upload failed: invalid image';
    Logger::log('InvalidImage');
    header('Location: /public/index.php');
    exit;
}
list($w, $h) = $dimensions;
if ($w > MAX_WIDTH || $h > MAX_HEIGHT) {
    $_SESSION['upload_status'] = 'Upload failed: image dimensions too large';
    Logger::log('DimensionsExceeded');
    header('Location: /public/index.php');
    exit;
}

$baseName = pathinfo($origName, PATHINFO_FILENAME);
$ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$sanitizedBase = preg_replace('/[^A-Za-z0-9_-]/', '', $baseName);
if ($sanitizedBase === '') $sanitizedBase = 'image';
$filename = $sanitizedBase . '_' . uniqid() . '.' . $ext;

$origDir = __DIR__ . '/../storage/originals';
$thumbDir = __DIR__ . '/../storage/thumbnails';
if (!is_dir($origDir)) mkdir($origDir, 0755, true);
if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);

$destPath = $origDir . '/' . $filename;
if (!move_uploaded_file($tmpName, $destPath)) {
    $_SESSION['upload_status'] = 'Upload failed: could not save file';
    Logger::log('MoveFailed');
    header('Location: /public/index.php');
    exit;
}

$thumbPath = $thumbDir . '/' . $filename;
$imageProcessor = new ImageProcessor();
$thumbResult = $imageProcessor->createThumbnail($destPath, $thumbPath, THUMB_WIDTH, THUMB_HEIGHT);
if (!$thumbResult) {
    $_SESSION['upload_status'] = 'Upload failed: could not create thumbnail';
    Logger::log('ThumbnailFailed');
    header('Location: /public/index.php');
    exit;
}

$_SESSION['upload_status'] = 'Upload successful';
Logger::log('UploadSuccess');
header('Location: /public/index.php');
exit;
?>