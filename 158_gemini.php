<?php
// index.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Profile Picture</title>
</head>
<body>
    <h1>Upload Your Profile Picture</h1>
    <form id="uploadForm" action="upload_handler.php" method="post" enctype="multipart/form-data">
        <input type="file" name="profile_picture" accept="image/jpeg, image/png, image/gif" required>
        <button type="submit">Upload</button>
    </form>
    <div id="response"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(this);

            fetch(this.action, {
                method: this.method,
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const responseDiv = document.getElementById('response');
                if (data.success) {
                    responseDiv.innerHTML = '<p>Upload successful! Thumbnail:</p><img src="' + data.thumbnail_url + '" alt="Thumbnail">';
                } else {
                    responseDiv.innerHTML = '<p style="color: red;">Error: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                document.getElementById('response').innerHTML = '<p style="color: red;">An unexpected error occurred.</p>';
            });
        });
    </script>
</body>
</html>

<?php
// upload_handler.php
header('Content-Type: application/json');

$thumbnail_dir = __DIR__ . '/thumbnails/';
$max_width = 200;
$max_height = 200;
$max_file_size = 5 * 1024 * 1024;

if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error.']);
    exit;
}

$file = $_FILES['profile_picture'];
$temp_path = $file['tmp_name'];
$original_name = $file['name'];
$file_size = $file['size'];

if ($file_size > $max_file_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds the limit of 5MB.']);
    exit;
}

$image_info = getimagesize($temp_path);
if (!$image_info) {
    echo json_encode(['success' => false, 'message' => 'Uploaded file is not a valid image.']);
    exit;
}

$mime_type = $image_info['mime'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];

if (!in_array($mime_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Unsupported image type. Only JPEG, PNG, and GIF are allowed.']);
    exit;
}

if (!is_dir($thumbnail_dir)) {
    if (!mkdir($thumbnail_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create thumbnail directory.']);
        exit;
    }
}

$extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
switch ($mime_type) {
    case 'image/jpeg':
        $extension = 'jpg';
        break;
    case 'image/png':
        $extension = 'png';
        break;
    case 'image/gif':
        $extension = 'gif';
        break;
}

$unique_filename = uniqid('thumb_', true) . '.' . $extension;
$thumbnail_path = $thumbnail_dir . $unique_filename;

list($original_width, $original_height) = $image_info;

$new_width = $original_width;
$new_height = $original_height;

if ($original_width > $max_width || $original_height > $max_height) {
    $width_ratio = $max_width / $original_width;
    $height_ratio = $max_height / $original_height;

    $ratio = min($width_ratio, $height_ratio);

    $new_width = (int)($original_width * $ratio);
    $new_height = (int)($original_height * $ratio);
}

$source_image = null;
switch ($mime_type) {
    case 'image/jpeg':
        $source_image = imagecreatefromjpeg($temp_path);
        break;
    case 'image/png':
        $source_image = imagecreatefrompng($temp_path);
        break;
    case 'image/gif':
        $source_image = imagecreatefromgif($temp_path);
        break;
}

if (!$source_image) {
    echo json_encode(['success' => false, 'message' => 'Failed to load source image.']);
    exit;
}

$thumbnail = imagecreatetruecolor($new_width, $new_height);

if ($mime_type === 'image/png' || $mime_type === 'image/gif') {
    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);
    $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
    imagefilledrectangle($thumbnail, 0, 0, $new_width, $new_height, $transparent);
}

imagecopyresampled(
    $thumbnail,
    $source_image,
    0, 0, 0, 0,
    $new_width, $new_height,
    $original_width, $original_height
);

$saved = false;
switch ($mime_type) {
    case 'image/jpeg':
        $saved = imagejpeg($thumbnail, $thumbnail_path, 90);
        break;
    case 'image/png':
        $saved = imagepng($thumbnail, $thumbnail_path);
        break;
    case 'image/gif':
        $saved = imagegif($thumbnail, $thumbnail_path);
        break;
}

imagedestroy($source_image);
imagedestroy($thumbnail);

if (file_exists($temp_path)) {
    unlink($temp_path);
}

if ($saved) {
    $thumbnail_url = 'thumbnails/' . $unique_filename;
    echo json_encode(['success' => true, 'thumbnail_url' => $thumbnail_url]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save thumbnail image.']);
}
?>