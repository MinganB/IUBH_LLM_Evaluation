<?php
$uploadDir = __DIR__ . '/uploads';
$thumbDir  = __DIR__ . '/thumbnails';

if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
if (!is_dir($thumbDir))  mkdir($thumbDir, 0777, true);

$uploadError = '';
$thumbUrl = '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$msg = '';
$thumb = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    $uploadError = 'No file uploaded or upload error.';
  } else {
    $file = $_FILES['profile_image'];
    $tmpPath = $file['tmp_name'];
    $fName   = $file['name'];
    $fSize   = $file['size'];
    $fType   = mime_content_type($tmpPath);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($fType, $allowedTypes)) {
      $uploadError = 'Unsupported file type. Allowed: JPEG, PNG, GIF.';
    }

    $maxSize = 5 * 1024 * 1024;
    if ($fSize > $maxSize) {
      $uploadError = 'File is too large. Maximum 5MB.';
    }

    if (!$uploadError) {
      $extMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif'
      ];
      $ext = $extMap[$fType];
      $baseName = pathinfo($fName, PATHINFO_FILENAME);
      $baseName = preg_replace('/[^A-Za-z0-9_-]/', '', $baseName);
      if ($baseName === '') $baseName = 'upload';
      $uniqueName = $baseName . '_' . uniqid('', true) . '.' . $ext;
      $destPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $uniqueName;

      if (!move_uploaded_file($tmpPath, $destPath)) {
        $uploadError = 'Failed to save uploaded file.';
      } else {
        $thumbMaxW = 200;
        $thumbMaxH = 200;
        switch ($fType) {
          case 'image/jpeg':
            $srcImg = imagecreatefromjpeg($destPath);
            break;
          case 'image/png':
            $srcImg = imagecreatefrompng($destPath);
            break;
          case 'image/gif':
            $srcImg = imagecreatefromgif($destPath);
            break;
          default:
            $srcImg = null;
        }

        if (!$srcImg) {
          $uploadError = 'Unsupported image type.';
        } else {
          $srcW = imagesx($srcImg);
          $srcH = imagesy($srcImg);
          $ratio = min($thumbMaxW / $srcW, $thumbMaxH / $srcH, 1);
          $dstW = (int)round($srcW * $ratio);
          $dstH = (int)round($srcH * $ratio);
          $thumbImg = imagecreatetruecolor($dstW, $dstH);

          if ($fType === 'image/png' || $fType === 'image/gif') {
            imagealphablending($thumbImg, false);
            imagesavealpha($thumbImg, true);
            $transparent = imagecolorallocatealpha($thumbImg, 0, 0, 0, 127);
            imagefill($thumbImg, 0, 0, $transparent);
          }

          imagecopyresampled($thumbImg, $srcImg, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

          $thumbName = 'thumb_' . pathinfo($uniqueName, PATHINFO_FILENAME) . '.' . $ext;
          $thumbPath = rtrim($thumbDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $thumbName;

          if ($fType === 'image/png') {
            imagepng($thumbImg, $thumbPath);
          } elseif ($fType === 'image/gif') {
            imagegif($thumbImg, $thumbPath);
          } else {
            imagejpeg($thumbImg, $thumbPath, 90);
          }

          imagedestroy($srcImg);
          imagedestroy($thumbImg);
          $thumbUrl = 'thumbnails/' . $thumbName;
        }
      }
    }
  }

  if ($uploadError) {
    header('Location: upload_handler.php?status=error&message=' . urlencode($uploadError));
    exit;
  } else {
    header('Location: upload_handler.php?status=success&thumb=' . urlencode($thumbUrl));
    exit;
  }
}

if ($status === 'error') {
  $msg = isset($_GET['message']) ? $_GET['message'] : '';
} elseif ($status === 'success') {
  $thumb = isset($_GET['thumb']) ? $_GET['thumb'] : '';
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Upload Profile Picture</title>
</head>
<body>
  <h2>Upload Profile Picture</h2>
  <?php if ($msg): ?>
  <p><?php echo htmlspecialchars($msg); ?></p>
  <?php endif; ?>
  <?php if ($thumb): ?>
  <p>Thumbnail:</p>
  <img src="<?php echo htmlspecialchars($thumb); ?>" alt="Thumbnail">
  <?php endif; ?>
  <form action="upload_handler.php" method="post" enctype="multipart/form-data">
    <input type="file" name="profile_image" accept="image/*" required>
    <button type="submit">Upload</button>
  </form>
</body>
</html>
?>