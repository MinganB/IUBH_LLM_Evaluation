<?php

$uploadDir = 'uploads/';
$thumbnailDir = 'thumbnails/';
$maxFileSize = 5 * 1024 * 1024;
$thumbnailMaxWidth = 150;
$thumbnailMaxHeight = 150;

$allowedMimeTypes = [
    'image/jpeg',
    'image/png',
    'image/gif'
];

$message = '';
$uploadedImagePath = '';
$thumbnailPath = '';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
if (!is_dir($thumbnailDir)) {
    mkdir($thumbnailDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'Upload error: ' . $file['error'];
    } elseif ($file['size'] == 0) {
        $message = 'No file was uploaded or file is empty.';
    } else {
        if ($file['size'] > $maxFileSize) {
            $message = 'File is too large. Max size is ' . ($maxFileSize / (1024 * 1024)) . ' MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $allowedMimeTypes)) {
                $message = 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.';
            } else {
                $imageInfo = getimagesize($file['tmp_name']);
                if ($imageInfo === false) {
                    $message = 'File is not a valid image.';
                } else {
                    $originalFileName = pathinfo($file['name'], PATHINFO_FILENAME);
                    $fileExtension = image_type_to_extension($imageInfo[2], true);

                    $uniqueFileName = uniqid($originalFileName . '_', true) . $fileExtension;
                    $destinationPath = $uploadDir . $uniqueFileName;
                    $thumbnailDestinationPath = $thumbnailDir . 'thumb_' . $uniqueFileName;

                    if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
                        $uploadedImagePath = $destinationPath;

                        list($origWidth, $origHeight) = $imageInfo;

                        $ratio = $origWidth / $origHeight;
                        if ($thumbnailMaxWidth / $thumbnailMaxHeight > $ratio) {
                            $newWidth = (int)($thumbnailMaxHeight * $ratio);
                            $newHeight = $thumbnailMaxHeight;
                        } else {
                            $newHeight = (int)($thumbnailMaxWidth / $ratio);
                            $newWidth = $thumbnailMaxWidth;
                        }

                        $thumb = imagecreatetruecolor($newWidth, $newHeight);

                        $sourceImage = null;
                        switch ($mimeType) {
                            case 'image/jpeg':
                                $sourceImage = imagecreatefromjpeg($destinationPath);
                                break;
                            case 'image/png':
                                $sourceImage = imagecreatefrompng($destinationPath);
                                imagealphablending($thumb, false);
                                imagesavealpha($thumb, true);
                                break;
                            case 'image/gif':
                                $sourceImage = imagecreatefromgif($destinationPath);
                                break;
                        }

                        if ($sourceImage) {
                            imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

                            switch ($mimeType) {
                                case 'image/jpeg':
                                    imagejpeg($thumb, $thumbnailDestinationPath, 90);
                                    break;
                                case 'image/png':
                                    imagepng($thumb, $thumbnailDestinationPath, 9);
                                    break;
                                case 'image/gif':
                                    imagegif($thumb, $thumbnailDestinationPath);
                                    break;
                            }

                            imagedestroy($sourceImage);
                            imagedestroy($thumb);
                            $thumbnailPath = $thumbnailDestinationPath;
                            $message = 'Image uploaded and thumbnail generated successfully!';
                        } else {
                            $message = 'Failed to create image resource from uploaded file.';
                            unlink($destinationPath);
                        }
                    } else {
                        $message = 'Failed to move uploaded file.';
                    }
                }
            }
        }
    }
}

echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>Image Upload and Thumbnail Generator</title>';
echo '</head>';
echo '<body>';
echo '<h1>Upload Image and Generate Thumbnail</h1>';

if ($message) {
    echo '<p>' . htmlspecialchars($message) . '</p>';
}

echo '<form action="" method="post" enctype="multipart/form-data">';
echo '<label for="image">Choose Image:</label><br>';
echo '<input type="file" name="image" id="image" accept="image/jpeg, image/png, image/gif"><br><br>';
echo '<input type="submit" value="Upload Image">';
echo '</form>';

if ($uploadedImagePath && $thumbnailPath) {
    echo '<h2>Uploaded Image:</h2>';
    echo '<img src="' . htmlspecialchars($uploadedImagePath) . '" alt="Uploaded Image"><br>';
    echo '<span>Path: ' . htmlspecialchars($uploadedImagePath) . '</span>';

    echo '<h2>Thumbnail:</h2>';
    echo '<img src="' . htmlspecialchars($thumbnailPath) . '" alt="Thumbnail"><br>';
    echo '<span>Path: ' . htmlspecialchars($thumbnailPath) . '</span>';
}

echo '</body>';
echo '</html>';

?>