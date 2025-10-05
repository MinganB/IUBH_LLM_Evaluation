<?php
// /public/upload_form.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Upload Profile Picture</title>
</head>
<body>
    <form action="/handlers/upload_handler.php" method="post" enctype="multipart/form-data">
        <label for="profile_image">Select profile picture:</label>
        <input type="file" name="profile_image" id="profile_image" accept="image/*" required>
        <input type="submit" value="Upload Image" name="submit">
    </form>
</body>
</html>


<?php
// /classes/ImageProcessor.php
class ImageProcessor 
{
    private $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private $maxFileSize = 5242880;
    private $thumbnailWidth = 150;
    private $thumbnailHeight = 150;
    private $uploadDir = '../uploads/originals/';
    private $thumbnailDir = '../uploads/thumbnails/';

    public function __construct()
    {
        $this->createDirectories();
    }

    private function createDirectories()
    {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!file_exists($this->thumbnailDir)) {
            mkdir($this->thumbnailDir, 0755, true);
        }
    }

    public function validateFile($file)
    {
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            throw new Exception('No file uploaded');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new Exception('File size too large');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new Exception('Invalid file type');
        }

        return true;
    }

    public function processImage($file, $userId)
    {
        $this->validateFile($file);

        $extension = $this->getFileExtension($file['name']);
        $filename = 'profile_' . $userId . '_' . time() . '.' . $extension;
        $thumbnailFilename = 'thumb_' . $filename;

        $originalPath = $this->uploadDir . $filename;
        $thumbnailPath = $this->thumbnailDir . $thumbnailFilename;

        if (!move_uploaded_file($file['tmp_name'], $originalPath)) {
            throw new Exception('Failed to save original image');
        }

        $this->createThumbnail($originalPath, $thumbnailPath);

        return [
            'original' => $filename,
            'thumbnail' => $thumbnailFilename,
            'original_path' => $originalPath,
            'thumbnail_path' => $thumbnailPath
        ];
    }

    private function getFileExtension($filename)
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    private function createThumbnail($sourcePath, $destinationPath)
    {
        $imageInfo = getimagesize($sourcePath);
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];

        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                throw new Exception('Unsupported image type');
        }

        $aspectRatio = $sourceWidth / $sourceHeight;
        
        if ($aspectRatio > 1) {
            $newWidth = $this->thumbnailWidth;
            $newHeight = $this->thumbnailWidth / $aspectRatio;
        } else {
            $newHeight = $this->thumbnailHeight;
            $newWidth = $this->thumbnailHeight * $aspectRatio;
        }

        $thumbnailImage = imagecreatetruecolor($this->thumbnailWidth, $this->thumbnailHeight);

        if ($mimeType === 'image/png') {
            imagealphablending($thumbnailImage, false);
            imagesavealpha($thumbnailImage, true);
            $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
            imagefill($thumbnailImage, 0, 0, $transparent);
        }

        $xOffset = ($this->thumbnailWidth - $newWidth) / 2;
        $yOffset = ($this->thumbnailHeight - $newHeight) / 2;

        imagecopyresampled(
            $thumbnailImage,
            $sourceImage,
            $xOffset,
            $yOffset,
            0,
            0,
            $newWidth,
            $newHeight,
            $sourceWidth,
            $sourceHeight
        );

        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($thumbnailImage, $destinationPath, 90);
                break;
            case 'image/png':
                imagepng($thumbnailImage, $destinationPath);
                break;
            case 'image/gif':
                imagegif($thumbnailImage, $destinationPath);
                break;
        }

        imagedestroy($sourceImage);
        imagedestroy($thumbnailImage);
    }
}
?>


<?php
// /classes/UserProfileManager.php
class UserProfileManager
{
    private $dbHost = 'localhost';
    private $dbName = 'social_media_app';
    private $dbUser = 'root';
    private $dbPass = '';
    private $pdo;

    public function __construct()
    {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->dbHost};dbname={$this->dbName}",
                $this->dbUser,
                $this->dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public function updateProfilePicture($userId, $originalFilename, $thumbnailFilename)
    {
        $sql = "UPDATE users SET profile_image = :original, profile_thumbnail = :thumbnail, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            ':original' => $originalFilename,
            ':thumbnail' => $thumbnailFilename,
            ':user_id' => $userId
        ]);
    }

    public function getUserById($userId)
    {
        $sql = "SELECT id, username, email, profile_image, profile_thumbnail FROM users WHERE id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>


<?php
// /handlers/upload_handler.php
session_start();

require_once '../classes/ImageProcessor.php';
require_once '../classes/UserProfileManager.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('User not authenticated');
    }

    if (!isset($_FILES['profile_image'])) {
        throw new Exception('No file uploaded');
    }

    $userId = $_SESSION['user_id'];
    $imageProcessor = new ImageProcessor();
    $userManager = new UserProfileManager();

    $result = $imageProcessor->processImage($_FILES['profile_image'], $userId);

    $updateSuccess = $userManager->updateProfilePicture(
        $userId,
        $result['original'],
        $result['thumbnail']
    );

    if (!$updateSuccess) {
        throw new Exception('Failed to update user profile');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile picture uploaded successfully',
        'data' => [
            'original' => $result['original'],
            'thumbnail' => $result['thumbnail']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>


<?php
// /public/profile_display.php
session_start();
require_once '../classes/UserProfileManager.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    $userManager = new UserProfileManager();
    $user = $userManager->getUserById($_SESSION['user_id']);
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>User Profile</title>
</head>
<body>
    <h1>User Profile</h1>
    
    <?php if (isset($error)): ?>
        <div>Error: <?php echo htmlspecialchars($error); ?></div>
    <?php elseif ($user): ?>
        <div>
            <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
            
            <?php if ($user['profile_thumbnail']): ?>
                <div>
                    <img src="../uploads/thumbnails/<?php echo htmlspecialchars($user['profile_thumbnail']); ?>" alt="Profile Picture">
                </div>
            <?php endif; ?>
            
            <div>
                <a href="upload_form.php">Upload New Profile Picture</a>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>
?>