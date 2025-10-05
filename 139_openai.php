<?php
session_start();

$UPLOAD_DIR = __DIR__ . '/uploads';
$ORIG_DIR = $UPLOAD_DIR . '/originals';
$THUMBS_DIR = $UPLOAD_DIR . '/thumbs';
$MAX_SIZE = 5 * 1024 * 1024;
$THUMB_SIZE = 200;

if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);
if (!is_dir($ORIG_DIR)) mkdir($ORIG_DIR, 0755, true);
if (!is_dir($THUMBS_DIR)) mkdir($THUMBS_DIR, 0755, true);

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 'user_' . bin2hex(random_bytes(4));
}
$user_id = $_SESSION['user_id'];

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        if ($_FILES['image']['size'] > $MAX_SIZE) {
            $errors[] = 'File size exceeds limit.';
        } else {
            $tmp = $_FILES['image']['tmp_name'];
            $info = getimagesize($tmp);
            if ($info === false) {
                $errors[] = 'Invalid image file.';
            } else {
                $mime = $info['mime'];
                $type = $info[2];
                switch ($type) {
                    case IMAGETYPE_JPEG: $ext = 'jpg'; break;
                    case IMAGETYPE_PNG: $ext = 'png'; break;
                    case IMAGETYPE_GIF: $ext = 'gif'; break;
                    case IMAGETYPE_WEBP: $ext = 'webp'; break;
                    default: $errors[] = 'Unsupported image type.'; $ext = '';
                }
                if (empty($errors)) {
                    $orig_path = $ORIG_DIR . '/' . $user_id . '.' . $ext;
                    if (!move_uploaded_file($tmp, $orig_path)) {
                        $errors[] = 'Failed to save uploaded file.';
                    } else {
                        switch ($type) {
                            case IMAGETYPE_JPEG: $src = imagecreatefromjpeg($orig_path); break;
                            case IMAGETYPE_PNG: $src = imagecreatefrompng($orig_path); break;
                            case IMAGETYPE_GIF: $src = imagecreatefromgif($orig_path); break;
                            case IMAGETYPE_WEBP: $src = imagecreatefromwebp($orig_path); break;
                            default: $src = false;
                        }
                        if (!$src) {
                            $errors[] = 'Failed to read image.';
                        } else {
                            $src_w = imagesx($src);
                            $src_h = imagesy($src);
                            $thumb = imagecreatetruecolor($THUMB_SIZE, $THUMB_SIZE);
                            $bg = imagecolorallocate($thumb, 255, 255, 255);
                            imagefill($thumb, 0, 0, $bg);

                            $scale = max($THUMB_SIZE / $src_w, $THUMB_SIZE / $src_h);
                            $new_w = (int)round($src_w * $scale);
                            $new_h = (int)round($src_h * $scale);
                            $dest_x = (int)floor(($THUMB_SIZE - $new_w) / 2);
                            $dest_y = (int)floor(($THUMB_SIZE - $new_h) / 2);

                            imagecopyresampled($thumb, $src, $dest_x, $dest_y, 0, 0, $new_w, $new_h, $src_w, $src_h);

                            $thumb_path = $THUMBS_DIR . '/' . $user_id . '.' . $ext;
                            switch ($ext) {
                                case 'jpg':
                                case 'jpeg':
                                    imagejpeg($thumb, $thumb_path, 92);
                                    break;
                                case 'png':
                                    imagepng($thumb, $thumb_path);
                                    break;
                                case 'gif':
                                    imagegif($thumb, $thumb_path);
                                    break;
                                case 'webp':
                                    imagewebp($thumb, $thumb_path);
                                    break;
                            }

                            imagedestroy($thumb);
                            imagedestroy($src);
                            $success = 'Profile image uploaded and thumbnail created.';
                        }
                    }
                }
            }
        }
    } else {
        $errors[] = 'No file uploaded or upload error.';
    }
}

$thumb_url = '';
foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $e) {
    $path = $THUMBS_DIR . '/' . $user_id . '.' . $e;
    if (file_exists($path)) {
        $thumb_url = 'uploads/thumbs/' . $user_id . '.' . $e;
        break;
    }
}
$orig_exists = false;
foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $e) {
    $path = $ORIG_DIR . '/' . $user_id . '.' . $e;
    if (file_exists($path)) {
        $orig_exists = true;
        break;
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
    <?php if (!empty($errors)): ?>
        <div>
            <?php foreach ($errors as $err): ?>
                <div><?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form enctype="multipart/form-data" method="post">
        <input type="file" name="image" accept="image/*" />
        <button type="submit">Upload</button>
    </form>

    <?php if ($thumb_url): ?>
        <h2>Current Avatar</h2>
        <img src="<?php echo htmlspecialchars($thumb_url); ?>" alt="Avatar" width="200" height="200" />
    <?php else: ?>
        <p>No avatar uploaded yet.</p>
    <?php endif; ?>

    <?php if ($orig_exists): ?>
        <p>Original image stored on server.</p>
    <?php endif; ?>
</body>
</html>
?>