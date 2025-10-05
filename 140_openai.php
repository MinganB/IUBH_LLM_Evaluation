<?php
session_start();

define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR.'/data');
define('UPLOAD_DIR', BASE_DIR.'/uploads');
define('ORIG_DIR', UPLOAD_DIR.'/originals');
define('THUMB_DIR', UPLOAD_DIR.'/thumbnails');
define('ALLOWED_MIME', ['image/jpeg','image/png','image/gif']);
define('MAX_FILE_SIZE', 2 * 1024 * 1024);
define('THUMB_SIZE', 150);

function ensureDir($path){
    if(!is_dir($path)){
        mkdir($path, 0775, true);
    }
}

ensureDir(DATA_DIR);
ensureDir(UPLOAD_DIR);
ensureDir(ORIG_DIR);
ensureDir(THUMB_DIR);

$profilesPath = DATA_DIR.'/profiles.json';
if(!file_exists($profilesPath)){
    file_put_contents($profilesPath, '{}');
}
$profiles = json_decode(@file_get_contents($profilesPath), true) ?: [];

if(!isset($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function saveProfiles($path, $profiles){
    file_put_contents($path, json_encode($profiles, JSON_PRETTY_PRINT));
}

// Simple login system: username-based
if(!isset($_SESSION['user_id'])){
    if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'])){
        $uname = $_POST['login_username'];
        $uname = preg_replace('/[^A-Za-z0-9_\-\.]/','', trim($uname));
        if($uname !== ''){
            $_SESSION['user_id'] = $uname;
            header('Location: '.$_SERVER['PHP_SELF']);
            exit;
        } else {
            $loginError = 'Invalid username';
        }
    }

    echo '<!doctype html><html><head><meta charset="utf-8"><title>Login</title></head><body>';
    if(isset($loginError)){
        echo '<p style="color:red;">'.htmlspecialchars($loginError).'</p>';
    }
    echo '<h2>Sign in</h2>
    <form method="post" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'">
        <label>Username: <input type="text" name="login_username" required></label>
        <button type="submit">Login</button>
    </form>';
    echo '</body></html>';
    exit;
}

// Authenticated user
$userIdRaw = $_SESSION['user_id'];
$userId = preg_replace('/[^A-Za-z0-9_\-\.]/','', $userIdRaw);

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // CSRF check
    $csrfOk = isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
    if(!$csrfOk){
        $error = 'Security token mismatch. Please try again.';
        echo '<p style="color:red;">'.htmlspecialchars($error).'</p>';
    }

    // Remove avatar
    if(isset($_POST['action']) && $_POST['action'] === 'remove' && $csrfOk){
        if(isset($profiles[$userId]['avatar'])){
            $oldOrig = BASE_DIR.'/'. ltrim($profiles[$userId]['avatar'], '/');
            if(file_exists($oldOrig)) unlink($oldOrig);
        }
        if(isset($profiles[$userId]['thumbnail'])){
            $oldThumb = BASE_DIR.'/'. ltrim($profiles[$userId]['thumbnail'], '/');
            if(file_exists($oldThumb)) unlink($oldThumb);
        }
        unset($profiles[$userId]);
        saveProfiles($profilesPath, $profiles);
        header('Location: '.$_SERVER['PHP_SELF']);
        exit;
    }

    // Upload
    if(isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK && $csrfOk){
        $f = $_FILES['avatar_file'];
        if($f['size'] > MAX_FILE_SIZE){
            $error = 'File is too large. Maximum allowed size is 2 MB.';
        } else {
            $tmpPath = $f['tmp_name'];
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $tmpPath);
            finfo_close($fi);

            if(!in_array($mime, ALLOWED_MIME)){
                $error = 'Unsupported file type. Allowed: JPEG, PNG, GIF.';
            } else {
                $ext = '';
                switch($mime){
                    case 'image/jpeg': $ext = '.jpg'; break;
                    case 'image/png':  $ext = '.png'; break;
                    case 'image/gif':  $ext = '.gif'; break;
                }

                $userOrigDir = ORIG_DIR.'/'.$userId;
                $userThumbDir = THUMB_DIR.'/'.$userId;
                ensureDir($userOrigDir);
                ensureDir($userThumbDir);

                $baseName = 'avatar_'.time().$ext;
                $origPath = $userOrigDir.'/'.$baseName;

                if(!move_uploaded_file($tmpPath, $origPath)){
                    $error = 'Failed to save uploaded file.';
                } else {
                    // Create thumbnail
                    $thumbPath = $userThumbDir.'/thumb_'.$baseName;
                    // Load source
                    switch($mime){
                        case 'image/jpeg':
                            $srcImg = imagecreatefromjpeg($origPath); break;
                        case 'image/png':
                            $srcImg = imagecreatefrompng($origPath); break;
                        case 'image/gif':
                            $srcImg = imagecreatefromgif($origPath); break;
                        default:
                            $srcImg = null;
                    }
                    if(!$srcImg){
                        $error = 'Could not process image.';
                    } else {
                        $srcW = imagesx($srcImg);
                        $srcH = imagesy($srcImg);
                        $thumb = imagecreatetruecolor(THUMB_SIZE, THUMB_SIZE);
                        $bg = imagecolorallocate($thumb, 255, 255, 255);
                        imagefill($thumb, 0, 0, $bg);

                        $scale = min(THUMB_SIZE / $srcW, THUMB_SIZE / $srcH);
                        $dstW = (int)round($srcW * $scale);
                        $dstH = (int)round($srcH * $scale);
                        $dstX = (int)floor((THUMB_SIZE - $dstW) / 2);
                        $dstY = (int)floor((THUMB_SIZE - $dstH) / 2);

                        imagecopyresampled($thumb, $srcImg, $dstX, $dstY, 0, 0, $dstW, $dstH, $srcW, $srcH);

                        switch($mime){
                            case 'image/jpeg':
                                imagejpeg($thumb, $thumbPath, 90); break;
                            case 'image/png':
                                imagepng($thumb, $thumbPath, 0); break;
                            case 'image/gif':
                                imagegif($thumb, $thumbPath); break;
                        }

                        imagedestroy($srcImg);
                        imagedestroy($thumb);
                    }

                    if(!isset($error)){
                        $avatarRel = 'uploads/originals/'.$userId.'/'.$baseName;
                        $thumbRel  = 'uploads/thumbnails/'.$userId.'/thumb_'.$baseName;

                        // Cleanup previous
                        if(isset($profiles[$userId]['avatar'])){
                            $old = BASE_DIR.'/'. ltrim($profiles[$userId]['avatar'], '/');
                            if(file_exists($old)) unlink($old);
                        }
                        if(isset($profiles[$userId]['thumbnail'])){
                            $oldt = BASE_DIR.'/'. ltrim($profiles[$userId]['thumbnail'], '/');
                            if(file_exists($oldt)) unlink($oldt);
                        }

                        $profiles[$userId] = [
                            'avatar' => $avatarRel,
                            'thumbnail' => $thumbRel
                        ];

                        saveProfiles($profilesPath, $profiles);
                        header('Location: '.$_SERVER['PHP_SELF']);
                        exit;
                    }
                }
            }
        }
        // If error occurred, display below
    }
}

