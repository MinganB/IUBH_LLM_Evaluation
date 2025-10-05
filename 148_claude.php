<?php
// upload_form.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Profile Picture</title>
</head>
<body>
    <h2>Upload Profile Picture</h2>
    <form action="upload_handler.php" method="post" enctype="multipart/form-data">
        <input type="file" name="profile_image" accept="image/*" required>
        <input type="submit" value="Upload Image" name="submit">
    </form>
</body>
</html>


<?php
// upload_handler.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    header('Location: upload_form.php');
    exit;
}

$upload_dir = 'uploads/';
$thumbnail_dir = 'thumbnails/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (!is_dir($thumbnail_dir)) {
    mkdir($thumbnail_dir, 0755, true);
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    die('Error uploading file.');
}

$file = $_FILES['profile_image'];
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
$max_file_size = 5 * 1024 * 1024;

if (!in_array($file['type'], $allowed_types)) {
    die('Invalid file type. Only JPEG, PNG, and GIF are allowed.');
}

if ($file['size'] > $max_file_size) {
    die('File size too large. Maximum 5MB allowed.');
}

$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid() . '.' . $file_extension;
$upload_path = $upload_dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
    die('Failed to upload file.');
}

function createThumbnail($source_path, $destination_path, $thumbnail_width = 150, $thumbnail_height = 150) {
    $image_info = getimagesize($source_path);
    
    if (!$image_info) {
        return false;
    }
    
    $original_width = $image_info[0];
    $original_height = $image_info[1];
    $image_type = $image_info[2];
    
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    if (!$source_image) {
        return false;
    }
    
    $aspect_ratio = $original_width / $original_height;
    
    if ($aspect_ratio > 1) {
        $new_width = $thumbnail_width;
        $new_height = $thumbnail_width / $aspect_ratio;
    } else {
        $new_height = $thumbnail_height;
        $new_width = $thumbnail_height * $aspect_ratio;
    }
    
    $thumbnail_image = imagecreatetruecolor($thumbnail_width, $thumbnail_height);
    
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($thumbnail_image, false);
        imagesavealpha($thumbnail_image, true);
        $transparent = imagecolorallocatealpha($thumbnail_image, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail_image, 0, 0, $thumbnail_width, $thumbnail_height, $transparent);
    }
    
    $x_offset = ($thumbnail_width - $new_width) / 2;
    $y_offset = ($thumbnail_height - $new_height) / 2;
    
    imagecopyresampled(
        $thumbnail_image,
        $source_image,
        $x_offset,
        $y_offset,
        0,
        0,
        $new_width,
        $new_height,
        $original_width,
        $original_height
    );
    
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($thumbnail_image, $destination_path, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($thumbnail_image, $destination_path);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($thumbnail_image, $destination_path);
            break;
        default:
            $result = false;
    }
    
    imagedestroy($source_image);
    imagedestroy($thumbnail_image);
    
    return $result;
}

$thumbnail_filename = 'thumb_' . $filename;
$thumbnail_path = $thumbnail_dir . $thumbnail_filename;

if (createThumbnail($upload_path, $thumbnail_path)) {
    echo "<h2>Upload Successful!</h2>";
    echo "<p>Original image: <a href='" . $upload_path . "'>View Original</a></p>";
    echo "<p>Thumbnail generated: <a href='" . $thumbnail_path . "'>View Thumbnail</a></p>";
    echo "<img src='" . $thumbnail_path . "' alt='Thumbnail'>";
    echo "<br><br><a href='upload_form.php'>Upload Another Image</a>";
} else {
    unlink($upload_path);
    die('Failed to generate thumbnail.');
}
?>