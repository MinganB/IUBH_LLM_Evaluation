<?php
// config.php
class DatabaseConfig {
    private static $host = 'localhost';
    private static $dbname = 'blog_db';
    private static $username = 'db_user';
    private static $password = 'db_pass';
    
    public static function getConnection() {
        try {
            $pdo = new PDO("mysql:host=" . self::$host . ";dbname=" . self::$dbname, self::$username, self::$password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            return null;
        }
    }
}

class SecurityLogger {
    private static $logFile = '/var/log/blog_operations.log';
    
    public static function log($operation, $postId = null, $additional = '') {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $logEntry = "[{$timestamp}] IP: {$ip} | Operation: {$operation}";
        
        if ($postId) {
            $logEntry .= " | Post ID: {$postId}";
        }
        
        if ($additional) {
            $logEntry .= " | Additional: {$additional}";
        }
        
        $logEntry .= " | User Agent: {$userAgent}" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

class InputValidator {
    public static function sanitizeString($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateTitle($title) {
        $title = self::sanitizeString($title);
        return !empty($title) && strlen($title) <= 255;
    }
    
    public static function validateContent($content) {
        $content = self::sanitizeString($content);
        return !empty($content) && strlen($content) <= 65535;
    }
    
    public static function validateId($id) {
        return filter_var($id, FILTER_VALIDATE_INT, array("options" => array("min_range" => 1)));
    }
}

class BlogPost {
    private $pdo;
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getConnection();
        if (!$this->pdo) {
            throw new Exception("Database connection failed");
        }
    }
    
    public function create($title, $content) {
        try {
            if (!InputValidator::validateTitle($title) || !InputValidator::validateContent($content)) {
                SecurityLogger::log('CREATE_FAILED', null, 'Invalid input data');
                return false;
            }
            
            $sanitizedTitle = InputValidator::sanitizeString($title);
            $sanitizedContent = InputValidator::sanitizeString($content);
            
            $sql = "INSERT INTO blog_posts (title, content, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$sanitizedTitle, $sanitizedContent]);
            
            if ($result) {
                $postId = $this->pdo->lastInsertId();
                SecurityLogger::log('CREATE_SUCCESS', $postId);
                return $postId;
            }
            
            SecurityLogger::log('CREATE_FAILED', null, 'Database execution failed');
            return false;
        } catch (PDOException $e) {
            error_log("Blog post creation failed: " . $e->getMessage());
            SecurityLogger::log('CREATE_ERROR', null, 'Database error');
            return false;
        }
    }
    
    public function read($id = null) {
        try {
            if ($id !== null) {
                if (!InputValidator::validateId($id)) {
                    SecurityLogger::log('READ_FAILED', $id, 'Invalid ID');
                    return false;
                }
                
                $sql = "SELECT id, title, content, created_at, updated_at FROM blog_posts WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    SecurityLogger::log('READ_SUCCESS', $id);
                    return $result;
                } else {
                    SecurityLogger::log('READ_NOT_FOUND', $id);
                    return false;
                }
            } else {
                $sql = "SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                SecurityLogger::log('READ_ALL_SUCCESS', null, 'Count: ' . count($result));
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Blog post read failed: " . $e->getMessage());
            SecurityLogger::log('READ_ERROR', $id);
            return false;
        }
    }
    
    public function update($id, $title, $content) {
        try {
            if (!InputValidator::validateId($id) || !InputValidator::validateTitle($title) || !InputValidator::validateContent($content)) {
                SecurityLogger::log('UPDATE_FAILED', $id, 'Invalid input data');
                return false;
            }
            
            $sanitizedTitle = InputValidator::sanitizeString($title);
            $sanitizedContent = InputValidator::sanitizeString($content);
            
            $sql = "UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$sanitizedTitle, $sanitizedContent, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                SecurityLogger::log('UPDATE_SUCCESS', $id);
                return true;
            }
            
            SecurityLogger::log('UPDATE_FAILED', $id, 'No rows affected');
            return false;
        } catch (PDOException $e) {
            error_log("Blog post update failed: " . $e->getMessage());
            SecurityLogger::log('UPDATE_ERROR', $id, 'Database error');
            return false;
        }
    }
    
    public function delete($id) {
        try {
            if (!InputValidator::validateId($id)) {
                SecurityLogger::log('DELETE_FAILED', $id, 'Invalid ID');
                return false;
            }
            
            $sql = "DELETE FROM blog_posts WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                SecurityLogger::log('DELETE_SUCCESS', $id);
                return true;
            }
            
            SecurityLogger::log('DELETE_FAILED', $id, 'No rows affected');
            return false;
        } catch (PDOException $e) {
            error_log("Blog post deletion failed: " . $e->getMessage());
            SecurityLogger::log('DELETE_ERROR', $id, 'Database error');
            return false;
        }
    }
}
?>


<?php
// blog_post_handler.php
require_once 'config.php';

session_start();

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

$blogPost = new BlogPost();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $message = 'Invalid request';
        $messageType = 'error';
        SecurityLogger::log('CSRF_VALIDATION_FAILED');
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';
                
