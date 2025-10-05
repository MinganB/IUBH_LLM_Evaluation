<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'error' => ''];

    if (!isset($_FILES['image'])) {
        $response['error'] = 'No file uploaded.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $file = $_FILES['image'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $response['error'] = 'Upload error code: ' . $file['error'];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $tmp = $file['tmp_name'];
    $size = $file['size'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($size <= 0 || $size > $maxSize) {
        $response['error'] = 'File size not allowed.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $imageInfo = getimagesize($tmp);
    if ($imageInfo === false) {
        $response['error'] = 'Invalid image file.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $mime = $imageInfo['mime'];
    $allowed = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($mime, $allowed)) {
        $response['error'] = 'Unsupported image type.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    switch ($mime) {
        case 'image/jpeg':
            $srcImg = imagecreatefromjpeg($tmp);
            $ext = 'jpg';
            break;
        case 'image/png':
            $srcImg = imagecreatefrompng($tmp);
            $ext = 'png';
            break;
        case 'image/gif':
            $srcImg = imagecreatefromgif($tmp);
            $ext = 'gif';
            break;
        default:
            $response['error'] = 'Unsupported image type.';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
    }

    if (!$srcImg) {
        $response['error'] = 'Failed to process image.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    list($origW, $origH) = getimagesize($tmp);
    $maxW = 200;
    $maxH = 200;
    $ratio = min($maxW / $origW, $maxH / $origH, 1);
    $thumbW = (int)round($origW * $ratio);
    $thumbH = (int)round($origH * $ratio);

    $thumb = imagecreatetruecolor($thumbW, $thumbH);
    if ($mime === 'image/png') {
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefill($thumb, 0, 0, $transparent);
    } elseif ($mime === 'image/gif') {
        $transparent = imagecolorallocate($thumb, 0, 0, 0);
        imagecolortransparent($thumb, $transparent);
    }

    imagecopyresampled($thumb, $srcImg, 0, 0, 0, 0, $thumbW, $thumbH, $origW, $origH);

    $thumbDir = __DIR__ . '/thumbnails';
    if (!is_dir($thumbDir)) {
        if (!mkdir($thumbDir, 0755, true)) {
            $response['error'] = 'Failed to create thumbnails directory.';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
    }

    $uniqueName = uniqid('thumb_', true) . '.' . $ext;
    $thumbPath = $thumbDir . '/' . $uniqueName;

    $saved = false;
    switch ($ext) {
        case 'jpg':
            $saved = imagejpeg($thumb, $thumbPath, 90);
            break;
        case 'png':
            $saved = imagepng($thumb, $thumbPath);
            break;
        case 'gif':
            $saved = imagegif($thumb, $thumbPath);
            break;
    }

    imagedestroy($srcImg);
    imagedestroy($thumb);

    if (!$saved) {
        $response['error'] = 'Failed to save thumbnail.';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $webPath = '/thumbnails/' . $uniqueName;
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $thumbnailUrl = $protocol . '://' . $host . $webPath;

    $response['success'] = true;
    $response['thumbnail_url'] = $thumbnailUrl;
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?><!DOCTYPE html>
<html>
<head>
  <title>Upload Image and Generate Thumbnail</title>
</head>
<body>
  <h1>Upload Image</h1>
  <form id="uploadForm" action="upload_handler.php" method="post" enctype="multipart/form-data">
    <input type="file" name="image" accept="image/*" required>
    <button type="submit">Upload</button>
  </form>
  <div id="result"></div>
  <script>
    const form = document.getElementById('uploadForm');
    const result = document.getElementById('result');
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      result.textContent = '';
      const formData = new FormData(form);
      try {
        const res = await fetch('upload_handler.php', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data && data.success) {
          result.textContent = 'Thumbnail URL: ' + data.thumbnail_url;
        } else {
          result.textContent = 'Error: ' + (data && data.error ? data.error : 'Unknown error');
        }
      } catch (err) {
        result.textContent = 'Error: ' + err.message;
      }
    });
  </script>
</body>
</html>
?>