<?php
<!DOCTYPE html>
<html>
<head>
    <title>Image Upload</title>
</head>
<body>
    <form action="upload_handler.php" method="post" enctype="multipart/form-data">
        <label for="image">Select image to upload:</label>
        <input type="file" name="image" id="image" accept="image/*" required>
        <input type="submit" value="Upload Image" name="submit">
    </form>
</body>
</html>


<?php
session_start();

if (!isset($_POST['submit'])) {
    header('Location: index.php');
    exit();
}

$target_dir = "uploads/";
$thumbnail_dir = "thumbnails/";

if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

if (!is_dir($thumbnail_dir)) {
    mkdir($thumbnail_dir, 0755, true);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    die("Upload failed.");
}

$file = $_FILES['image'];
$file_size = $file['size'];
$file_tmp = $file['tmp_name'];
$file_type = $file['type'];
$file_name = $file['name'];

$max_file_size = 5 * 1024 * 1024;
if ($file_size > $max_file_size) {
    die("File too large. Maximum size is 5MB.");
}

$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file_type, $allowed_types)) {
    die("Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.");
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detected_type = finfo_file($finfo, $file_tmp);
finfo_close($finfo);

if (!in_array($detected_type, $allowed_types)) {
    die("File type validation failed.");
}

$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($file_ext, $allowed_extensions)) {
    die("Invalid file extension.");
}

$safe_filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
$target_file = $target_dir . $safe_filename;
$thumbnail_file = $thumbnail_dir . $safe_filename;

if (!move_uploaded_file($file_tmp, $target_file)) {
    die("Failed to save uploaded file.");
}

function createThumbnail($source, $destination, $max_width = 150, $max_height = 150) {
    $image_info = getimagesize($source);
    if ($image_info === false) {
        return false;
    }
    
    $width = $image_info[0];
    $height = $image_info[1];
    $type = $image_info[2];
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            $source_image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if ($source_image === false) {
        return false;
    }
    
    $ratio = min($max_width / $width, $max_height / $height);
    $new_width = intval($width * $ratio);
    $new_height = intval($height * $ratio);
    
    $thumbnail = imagecreatetruecolor($new_width, $new_height);
    
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $new_width, $new_height, $transparent);
    }
    
    imagecopyresampled($thumbnail, $source_image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($thumbnail, $destination, 80);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($thumbnail, $destination, 8);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($thumbnail, $destination);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($thumbnail, $destination, 80);
            break;
    }
    
    imagedestroy($source_image);
    imagedestroy($thumbnail);
    
    return $result;
}

if (createThumbnail($target_file, $thumbnail_file)) {
    echo "Image uploaded and thumbnail created successfully.";
    echo "<br>Original: " . htmlspecialchars($safe_filename);
    echo "<br>Thumbnail: " . htmlspecialchars($safe_filename);
} else {
    unlink($target_file);
    die("Failed to create thumbnail.");
}
?>