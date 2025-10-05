<?php

header('Content-Type: application/json');

define('THUMBNAIL_DIR', __DIR__ . '/thumbnails/');
define('MAX_THUMBNAIL_WIDTH', 200);
define('MAX_THUMBNAIL_HEIGHT', 200);

$allowed_types = [
    'image/jpeg',
    'image/png',
    'image/gif'
];

$create_functions = [
    'image/jpeg' => 'imagecreatefromjpeg',
    'image/png' => 'imagecreatefrompng',
    'image/gif' => 'imagecreatefromgif',
];

$save_functions = [
    'image/jpeg' => 'imagejpeg',
    'image/png' => 'imagepng',
    'image/gif' => 'imagegif',
];

$file_extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
];

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or an upload error occurred. Error code: ' . ($_FILES['image']['error'] ?? 'N/A')]);
    exit;
}

$file_tmp_name = $_FILES['image']['tmp_name'];
$file_name = $_FILES['image']['name'];
$file_type = $_FILES['image']['type'];

if (!in_array($file_type, $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Unsupported file type. Only JPEG, PNG, GIF are allowed.']);
    exit;
}

if (!is_dir(THUMBNAIL_DIR)) {
    if (!mkdir(THUMBNAIL_DIR, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create thumbnail directory. Check permissions.']);
        exit;
    }
}

$create_func = $create_functions[$file_type];
$save_func = $save_functions[$file_type];
$file_ext = $file_extensions[$file_type];

$source_image = $create_func($file_tmp_name);

if (!$source_image) {
    echo json_encode(['success' => false, 'message' => 'Failed to open image file for processing.']);
    exit;
}

$original_width = imagesx($source_image);
$original_height = imagesy($source_image);

$ratio = min(MAX_THUMBNAIL_WIDTH / $original_width, MAX_THUMBNAIL_HEIGHT / $original_height);

$new_width = round($original_width * $ratio);
$new_height = round($original_height * $ratio);

$new_image = imagecreatetruecolor($new_width, $new_height);

if ($file_type == 'image/png' || $file_type == 'image/gif') {
    imagealphablending($new_image, false);
    imagesavealpha($new_image, true);
    $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
    imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
}

imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

$unique_filename = uniqid() . '-' . preg_replace('/[^a-zA-Z0-9_\-.]/', '', pathinfo($file_name, PATHINFO_FILENAME)) . '.' . $file_ext;
$thumbnail_path = THUMBNAIL_DIR . $unique_filename;

$quality = 90; 
if ($file_type == 'image/png') {
    $quality = 9; 
}

if ($file_type == 'image/jpeg') {
    $save_func($new_image, $thumbnail_path, $quality);
} elseif ($file_type == 'image/png') {
    $save_func($new_image, $thumbnail_path, $quality);
} else { 
    $save_func($new_image, $thumbnail_path);
}

imagedestroy($source_image);
imagedestroy($new_image);

if (!file_exists($thumbnail_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save thumbnail image to disk.']);
    exit;
}

$thumbnail_url = str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath($thumbnail_path));
$thumbnail_url = str_replace(DIRECTORY_SEPARATOR, '/', $thumbnail_url); 

echo json_encode(['success' => true, 'thumbnail_url' => $thumbnail_url]);


<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Image Upload and Thumbnail</title>
</head>
<body>

    <form id="uploadForm" enctype="multipart/form-data">
        <input type="file" name="image" accept="image/jpeg, image/png, image/gif" required>
        <button type="submit">Upload Image</button>
    </form>

    <div id="response"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const responseDiv = document.getElementById('response');
            responseDiv.innerHTML = '<p>Uploading...</p>';

            try {
                const response = await fetch('upload_handler.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (result.success) {
                    responseDiv.innerHTML = `<p>Thumbnail generated successfully!</p><img src="${result.thumbnail_url}" alt="Thumbnail">`;
                } else {
                    responseDiv.innerHTML = `<p style="color: red;">Error: ${result.message}</p>`;
                }
            } catch (error) {
                console.error('Fetch error:', error);
                responseDiv.innerHTML = `<p style="color: red;">An unexpected error occurred: ${error.message}</p>`;
            }
        });
    </script>

</body>
</html>
?>