// Prepare data for frontend
$avatarThumb = '';
if(isset($profiles[$userId]['thumbnail'])){
    $avatarThumb = $profiles[$userId]['thumbnail'];
}

$csrfToken = $_SESSION['csrf_token'];
echo '<!doctype html><html><head><meta charset="utf-8"><title>Profile Picture Uploader</title></head><body>';
echo '<h2>Upload your profile picture</h2>';

if(isset($profiles[$userId]['avatar']) && isset($profiles[$userId]['thumbnail'])){
    echo '<div>';
    echo '<p>Current avatar:</p>';
    echo '<img src="'.htmlspecialchars($avatarThumb).'" alt="Thumbnail" width="150" height="150" style="border:1px solid #ccc;">';
    echo '</div>';
} else {
    echo '<p>No profile picture uploaded yet.</p>';
}

echo '<form method="post" enctype="multipart/form-data" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'">';
echo '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($csrfToken).'">';
echo '<input type="hidden" name="user_id" value="'.htmlspecialchars($userId).'">';
echo '<input type="hidden" name="action" value="upload">';
echo '<label>Choose image (JPEG, PNG, GIF; max 2MB): <input type="file" name="avatar_file" accept="image/*" required></label>';
echo '<button type="submit">Upload</button>';
echo '</form>';

echo '<form method="post" action="'.htmlspecialchars($_SERVER['PHP_SELF']).'">';
echo '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($csrfToken).'">';
echo '<input type="hidden" name="user_id" value="'.htmlspecialchars($userId).'">';
echo '<input type="hidden" name="action" value="remove">';
echo '<button type="submit" onclick="return confirm(\'Remove current avatar?\');">Remove Avatar</button>';
echo '</form>';

echo '<p>Security: This module validates file types, sizes, and uses CSRF protection. Thumbnails are generated server-side for efficient display.</p>';
echo '</body></html>';
?>