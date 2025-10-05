<?php
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Upload Profile Picture</title>
</head>
<body>
<h2>Upload Profile Picture</h2>
<form id="uploadForm" action="/handlers/upload_handler.php" method="post" enctype="multipart/form-data">
  <input type="file" name="image" accept="image/*" required />
  <button type="submit">Upload</button>
</form>
<div id="output"></div>
<script>
document.getElementById('uploadForm').addEventListener('submit', function(e){
  e.preventDefault();
  var form = this;
  var data = new FormData(form);
  fetch(form.action, {method: 'POST', body: data})
    .then(function(res){ return res.json(); })
    .then(function(json){
      var out = document.getElementById('output');
      if (json && json.success) {
        out.innerHTML = 'Thumbnail URL: <a href="'+json.thumbnail_url+'" target="_blank">'+json.thumbnail_url+'</a><br><img src="'+json.thumbnail_url+'" alt="Thumbnail" style="max-width:200px; max-height:200px;">';
      } else {
        out.innerHTML = 'Error: ' + (json && json.error ? json.error : 'Upload failed');
      }
    })
    .catch(function(){ document.getElementById('output').innerText = 'An error occurred during upload'; });
});
</script>
</body>
</html>
<?php
?><?php
require_once __DIR__ . '/../classes/ImageProcessor.php';
$processor = new ImageProcessor();
header('Content-Type: application/json');
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['success'=>false, 'error'=>'No file uploaded or upload error']);
  exit;
}
$tmpPath = $_FILES['image']['tmp_name'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $tmpPath);
finfo_close($finfo);
$allowed = ['image/jpeg','image/png','image/gif'];
if (!$processor->isSupportedMime($mime)) {
  echo json_encode(['success'=>false, 'error'=>'Unsupported file type']);
  exit;
}
$extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
$extension = $extMap[$mime];
$basePublic = __DIR__ . '/../public';
$origDir = $basePublic . '/uploads/originals';
$thumbDir = $basePublic . '/thumbnails';
if (!is_dir($origDir)) mkdir($origDir, 0755, true);
if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
$originalFilename = uniqid('orig_', true) . '.' . $extension;
$originalPath = $origDir . '/' . $originalFilename;
if (!move_uploaded_file($tmpPath, $originalPath)) {
  echo json_encode(['success'=>false, 'error'=>'Failed to save uploaded file']);
  exit;
}
$thumbFilename = uniqid('thumb_', true) . '.' . $extension;
$thumbPath = $thumbDir . '/' . $thumbFilename;
$thumbRes = $processor->createThumbnail($originalPath, $thumbPath, 200, 200);
if (!$thumbRes['success']) {
  echo json_encode(['success'=>false, 'error'=>$thumbRes['error']]);
  exit;
}
$thumbRelPath = '/thumbnails/' . $thumbFilename;
$host = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host .= $_SERVER['HTTP_HOST'];
$thumbnailUrl = $host . $thumbRelPath;
echo json_encode(['success'=>true, 'thumbnail_url'=>$thumbnailUrl]);
exit;
?><?php
class ImageProcessor {
  private $maxWidth;
  private $maxHeight;
  public function __construct($maxWidth = 200, $maxHeight = 200) {
    $this->maxWidth = $maxWidth;
    $this->maxHeight = $maxHeight;
  }
  public function isSupportedMime($mime) {
    $allowed = ['image/jpeg','image/png','image/gif'];
    return in_array($mime, $allowed, true);
  }
  public function createThumbnail($sourcePath, $destPath, $maxWidth, $maxHeight) {
    $info = getimagesize($sourcePath);
    if ($info === false) {
      return ['success'=>false, 'error'=>'Invalid image file'];
    }
    $srcW = $info[0];
    $srcH = $info[1];
    $type = $info[2];
    $srcMime = '';
    $srcImg = null;
    switch ($type) {
      case IMAGETYPE_JPEG:
        $srcMime = 'image/jpeg';
        $srcImg = imagecreatefromjpeg($sourcePath);
        break;
      case IMAGETYPE_PNG:
        $srcMime = 'image/png';
        $srcImg = imagecreatefrompng($sourcePath);
        break;
      case IMAGETYPE_GIF:
        $srcMime = 'image/gif';
        $srcImg = imagecreatefromgif($sourcePath);
        break;
      default:
        return ['success'=>false, 'error'=>'Unsupported image type'];
    }
    $ratio = min($maxWidth / $srcW, $maxHeight / $srcH, 1);
    $dstW = (int)round($srcW * $ratio);
    $dstH = (int)round($srcH * $ratio);
    $thumb = imagecreatetruecolor($dstW, $dstH);
    if ($srcMime === 'image/png') {
      imagealphablending($thumb, false);
      imagesavealpha($thumb, true);
      $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
      imagefilledRectangle($thumb, 0, 0, $dstW, $dstH, $transparent);
    }
    imagecopyresampled($thumb, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    switch ($srcMime) {
      case 'image/jpeg':
        imagejpeg($thumb, $destPath, 90);
        break;
      case 'image/png':
        imagepng($thumb, $destPath);
        break;
      case 'image/gif':
        imagegif($thumb, $destPath);
        break;
    }
    imagedestroy($thumb);
    imagedestroy($srcImg);
    return ['success'=>true, 'thumb_path'=>$destPath, 'width'=>$dstW, 'height'=>$dstH];
  }
}
?>