<?php
$publicDir = __DIR__;
$origDir = $publicDir.'/uploads/originals';
$thumbDir = $publicDir.'/uploads/thumbnails';

$error = null;
$uploadedFinalName = null;
$uploadedThumbName = null;
$size = 180;

function ensureDirectories($dirs){
    foreach ($dirs as $d){
        if (!is_dir($d)){
            mkdir($d, 0777, true);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK){
        $error = 'No file uploaded or upload error.';
    } else {
        $file = $_FILES['image'];
        $tmpPath = $file['tmp_name'];
        $origName = $file['name'];
        $imageInfo = getimagesize($tmpPath);
        if ($imageInfo === false){
            $error = 'Invalid image file.';
        } else {
            $mime = $imageInfo['mime'];
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/gif'  => 'gif',
                'image/webp' => 'webp'
            ];
            if (!isset($allowed[$mime])){
                $error = 'Unsupported image type.';
            } else {
                ensureDirectories([$origDir, $thumbDir]);
                $ext = $allowed[$mime];
                $nameOnly = pathinfo($origName, PATHINFO_FILENAME);
                $safeName = preg_replace('/[^A-Za-z0-9_-]+/','_', $nameOnly);
                $unique = time().'_'.mt_rand(1000,9999);
                $finalName = $safeName.'_'.$unique.'.'.$ext;
                $destOriginal = $origDir.'/'.$finalName;

                if (!move_uploaded_file($tmpPath, $destOriginal)){
                    $error = 'Failed to save uploaded file.';
                } else {
                    $thumbName = $finalName;
                    $destThumb = $thumbDir.'/'.$thumbName;

                    $srcImg = null;
                    switch ($mime){
                        case 'image/jpeg':
                            $srcImg = imagecreatefromjpeg($destOriginal);
                            break;
                        case 'image/png':
                            $srcImg = imagecreatefrompng($destOriginal);
                            break;
                        case 'image/gif':
                            $srcImg = imagecreatefromgif($destOriginal);
                            break;
                        case 'image/webp':
                            if (function_exists('imagecreatefromwebp')){
                                $srcImg = imagecreatefromwebp($destOriginal);
                            }
                            break;
                    }

                    if (!$srcImg){
                        $error = 'Failed to process source image.';
                    } else {
                        $srcW = imagesx($srcImg);
                        $srcH = imagesy($srcImg);
                        $cropSize = min($srcW, $srcH);
                        $srcX = intval(($srcW - $cropSize) / 2);
                        $srcY = intval(($srcH - $cropSize) / 2);

                        $dst = imagecreatetruecolor($size, $size);
                        if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp'){
                            imagealphablending($dst, false);
                            imagesavealpha($dst, true);
                            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                            imagefill($dst, 0, 0, $transparent);
                        }

                        imagecopyresampled($dst, $srcImg, 0, 0, $srcX, $srcY, $size, $size, $cropSize, $cropSize);

                        switch ($mime){
                            case 'image/jpeg':
                                imagejpeg($dst, $destThumb, 90);
                                break;
                            case 'image/png':
                                imagepng($dst, $destThumb);
                                break;
                            case 'image/gif':
                                imagegif($dst, $destThumb);
                                break;
                            case 'image/webp':
                                if (function_exists('imagewebp')){
                                    imagewebp($dst, $destThumb, 90);
                                } else {
                                    $error = 'WebP support is not available on this server.';
                                }
                                break;
                        }

                        imagedestroy($dst);
                        imagedestroy($srcImg);

                        if ($error === null){
                            $uploadedFinalName = $finalName;
                            $uploadedThumbName = $thumbName;
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Profile Picture</title>
</head>
<body>
<h1>Upload Profile Picture</h1>
<form method="POST" enctype="multipart/form-data" action="">
    <input type="file" name="image" accept="image/*" required />
    <button type="submit">Upload</button>
</form>
<?php if ($error !== null): ?>
    <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<?php if ($uploadedFinalName && $uploadedThumbName): ?>
    <h2>Uploaded Image</h2>
    <p><img src="/uploads/originals/<?php echo htmlspecialchars($uploadedFinalName); ?>" alt="Original" style="max-width:400px; border:1px solid #ccc;" /></p>
    <h2>Thumbnail</h2>
    <p><img src="/uploads/thumbnails/<?php echo htmlspecialchars($uploadedThumbName); ?>" alt="Thumbnail" style="width:180px; height:180px; object-fit: cover; border:1px solid #ccc;" /></p>
<?php endif; ?>
</body>
</html>
?>