                $postId = $blogPost->create($title, $content);
                if ($postId) {
                    $message = 'Blog post created successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to create blog post';
                    $messageType = 'error';
                }
                break;
                
            case 'update':
                $id = $_POST['id'] ?? '';
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';
                
                if ($blogPost->update($id, $title, $content)) {
                    $message = 'Blog post updated successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update blog post';
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? '';
                
                if ($blogPost->delete($id)) {
                    $message = 'Blog post deleted successfully';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete blog post';
                    $messageType = 'error';
                }
                break;
                
            default:
                $message = 'Invalid action';
                $messageType = 'error';
        }
    }
}

$posts = $blogPost->read();
$editPost = null;

if (isset($_GET['edit']) && InputValidator::validateId($_GET['edit'])) {
    $editPost = $blogPost->read($_GET['edit']);
}

$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Manager</title>
</head>
<body>
    <div class="container">
        <h1>Blog Post Manager</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="blog_post_handler.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <?php if ($editPost): ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id']); ?>">
                <h2>Edit Blog Post</h2>
            <?php else: ?>
                <input type="hidden" name="action" value="create">
                <h2>Create New Blog Post</h2>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="title">Title:</label>
                <input type="text" 
                       id="title" 
                       name="title" 
                       maxlength="255" 
                       required 
                       value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="content">Content:</label>
                <textarea id="content" 
                          name="content" 
                          rows="10" 
                          maxlength="65535" 
                          required><?php echo $editPost ? htmlspecialchars($editPost['content']) : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit"><?php echo $editPost ? 'Update' : 'Create'; ?> Blog Post</button>
                <?php if ($editPost): ?>
                    <a href="blog_post_handler.php">Cancel</a>
                <?php endif; ?>
            </div>
        </form>

        <h2>Existing Blog Posts</h2>
        
        <?php if (is_array($posts) && count($posts) > 0): ?>
            <?php foreach ($posts as $post): ?>
                <div class="post">
                    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                    <div class="post-meta">
                        Created: <?php echo htmlspecialchars($post['created_at']); ?>
                        <?php if ($post['updated_at'] !== $post['created_at']): ?>
                            | Updated: <?php echo htmlspecialchars($post['updated_at']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="post-content">
                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                    </div>
                    <div class="post-actions">
                        <a href="?edit=<?php echo htmlspecialchars($post['id']); ?>">Edit</a>
                        
                        <form method="POST" action="blog_post_handler.php" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                            <button type="submit" onclick="return confirm('Are you sure you want to delete this post?')">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No blog posts found.</p>
        <?php endif; ?>
    </div>
</body>
</html>


sql
-- database_schema.sql
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
?>