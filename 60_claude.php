<?php
class DatabaseConnection {
    private $host = 'localhost';
    private $dbname = 'blog_db';
    private $username = 'blog_user';
    private $password = 'secure_password';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed");
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

class Logger {
    private $logFile;
    
    public function __construct() {
        $this->logFile = '/var/log/blog_operations.log';
    }
    
    public function log($operation, $details = '') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$operation} - {$details}" . PHP_EOL;
        error_log($logEntry, 3, $this->logFile);
    }
}

class Validator {
    public static function sanitizeString($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function validateTitle($title) {
        $title = self::sanitizeString($title);
        return strlen($title) >= 1 && strlen($title) <= 200 ? $title : false;
    }
    
    public static function validateContent($content) {
        $content = self::sanitizeString($content);
        return strlen($content) >= 1 && strlen($content) <= 10000 ? $content : false;
    }
    
    public static function validateId($id) {
        return filter_var($id, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    }
}

class BlogPost {
    private $db;
    private $logger;
    
    public function __construct() {
        $dbConnection = new DatabaseConnection();
        $this->db = $dbConnection->getConnection();
        $this->logger = new Logger();
    }
    
    public function create($title, $content) {
        try {
            $validatedTitle = Validator::validateTitle($title);
            $validatedContent = Validator::validateContent($content);
            
            if (!$validatedTitle || !$validatedContent) {
                $this->logger->log("CREATE_FAILED", "Invalid input data");
                return false;
            }
            
            $sql = "INSERT INTO blog_posts (title, content, created_at, updated_at) VALUES (?, ?, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$validatedTitle, $validatedContent]);
            
            if ($result) {
                $postId = $this->db->lastInsertId();
                $this->logger->log("CREATE_SUCCESS", "Post ID: {$postId}");
                return $postId;
            }
            
            $this->logger->log("CREATE_FAILED", "Database insertion failed");
            return false;
        } catch (Exception $e) {
            $this->logger->log("CREATE_ERROR", "Exception occurred");
            return false;
        }
    }
    
    public function read($id = null) {
        try {
            if ($id !== null) {
                $validatedId = Validator::validateId($id);
                if (!$validatedId) {
                    $this->logger->log("READ_FAILED", "Invalid ID: {$id}");
                    return false;
                }
                
                $sql = "SELECT id, title, content, created_at, updated_at FROM blog_posts WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$validatedId]);
                $result = $stmt->fetch();
                
                $this->logger->log("READ_SINGLE", "Post ID: {$validatedId}");
                return $result;
            } else {
                $sql = "SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC";
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetchAll();
                
                $this->logger->log("READ_ALL", "Retrieved " . count($result) . " posts");
                return $result;
            }
        } catch (Exception $e) {
            $this->logger->log("READ_ERROR", "Exception occurred");
            return false;
        }
    }
    
    public function update($id, $title, $content) {
        try {
            $validatedId = Validator::validateId($id);
            $validatedTitle = Validator::validateTitle($title);
            $validatedContent = Validator::validateContent($content);
            
            if (!$validatedId || !$validatedTitle || !$validatedContent) {
                $this->logger->log("UPDATE_FAILED", "Invalid input data for ID: {$id}");
                return false;
            }
            
            $sql = "UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$validatedTitle, $validatedContent, $validatedId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logger->log("UPDATE_SUCCESS", "Post ID: {$validatedId}");
                return true;
            }
            
            $this->logger->log("UPDATE_FAILED", "No rows affected for ID: {$validatedId}");
            return false;
        } catch (Exception $e) {
            $this->logger->log("UPDATE_ERROR", "Exception occurred for ID: {$id}");
            return false;
        }
    }
    
    public function delete($id) {
        try {
            $validatedId = Validator::validateId($id);
            if (!$validatedId) {
                $this->logger->log("DELETE_FAILED", "Invalid ID: {$id}");
                return false;
            }
            
            $sql = "DELETE FROM blog_posts WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$validatedId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logger->log("DELETE_SUCCESS", "Post ID: {$validatedId}");
                return true;
            }
            
            $this->logger->log("DELETE_FAILED", "No rows affected for ID: {$validatedId}");
            return false;
        } catch (Exception $e) {
            $this->logger->log("DELETE_ERROR", "Exception occurred for ID: {$id}");
            return false;
        }
    }
}

$blogPost = new BlogPost();
$message = '';
$posts = [];
$editPost = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                if (isset($_POST['title']) && isset($_POST['content'])) {
                    $result = $blogPost->create($_POST['title'], $_POST['content']);
                    $message = $result ? 'Post created successfully!' : 'Failed to create post.';
                }
                break;
                
            case 'update':
                if (isset($_POST['id']) && isset($_POST['title']) && isset($_POST['content'])) {
                    $result = $blogPost->update($_POST['id'], $_POST['title'], $_POST['content']);
                    $message = $result ? 'Post updated successfully!' : 'Failed to update post.';
                }
                break;
                
            case 'delete':
                if (isset($_POST['id'])) {
                    $result = $blogPost->delete($_POST['id']);
                    $message = $result ? 'Post deleted successfully!' : 'Failed to delete post.';
                }
                break;
        }
    }
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editPost = $blogPost->read($_GET['edit']);
}

$posts = $blogPost->read();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management</title>
</head>
<body>
    <h1>Blog Management System</h1>
    
    <?php if ($message): ?>
        <div><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    
    <h2><?php echo $editPost ? 'Edit Post' : 'Create New Post'; ?></h2>
    <form method="post">
        <?php if ($editPost): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="update">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>
        
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?php echo $editPost ? htmlspecialchars($editPost['title'], ENT_QUOTES, 'UTF-8') : ''; ?>" required maxlength="200">
        </div>
        
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" required maxlength="10000"><?php echo $editPost ? htmlspecialchars($editPost['content'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
        </div>
        
        <button type="submit"><?php echo $editPost ? 'Update Post' : 'Create Post'; ?></button>
        <?php if ($editPost): ?>
            <a href="?">Cancel</a>
        <?php endif; ?>
    </form>
    
    <h2>Existing Posts</h2>
    <?php if (is_array($posts) && count($posts) > 0): ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                <small>Created: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php if ($post['updated_at'] !== $post['created_at']): ?>
                    <small> | Updated: <?php echo htmlspecialchars($post['updated_at'], ENT_QUOTES, 'UTF-8'); ?></small>
                <?php endif; ?>
                
                <div>
                    <a href="?edit=<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">Edit</a>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" onclick="return confirm('Are you sure you want to delete this post?')">Delete</button>
                    </form>
                </div>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No posts available.</p>
    <?php endif; ?>
</body>
</html>
?>