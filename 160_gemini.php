<?php
// File: public/index.php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Profile Picture</title>
</head>
<body>
    <h1>Upload Your Profile Picture</h1>

    <form id="uploadForm" action="/handlers/upload_handler.php" method="POST" enctype="multipart/form-data">
        <label for="profile_image">Choose Image:</label>
        <input type="file" name="profile_image" id="profile_image" accept="image/jpeg, image/png, image/gif" required>
        <br><br>
        <button type="submit">Upload and Generate Thumbnail</button>
    </form>

    <div id="response" style="margin-top: 20px;"></div>
    <div id="thumbnailDisplay" style="margin-top: 10px;"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const responseDiv = document.getElementById('response');
            const thumbnailDisplayDiv = document.getElementById('thumbnailDisplay');

            responseDiv.innerHTML = 'Uploading...';
            thumbnailDisplayDiv.innerHTML = '';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    responseDiv.innerHTML = '<p style="color: green;">' + result.message + '</p>';
                    if (result.thumbnail_url) {
                        thumbnailDisplayDiv.innerHTML = '<p>Thumbnail Preview:</p><img src="' + result.thumbnail_url + '" alt="Thumbnail">';
                    }
                } else {
                    responseDiv.innerHTML = '<p style="color: red;">Error: ' + result.message + '</p>';
                }
            } catch (error) {
                responseDiv.innerHTML = '<p style="color: red;">Network error: ' + error.message + '</p>';
            }
        });
    </script>
</body>
</html>

<?php
// File: handlers/upload_handler.php

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

$base_dir = dirname(__DIR__);
$thumbnails_dir = $base_dir . '/thumbnails/';
$thumbnail_base_url = '/thumbnails/';

if (!is_dir($thumbnails_dir)) {
    if (!mkdir($thumbnails_dir, 0755, true)) {
        $response['message'] = 'Failed to create thumbnail directory.';
        echo json_encode($response);
        exit;
    }
}

if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
    switch ($_FILES['profile_image']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $response['message'] = 'Uploaded file exceeds maximum size.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $response['message'] = 'File upload was interrupted.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $response['message'] = 'No file was uploaded.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $response['message'] = 'Missing a temporary folder for uploads.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $response['message'] = 'Failed to write file to disk.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $response['message'] = 'A PHP extension stopped the file upload.';
            break;
        default:
            $response['message'] = 'File upload error: ' . ($_FILES['profile_image']['error'] ?? 'Unknown');
            break;
    }
    echo json_encode($response);
    exit;
}

$file = $_FILES['profile_image'];
$image_mime_type = $file['type'];
$image_temp_path = $file['tmp_name'];

$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
if (!in_array($image_mime_type, $allowed_types)) {
    $response['message'] = 'Unsupported file type. Only JPEG, PNG, and GIF are allowed.';
    echo json_encode($response);
    exit;
}

if (!($image_info = getimagesize($image_temp_path))) {
    $response['message'] = 'Uploaded file is not a valid image.';
    echo json_encode($response);
    exit;
}

$original_width = $image_info[0];
$original_height = $image_info[1];

$max_width = 200;
$max_height = 200;

$aspect_ratio = $original_width / $original_height;

if ($original_width > $max_width || $original_height > $max_height) {
    if ($max_width / $max_height > $aspect_ratio) {
        $new_width = (int)($max_height * $aspect_ratio);
        $new_height = $max_height;
    } else {
        $new_width = $max_width;
        $new_height = (int)($max_width / $aspect_ratio);
    }
} else {
    $new_width = $original_width;
    $new_height = $original_height;
}

$source_image = null;
switch ($image_mime_type) {
    case 'image/jpeg':
        $source_image = imagecreatefromjpeg($image_temp_path);
        break;
    case 'image/png':
        $source_image = imagecreatefrompng($image_temp_path);
        break;
    case 'image/gif':
        $source_image = imagecreatefromgif($image_temp_path);
        break;
}

if (!$source_image) {
    $response['message'] = 'Failed to create image resource from uploaded file.';
    echo json_encode($response);
    exit;
}

$thumbnail = imagecreatetruecolor($new_width, $new_height);

if ($image_mime_type == 'image/png') {
    imagealphablending($thumbnail, false);
    imagesavealpha($thumbnail, true);
    $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
    imagefilledrectangle($thumbnail, 0, 0, $new_width, $new_height, $transparent);
} elseif ($image_mime_type == 'image/gif') {
    $transparent_index = imagecolortransparent($source_image);
    if ($transparent_index >= 0) {
        $transparent_color = imagecolorsforindex($source_image, $transparent_index);
        $transparent_index = imagecolorallocate($thumbnail, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
        imagefill($thumbnail, 0, 0, $transparent_index);
        imagecolortransparent($thumbnail, $transparent_index);
    }
}

imagecopyresampled($thumbnail, $source_image, 0, 0, 0, 0, $new_width, $new_height, $original_width, $original_height);

$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$unique_filename = uniqid('thumb_', true) . '.' . $extension;
$thumbnail_save_path = $thumbnails_dir . $unique_filename;

$save_success = false;
switch ($image_mime_type) {
    case 'image/jpeg':
        $save_success = imagejpeg($thumbnail, $thumbnail_save_path, 90);
        break;
    case 'image/png':
        $save_success = imagepng($thumbnail, $thumbnail_save_path);
        break;
    case 'image/gif':
        $save_success = imagegif($thumbnail, $thumbnail_save_path);
        break;
}

imagedestroy($source_image);
imagedestroy($thumbnail);

if ($save_success) {
    $response['success'] = true;
    $response['thumbnail_url'] = $thumbnail_base_url . $unique_filename;
    $response['message'] = 'Thumbnail generated and saved successfully.';
} else {
    $response['message'] = 'Failed to save thumbnail image.';
}

echo json_encode($response);
?>