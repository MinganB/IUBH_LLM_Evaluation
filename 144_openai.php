<?php
class Logger {
    private $logFile;
    public function __construct($logPath = null) {
        $this->logFile = $logPath ?? dirname(__DIR__) . '/logs/upload.log';
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        if (!is_writable($logDir)) {
            @chmod($logDir, 0755);
        }
    }
    private function write($level, $message) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $entry = "[$timestamp] [$level] [$ip] $message" . PHP_EOL;
        file_put_contents($this->logFile, $entry, FILE_APPEND);
    }
    public function info($message) { $this->write('INFO', $message); }
    public function error($message) { $this->write('ERROR', $message); }
}
?><?php
class FileValidator {
    private $allowedMime;
    private $maxSize;
    private $maxWidth;
    private $maxHeight;
    public function __construct(array $allowedMime, int $maxSize, int $maxWidth, int $maxHeight) {
        $this->allowedMime = $allowedMime;
        $this->maxSize = $maxSize;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
    }
    public function isValid(array $file): bool {
        if ($file['error'] !== UPLOAD_ERR_OK) return false;
        if ($file['size'] <= 0 || $file['size'] > $this->maxSize) return false;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) return false;
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $this->allowedMime, true)) return false;
        $dimensions = @getimagesize($file['tmp_name']);
        if ($dimensions === false) return false;
        $width = $dimensions[0];
        $height = $dimensions[1];
        if ($width <= 0 || $height <= 0) return false;
        if ($width > $this->maxWidth || $height > $this->maxHeight) return false;
        return true;
    }
}
?><?php
use Intervention\Image\ImageManager;
class ImageService {
    private $manager;
    public function __construct() {
        $this->manager = new ImageManager(['driver' => 'gd']);
    }
    public function createThumbnail(string $source, string $destination, int $width, int $height): void {
        $img = $this->manager->make($source)->fit($width, $height, function ($constraint) {
            $constraint->upsize();
        });
        $img->save($destination);
    }
}
?><?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/FileValidator.php';
require_once __DIR__ . '/../classes/ImageService.php';

$log = new Logger();

function sanitizeFilename(string $filename): string {
    $basename = basename($filename);
    $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
    $name = pathinfo($basename, PATHINFO_FILENAME);
    $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $name);
    if ($name === '' || $name === '_') $name = 'image';
    $allowedExt = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowedExt, true)) {
        $ext = 'jpg';
    }
    $unique = bin2hex(random_bytes(6));
    return $name . '_' . $unique . '.' . $ext;
}

$UPLOAD_ROOT = dirname(__DIR__) . '/public/uploads/';
$ORIG_DIR = $UPLOAD_ROOT . 'originals';
$THUM_DIR = $UPLOAD_ROOT . 'thumbnails';
$MAX_SIZE = 5 * 1024 * 1024;
$MAX_WIDTH = 4000;
$MAX_HEIGHT = 4000;
$ALLOWED = ['image/jpeg','image/png','image/gif','image/webp'];

$response = ['success'=>false, 'error'=>'Invalid request.'];
$ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method.');
    }
    if (!isset($_FILES['image'])) {
        throw new Exception('No file uploaded.');
    }
    $file = $_FILES['image'];
    $log->info("Upload attempt from $ip for file ".$file['name']);
    $validator = new FileValidator($ALLOWED, $MAX_SIZE, $MAX_WIDTH, $MAX_HEIGHT);
    if (!$validator->isValid($file)) {
        throw new Exception('Uploaded file did not pass validation.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('Invalid uploaded file.');
    }
    if (!is_dir($ORIG_DIR)) mkdir($ORIG_DIR, 0755, true);
    if (!is_dir($THUM_DIR)) mkdir($THUM_DIR, 0755, true);

    $safeName = sanitizeFilename($file['name']);
    $destPath = $ORIG_DIR . '/' . $safeName;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Failed to save uploaded file.');
    }

    $imageService = new ImageService();
    $thumbPath = $THUM_DIR . '/' . $safeName;
    $imageService->createThumbnail($destPath, $thumbPath, 150, 150);

    $response = [
        'success' => true,
        'filename' => $safeName,
        'thumbnail' => '/public/uploads/thumbnails/' . $safeName
    ];
    $log->info("Upload success from $ip: $safeName");
} catch (Exception $e) {
    $log->error("Upload failed from $ip: " . $e->getMessage());
    $response['error'] = 'Upload failed. Please try again later.';
}
echo json_encode($response);
?><?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>Upload Profile Picture</title>
</head>
<body>
<h1>Upload Profile Picture</h1>
<form id="uploadForm" action="/handlers/upload.php" method="post" enctype="multipart/form-data">
  <label>Select image (jpeg, png, gif, webp; max 5MB)</label><br/>
  <input type="file" name="image" accept="image/*" required /><br/><br/>
  <button type="submit">Upload</button>
</form>
<div id="result" style="margin-top:20px;"></div>
<script>
document.getElementById('uploadForm').addEventListener('submit', async function(e){
  e.preventDefault();
  const formData = new FormData(this);
  const res = await fetch('/handlers/upload.php', {method: 'POST', body: formData});
  const data = await res.json();
  const resultDiv = document.getElementById('result');
  resultDiv.innerHTML = '';
  if (data.success) {
    const p = document.createElement('p');
    p.textContent = 'Upload successful: ' + data.filename;
    resultDiv.appendChild(p);
    if (data.thumbnail) {
      const img = document.createElement('img');
      img.src = data.thumbnail;
      img.alt = data.filename;
      img.style = 'max-width:150px; max-height:150px;';
      resultDiv.appendChild(img);
    }
  } else {
    const p = document.createElement('p');
    p.textContent = 'Error: ' + (data.error || 'Upload failed.');
    resultDiv.appendChild(p);
  }
});
</script>
</body>
</html>
?>