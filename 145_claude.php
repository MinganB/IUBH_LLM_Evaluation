<?php
<!DOCTYPE html>
<html>
<head>
    <title>Image Upload</title>
</head>
<body>
    <form action="upload_handler.php" method="post" enctype="multipart/form-data">
        <label for="image">Select image:</label>
        <input type="file" name="image" id="image" accept="image/*" required>
        <input type="submit" value="Upload Image" name="submit">
    </form>
</body>
</html>


<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $uploadDir = 'uploads/';
    $thumbnailDir = 'thumbnails/';
    
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    if (!file_exists($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileSize = $_FILES['image']['size'];
        $fileType = $_FILES['image']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
        
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $dest_path = $uploadDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                createThumbnail($dest_path, $thumbnailDir . 'thumb_' . $newFileName, 200, 200);
                echo "File is successfully uploaded and thumbnail created.";
            } else {
                echo "There was an error moving the uploaded file.";
            }
        } else {
            echo "Upload failed. Allowed file types: " . implode(',', $allowedfileExtensions);
        }
    } else {
        echo "There is some error in the file upload. Please check the following error.<br>";
        echo 'Error:' . $_FILES['image']['error'];
    }
}

function createThumbnail($src, $dest, $thumbWidth, $thumbHeight) {
    $imageInfo = getimagesize($src);
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $mime = $imageInfo['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($src);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($src);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($src);
            break;
        default:
            return false;
    }
    
    $ratio = min($thumbWidth / $width, $thumbHeight / $height);
    $newWidth = $width * $ratio;
    $newHeight = $height * $ratio;
    
    $thumbImage = imagecreatetruecolor($newWidth, $newHeight);
    
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);
        $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
        imagefilledrectangle($thumbImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($thumbImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($thumbImage, $dest, 80);
            break;
        case 'image/png':
            imagepng($thumbImage, $dest);
            break;
        case 'image/gif':
            imagegif($thumbImage, $dest);
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($thumbImage);
    
    return true;
}
?>