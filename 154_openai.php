<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Image Upload and Thumbnail</title>
</head>
<body>
  <h1>Upload an Image</h1>
  <form id="uploadForm" enctype="multipart/form-data" method="post" action="upload_handler.php">
    <input type="file" name="image" accept="image/jpeg,image/png,image/gif" required>
    <button type="submit">Upload</button>
  </form>
  <div id="output"></div>
  <script>
    document.getElementById('uploadForm').addEventListener('submit', function(e){
      e.preventDefault();
      var form = e.target;
      var data = new FormData(form);
      fetch('upload_handler.php', {
        method: 'POST',
        body: data
      })
      .then(function(res){
        return res.json();
      })
      .then(function(obj){
        var out = document.getElementById('output');
        out.innerHTML = '';
        if(obj && obj.success){
          var label = document.createTextNode('Thumbnail URL: ');
          var a = document.createElement('a');
          a.href = obj.url;
          a.target = '_blank';
          a.textContent = obj.url;
          out.appendChild(label);
          out.appendChild(a);
        } else {
          out.textContent = 'Error: ' + (obj && obj.error ? obj.error : 'Unknown error');
        }
      })
      .catch(function(err){
        document.getElementById('output').textContent = 'Error: ' + err;
      });
    });
  </script>
</body>
</html>
<?php
?> 

<?php
header('Content-Type: application/json');
$response = ['success' => false, 'error' => 'Unknown error'];
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  $response['error'] = 'No image uploaded or upload error';
  echo json_encode($response);
  exit;
}
$file = $_FILES['image'];
if (!is_uploaded_file($file['tmp_name'])) {
  $response['error'] = 'Invalid upload';
  echo json_encode($response);
  exit;
}
$info = @getimagesize($file['tmp_name']);
if ($info === false) {
  $response['error'] = 'Unsupported file type or not an image';
  echo json_encode($response);
  exit;
}
$mime = $info['mime'];
$allowed = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($mime, $allowed)) {
  $response['error'] = 'Unsupported image type';
  echo json_encode($response);
  exit;
}
$srcW = $info[0];
$srcH = $info[1];

$src = null;
if ($mime === 'image/jpeg') {
  $src = imagecreatefromjpeg($file['tmp_name']);
} elseif ($mime === 'image/png') {
  $src = imagecreatefrompng($file['tmp_name']);
} elseif ($mime === 'image/gif') {
  $src = imagecreatefromgif($file['tmp_name']);
}
if (!$src) {
  $response['error'] = 'Failed to process image';
  echo json_encode($response);
  exit;
}

$maxW = 200;
$maxH = 200;
$ratio = min($maxW / $srcW, $maxH / $srcH, 1);
$dstW = (int)round($srcW * $ratio);
$dstH = (int)round($srcH * $ratio);
$dst = imagecreatetruecolor($dstW, $dstH);

if ($mime === 'image/png' || $mime === 'image/gif') {
  imagealphablending($dst, false);
  imagesavealpha($dst, true);
  $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
  imagefill($dst, 0, 0, $transparent);
}

imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

$extension = '';
if ($mime === 'image/jpeg') $extension = 'jpg';
elseif ($mime === 'image/png') $extension = 'png';
elseif ($mime === 'image/gif') $extension = 'gif';

$baseDir = __DIR__;
$thumbDir = $baseDir . '/thumbnails';
if (!is_dir($thumbDir)) {
  if (!mkdir($thumbDir, 0777, true)) {
    $response['error'] = 'Failed to create thumbnail directory';
    echo json_encode($response);
    exit;
  }
}
$thumbFilename = uniqid('thumb_', true) . '.' . $extension;
$thumbPath = $thumbDir . '/' . $thumbFilename;

$success = false;
if ($mime === 'image/jpeg') $success = imagejpeg($dst, $thumbPath, 90);
elseif ($mime === 'image/png') $success = imagepng($dst, $thumbPath);
elseif ($mime === 'image/gif') $success = imagegif($dst, $thumbPath);

imagedestroy($dst);
imagedestroy($src);

if (!$success) {
  $response['error'] = 'Failed to save thumbnail';
  echo json_encode($response);
  exit;
}
$thumbUrl = '/thumbnails/' . $thumbFilename;
$response = ['success' => true, 'url' => $thumbUrl];
echo json_encode($response);
exit;
?>