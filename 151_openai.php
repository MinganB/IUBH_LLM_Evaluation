<?php
class ImageManager {
    private string $originalsDir;
    private string $thumbnailsDir;

    public function __construct() {
        $projectRoot = realpath(__DIR__ . '/..');
        $uploadsBase = $projectRoot . '/public/uploads';
        $this->originalsDir = $uploadsBase . '/originals';
        $this->thumbnailsDir = $uploadsBase . '/thumbnails';
        if (!is_dir($this->originalsDir)) {
            mkdir($this->originalsDir, 0755, true);
        }
        if (!is_dir($this->thumbnailsDir)) {
            mkdir($this->thumbnailsDir, 0755, true);
        }
    }

    public function saveOriginal(array $file): array {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload failed');
        }
        if ($file['size'] <= 0) {
            throw new Exception('Empty file');
        }
        $tmpPath = $file['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {
            throw new Exception('Unsupported image type');
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $ext = $ext ? strtolower($ext) : 'jpg';
        if ($ext === 'jpeg') $ext = 'jpg';
        $filename = uniqid('img_', true) . '.' . $ext;
        $destPath = rtrim($this->originalsDir, '/\\') . '/' . $filename;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new Exception('Failed to save uploaded file');
        }
        $urlPath = '/uploads/originals/' . $filename;
        return ['absolutePath' => $destPath, 'url' => $urlPath];
    }

    public function createThumbnail(string $originalPath, int $thumbWidth = 150, int $thumbHeight = 150): string {
        $info = getimagesize($originalPath);
        if ($info === false) {
            throw new Exception('Invalid image');
        }
        $origW = $info[0];
        $origH = $info[1];
        $type = $info[2];
        switch ($type) {
            case IMAGETYPE_JPEG:
                $src = imagecreatefromjpeg($originalPath);
                break;
            case IMAGETYPE_PNG:
                $src = imagecreatefrompng($originalPath);
                break;
            case IMAGETYPE_GIF:
                $src = imagecreatefromgif($originalPath);
                break;
            default:
                throw new Exception('Unsupported image type');
        }
        $dst = imagecreatetruecolor($thumbWidth, $thumbHeight);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        $ratio = min($thumbWidth / $origW, $thumbHeight / $origH);
        $newW = (int) floor($origW * $ratio);
        $newH = (int) floor($origH * $ratio);
        $dstX = (int) (($thumbWidth - $newW) / 2);
        $dstY = (int) (($thumbHeight - $newH) / 2);
        imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newW, $newH, $origW, $origH);
        $pathInfo = pathinfo($originalPath);
        $baseName = $pathInfo['filename'];
        $thumbFilename = $baseName . '_thumb.jpg';
        $thumbPath = rtrim($this->thumbnailsDir, '/\\') . '/' . $thumbFilename;
        imagejpeg($dst, $thumbPath, 90);
        imagedestroy($src);
        imagedestroy($dst);
        $urlPath = '/uploads/thumbnails/' . $thumbFilename;
        return $urlPath;
    }
}
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?><?php
?>