<?php
session_start();

$ORIGINALS_DIR = __DIR__ . '/storage/originals';
$THUMBS_DIR = __DIR__ . '/storage/thumbs';

function ensureDir($dir) {
  if (!is_dir($dir)) {
    return mkdir($dir, 0775, true);
  }
  return true;
}

function createThumbnail($srcPath, $dstPath, $maxW, $maxH) {
  $info = getimagesize($srcPath);
  if ($info === false) return false;
  $width = $info[0];
  $height = $info[1];
  $mime = $info['mime'];
  switch ($mime) {
    case 'image/jpeg':
    case 'image/pjpeg':
      $srcImg = imagecreatefromjpeg($srcPath);
      break;
    case 'image/png':
      $srcImg = imagecreatefrompng($srcPath);
      break;
    case 'image/gif':
      $srcImg = imagecreatefromgif($srcPath);
      break;
    default:
      return false;
  }
  if (!$srcImg) return false;
  $ratio = min($maxW / $width, $maxH / $height, 1);
  $newW = (int)round($width * $ratio);
  $newH = (int)round($height * $ratio);
  $dstImg = imagecreatetruecolor($newW, $newH);
  if ($mime === 'image/png') {
    imagealphablending($dstImg, false);
    imagesavealpha($dstImg, true);
  }
  imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $width, $height);
  $result = imagejpeg($dstImg, $dstPath, 90);
  imagedestroy($srcImg);
  imagedestroy($dstImg);
  return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  $response = ['status' => 'error', 'message' => 'Unknown error'];

  $csrfToken = $_POST['csrf_token'] ?? '';
  if (!isset($_SESSION['csrf_token']) || $csrfToken !== $_SESSION['csrf_token']) {
    $response['message'] = 'Invalid CSRF token';
    echo json_encode($response); exit;
  }

  if (!isset($_POST['user_id']) || !ctype_digit($_POST['user_id'])) {
    $response['message'] = 'Invalid user_id';
    echo json_encode($response); exit;
  }

  if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $response['message'] = 'No image uploaded';
    echo json_encode($response); exit;
  }

  $file = $_FILES['image'];
  $tmp = $file['tmp_name'];
  if (!is_uploaded_file($tmp)) {
    $response['message'] = 'Invalid upload';
    echo json_encode($response); exit;
  }

  $info = getimagesize($tmp);
  if ($info === false) {
    $response['message'] = 'Invalid image';
    echo json_encode($response); exit;
  }
  $mime = $info['mime'];
  $allowed = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif'
  ];
  if (!isset($allowed[$mime])) {
    $response['message'] = 'Unsupported image type';
    echo json_encode($response); exit;
  }

  if (!is_dir($ORIGINALS_DIR) && !ensureDir($ORIGINALS_DIR)) {
    $response['message'] = 'Server error creating storage';
    echo json_encode($response); exit;
  }
  if (!is_dir($THUMBS_DIR) && !ensureDir($THUMBS_DIR)) {
    $response['message'] = 'Server error creating storage';
    echo json_encode($response); exit;
  }

  $userId = $_POST['user_id'];
  $timestamp = time();
  $baseName = 'user_' . $userId . '_' . $timestamp;
  $originalExt = $allowed[$mime];
  $originalFilename = $baseName . '.' . $originalExt;
  $originalPath = $ORIGINALS_DIR . '/' . $originalFilename;

  if (!move_uploaded_file($tmp, $originalPath)) {
    $response['message'] = 'Failed to save file';
    echo json_encode($response); exit;
  }

  $thumbPath = $THUMBS_DIR . '/' . $baseName . '_thumb.jpg';
  if (!createThumbnail($originalPath, $thumbPath, 200, 200)) {
    $response['message'] = 'Thumbnail generation failed';
    echo json_encode($response); exit;
  }

  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'];
  $originalUrl = $scheme . '://' . $host . '/storage/originals/' . $originalFilename;
  $thumbUrl = $scheme . '://' . $host . '/storage/thumbs/' . basename($thumbPath);

  $response = [
    'status' => 'success',
    'message' => 'Image uploaded',
    'original' => $originalUrl,
    'thumbnail' => $thumbUrl
  ];
  echo json_encode($response);
  exit;
}

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html>
<head>
  <title>Profile Image Upload</title>
</head>
<body>
  <h1>Upload Profile Image</h1>
  <form id="uploadForm" method="POST" enctype="multipart/form-data" action="">
    <label>User ID: <input type="text" name="user_id" required></label><br/>
    <label>Image: <input type="file" name="image" accept="image/*" required></label><br/>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
    <button type="submit">Upload</button>
  </form>
  <div id="response" style="margin-top:20px;"></div>
  <h2>Preview</h2>
  <div id="preview"></div>
  <script>
    document.getElementById('uploadForm').addEventListener('submit', function(e){
      e.preventDefault();
      var formData = new FormData(this);
      fetch('', {
        method: 'POST',
        body: formData
      }).then(async function(res){
        var data = null;
        try { data = await res.json(); } catch(err) { data = {status:'error', message:'Invalid response'}; }
        var respDiv = document.getElementById('response');
        respDiv.textContent = data ? JSON.stringify(data) : 'No response';
        if (data && data.status === 'success') {
          var preview = document.getElementById('preview');
          preview.innerHTML = '<p>Original:</p><img src="' + data.original + '" style="max-width:200px;"/><p>Thumbnail:</p><img src="' + data.thumbnail + '" style="max-width:200px;"/>';
        }
      }).catch(function(err){
        document.getElementById('response').textContent = 'Error: ' + err;
      });
    });
  </script>
</body>
</html>
?>