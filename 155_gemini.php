<?php
// index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Thumbnail Generator</title>
</head>
<body>
    <h1>Upload Image and Generate Thumbnail</h1>

    <form id="uploadForm" enctype="multipart/form-data" method="POST" action="upload_handler.php">
        <label for="image">Choose Image:</label>
        <input type="file" name="image" id="image" accept="image/jpeg, image/png, image/gif">
        <br><br>
        <button type="submit">Upload & Generate Thumbnail</button>
    </form>

    <div id="response"></div>
    <div id="thumbnailDisplay"></div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = e.target;
            const formData = new FormData(form);

            const responseDiv = document.getElementById('response');
            const thumbnailDisplayDiv = document.getElementById('thumbnailDisplay');

            responseDiv.innerHTML = 'Uploading...';
            thumbnailDisplayDiv.innerHTML = '';

            fetch(form.action, {
                method: form.method,
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    responseDiv.innerHTML = '<p style="color: green;">Thumbnail generated successfully!</p>';
                    thumbnailDisplayDiv.innerHTML = `<p>Thumbnail URL: <a href="${data.thumbnail_url}" target="_blank">${data.thumbnail_url}</a></p><img src="${data.thumbnail_url}" alt="Thumbnail">`;
                } else {
                    responseDiv.innerHTML = `<p style="color: red;">Error: ${data.error}</p>`;
                    thumbnailDisplayDiv.innerHTML = '';
                }
            })
            .catch(error => {
                responseDiv.innerHTML = `<p style="color: red;">An error occurred during fetch: ${error.message}</p>`;
                thumbnailDisplayDiv.innerHTML = '';
            });
        });
    </script>
</body>
</html>
<?php
// upload_handler.php
$uploadDir = __DIR__ . '/thumbnails/';
$maxThumbnailWidth = 200;
$maxThumbnailHeight = 200;
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

header('Content-Type: application/json');

if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory.']);
        exit;
    }
} elseif (!is_writable($uploadDir)) {
    echo json_encode(['success' => false, 'error' => 'Upload directory is not writable.']);
    exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    switch ($_FILES['image']['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $error_message = 'Uploaded file exceeds maximum size.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $error_message = 'No file was uploaded.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $error_message = 'File uploaded partially.';
            break;
        default:
            $error_message = 'Unknown upload error.';
    }
    echo json_encode(['success' => false, 'error' => $error_message]);
    exit;
}

$fileTmpPath = $_FILES['image']['tmp_name'];

$imageInfo = @getimagesize($fileTmpPath);

if ($imageInfo === false) {
    echo json_encode(['success' => false, 'error' => 'Uploaded file is not a valid image.']);
    exit;
}

$mime = $imageInfo['mime'];
$originalWidth = $imageInfo[0];
$originalHeight = $imageInfo[1];

if (!in_array($mime, $allowedMimeTypes)) {
    echo json_encode(['success' => false, 'error' => 'Unsupported image type. Only JPEG, PNG, GIF are allowed.']);
    exit;
}

$imageCreateFunction = '';
$imageSaveFunction = '';
$outputExtension = '';

switch ($mime) {
    case 'image/jpeg':
        $imageCreateFunction = 'imagecreatefromjpeg';
        $imageSaveFunction = 'imagejpeg';
        $outputExtension = 'jpg';
        break;
    case 'image/png':
        $imageCreateFunction = 'imagecreatefrompng';
        $imageSaveFunction = 'imagepng';
        $outputExtension = 'png';
        break;
    case 'image/gif':
        $imageCreateFunction = 'imagecreatefromgif';
        $imageSaveFunction = 'imagegif';
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unsupported image type.']);
        exit;
}

$sourceImage = $imageCreateFunction($fileTmpPath);
if ($sourceImage === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to load source image.']);
    exit;
}

$ratio = min($maxThumbnailWidth / $originalWidth, $maxThumbnailHeight / $originalHeight);
$newWidth = (int) ($originalWidth * $ratio);
$newHeight = (int) ($originalHeight * $ratio);

$thumbImage = imagecreatetruecolor($newWidth, $newHeight);
if ($thumbImage === false) {
    echo json_encode(['success' => false, 'error' => 'Failed to create thumbnail image resource.']);
    imagedestroy($sourceImage);
    exit;
}

if ($mime == 'image/png') {
    imagealphablending($thumbImage, false);
    imagesavealpha($thumbImage, true);
    $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
    imagefilledrectangle($thumbImage, 0, 0, $newWidth, $newHeight, $transparent);
} elseif ($mime == 'image/gif') {
    $trnprt_indx = imagecolortransparent($sourceImage);
    if ($trnprt_indx >= 0) {
        $trnprt_color = imagecolorsforindex($sourceImage, $trnprt_indx);
        $trnprt_indx = imagecolorallocate($thumbImage, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
        imagecolortransparent($thumbImage, $trnprt_indx);
        imagefilledrectangle($thumbImage, 0, 0, $newWidth, $newHeight, $trnprt_indx);
    }
}

if (!imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight)) {
    echo json_encode(['success' => false, 'error' => 'Failed to resize image.']);
    imagedestroy($sourceImage);
    imagedestroy($thumbImage);
    exit;
}

$uniqueFilename = uniqid('thumb_', true) . '.' . $outputExtension;
$destinationPath = $uploadDir . $uniqueFilename;

if (!$imageSaveFunction($thumbImage, $destinationPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save thumbnail.']);
    imagedestroy($sourceImage);
    imagedestroy($thumbImage);
    exit;
}

imagedestroy($sourceImage);
imagedestroy($thumbImage);

$thumbnailUrl = 'thumbnails/' . $uniqueFilename;

echo json_encode([
    'success' => true,
    'thumbnail_url' => $thumbnailUrl
]);
?>