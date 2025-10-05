<?php
session_start();

$projectRoot = dirname(__DIR__);
$origDir = $projectRoot.'/public/uploads/original';
$thumbDir = $projectRoot.'/public/uploads/thumbs';
$thumbSize = 200;
$maxUploadSize = 5 * 1024 * 1024;
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

class ThumbnailGenerator {
    public static function generate($srcPath, $destPath, $size) {
        $info = getimagesize($srcPath);
        if ($info === false) return false;
        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $srcImg = imagecreatefromjpeg($srcPath);
                $ext = 'jpg';
                break;
            case 'image/png':
                $srcImg = imagecreatefrompng($srcPath);
                $ext = 'png';
                break;
            case 'image/gif':
                $srcImg = imagecreatefromgif($srcPath);
                $ext = 'gif';
                break;
            default:
                return false;
        }
        if (!$srcImg) return false;
        $srcW = $info[0];
        $srcH = $info[1];
        $thumb = imagecreatetruecolor($size, $size);
        if ($mime === 'image/png' || $mime === 'image/gif') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefill($thumb, 0, 0, $transparent);
        }
        $scale = max($size / $srcW, $size / $srcH);
        $dstW = (int)round($srcW * $scale);
        $dstH = (int)round($srcH * $scale);
        $dx = (int)(($size - $dstW) / 2);
        $dy = (int)(($size - $dstH) / 2);
        imagecopyresampled($thumb, $srcImg, $dx, $dy, 0, 0, $dstW, $dstH, $srcW, $srcH);
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                imagejpeg($thumb, $destPath, 90);
                break;
            case 'image/png':
                imagepng($thumb, $destPath);
                break;
            case 'image/gif':
                imagegif($thumb, $destPath);
                break;
        }
        imagedestroy($srcImg);
        imagedestroy($thumb);
        return true;
    }
}

class MediaManager {
    private $origDir;
    private $thumbDir;
    public function __construct($origDir, $thumbDir) {
        $this->origDir = rtrim($origDir, '/');
        $this->thumbDir = rtrim($thumbDir, '/');
    }
    public function ensureDirs() {
        if (!is_dir($this->origDir)) { mkdir($this->origDir, 0755, true); }
        if (!is_dir($this->thumbDir)) { mkdir($this->thumbDir, 0755, true); }
    }
    public function saveOriginal($tmpPath, $filename) {
        $dest = $this->origDir.'/'.$filename;
        if (!move_uploaded_file($tmpPath, $dest)) {
            return false;
        }
        return $dest;
    }
    public function saveThumbnailFromOriginal($srcPath, $filename) {
        $dest = $this->thumbDir.'/'.$filename;
        $ok = ThumbnailGenerator::generate($srcPath, $dest, $GLOBALS['thumbSize']);
        if (!$ok) return false;
        return $dest;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['csrf_token'];
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Upload Profile Picture</title>
    </head>
    <body>
        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] === 'success'): ?>
                <p>Upload successful. Thumbnail created.</p>
            <?php else: ?>
                <p>Error: <?php echo htmlspecialchars($_GET['message'] ?? 'Unknown error'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <form action="upload_handler.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <label for="image">Select image:</label>
            <input type="file" name="profile_image" id="image" accept="image/*" required>
            <button type="submit">Upload</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $responseStatus = 'error';
    $responseMessage = 'Unknown error';
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $responseMessage = 'Invalid CSRF token';
        header('Location: /public/index.php?status=error&message=' . urlencode($responseMessage));
        exit;
    }
    if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        $responseMessage = 'No file uploaded or upload error';
        header('Location: /public/index.php?status=error&message=' . urlencode($responseMessage));
        exit;
    }
    $file = $_FILES['profile_image'];
    if ($file['size'] > $maxUploadSize) {
        $responseMessage = 'File size exceeds limit';
        header('Location: /public/index.php?status=error&message=' . urlencode($responseMessage));
        exit;
    }
    $tmpPath = $file['tmp_name'];
    $info = getimagesize($tmpPath);
    if ($info === false) {
        $responseMessage = 'Invalid image file';
        header('Location: /public/index.php?status=error&message=' . urlencode($responseMessage));
        exit;
    }
    $mime = $info['mime'];
    if (!in_array($mime, $allowedMimes, true)) {
        $responseMessage = 'Unsupported image type';
        header('Location: /public/index.php?status=error&message=' . urlencode($responseMessage));
        exit;
    }
    $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
    $ext = $extMap[$mime] ?? 'bin';
    $uniqueName = bin2hex(random_bytes(8)).'.'.$ext;
    $storage = new MediaManager($origDir, $thumbDir);
    $storage->ensureDirs();
    $origSaved = $storage->saveOriginal($tmpPath, $uniqueName);
    if ($origSaved === false) {
        $responseMessage = 'Failed to save original image';
        header('Location: /public/index.php?status=error&message=' . urlencode($responseMessage));
        exit;
    }
    $thumbSaved = $storage->saveThumbnailFromOriginal($origSaved, $uniqueName);
    if ($thumbSaved === false) {
        $responseMessage = 'Failed to create thumbnail';
        header('Location: /public/index.php?status=error&message=' . urlencode($responseMessage));
        exit;
    }
    $responseStatus = 'success';
    header('Location: /public/index.php?status=success&filename=' . urlencode($uniqueName));
    exit;
}
?>