<?php
session_start();

class DatabaseConnection {
    private $host = 'localhost';
    private $dbname = 'blog_db';
    private $username = 'blog_user';
    private $password = 'secure_password';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed");
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

class BlogPost {
    private $db;
    private $logFile = '/var/log/blog_operations.log';
    
    public function __construct() {
        $dbConnection = new DatabaseConnection();
        $this->db = $dbConnection->getConnection();
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    private function validateTitle($title) {
        return !empty($title) && strlen($title) <= 255;
    }
    
    private function validateContent($content) {
        return !empty($content) && strlen($content) <= 10000;
    }
    
    private function logOperation($operation, $postId = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] Operation: {$operation}";
        if ($postId) {
            $logMessage .= ", Post ID: {$postId}";
        }
        $logMessage .= "\n";
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public function createPost($title, $content) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            
            if (!$this->validateTitle($title) || !$this->validateContent($content)) {
                return false;
            }
            
            $stmt = $this->db->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$title, $content]);
            
            if ($result) {
                $postId = $this->db->lastInsertId();
                $this->logOperation("CREATE", $postId);
                return $postId;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Create post error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getAllPosts() {
        try {
            $stmt = $this->db->prepare("SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll();
            
            $this->logOperation("READ_ALL");
            return $posts;
        } catch (PDOException $e) {
            error_log("Get all posts error: " . $e->getMessage());
            return [];
        }
    }
    
    public function getPostById($id) {
        try {
            $stmt = $this->db->prepare("SELECT id, title, content, created_at, updated_at FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            
            if ($post) {
                $this->logOperation("READ", $id);
            }
            
            return $post;
        } catch (PDOException $e) {
            error_log("Get post by ID error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updatePost($id, $title, $content) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            
            if (!$this->validateTitle($title) || !$this->validateContent($content)) {
                return false;
            }
            
            $stmt = $this->db->prepare("UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$title, $content, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation("UPDATE", $id);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Update post error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deletePost($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM blog_posts WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation("DELETE", $id);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Delete post error: " . $e->getMessage());
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
                    $result = $blogPost->createPost($_POST['title'], $_POST['content']);
                    $message = $result ? 'Post created successfully!' : 'Failed to create post.';
                }
                break;
                
            case 'update':
                if (isset($_POST['id']) && isset($_POST['title']) && isset($_POST['content'])) {
                    $result = $blogPost->updatePost($_POST['id'], $_POST['title'], $_POST['content']);
                    $message = $result ? 'Post updated successfully!' : 'Failed to update post.';
                }
                break;
                
            case 'delete':
                if (isset($_POST['id'])) {
                    $result = $blogPost->deletePost($_POST['id']);
                    $message = $result ? 'Post deleted successfully!' : 'Failed to delete post.';
                }
                break;
        }
    }
}

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editPost = $blogPost->getPostById($_GET['edit']);
}

$posts = $blogPost->getAllPosts();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management</title>
</head>
<body>
    <h1>Blog Management</h1>
    
    <?php if ($message): ?>
        <div><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    
    <h2><?php echo $editPost ? 'Edit Post' : 'Create New Post'; ?></h2>
    <form action="blog_post_handler.php" method="POST">
        <?php if ($editPost): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>
        
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" maxlength="255" required 
                   value="<?php echo $editPost ? htmlspecialchars($editPost['title'], ENT_QUOTES, 'UTF-8') : ''; ?>">
        </div>
        
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" maxlength="10000" required><?php echo $editPost ? htmlspecialchars($editPost['content'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
        </div>
        
        <div>
            <button type="submit"><?php echo $editPost ? 'Update Post' : 'Create Post'; ?></button>
            <?php if ($editPost): ?>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
    
    <h2>Existing Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></p>
                <small>Created: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($post['updated_at']): ?>
                        | Updated: <?php echo htmlspecialchars($post['updated_at'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </small>
                
                <div>
                    <a href="?edit=<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">Edit</a>
                    
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" onclick="return confirm('Are you sure you want to delete this post?')">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>


<?php
session_start();

class DatabaseConnection {
    private $host = 'localhost';
    private $dbname = 'blog_db';
    private $username = 'blog_user';
    private $password = 'secure_password';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            header("Location: index.php?error=1");
            exit;
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

class BlogPost {
    private $db;
    private $logFile = '/var/log/blog_operations.log';
    
    public function __construct() {
        $dbConnection = new DatabaseConnection();
        $this->db = $dbConnection->getConnection();
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    private function validateTitle($title) {
        return !empty($title) && strlen($title) <= 255;
    }
    
    private function validateContent($content) {
        return !empty($content) && strlen($content) <= 10000;
    }
    
    private function logOperation($operation, $postId = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] Operation: {$operation}";
        if ($postId) {
            $logMessage .= ", Post ID: {$postId}";
        }
        $logMessage .= "\n";
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    public function createPost($title, $content) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            
            if (!$this->validateTitle($title) || !$this->validateContent($content)) {
                return false;
            }
            
            $stmt = $this->db->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$title, $content]);
            
            if ($result) {
                $postId = $this->db->lastInsertId();
                $this->logOperation("CREATE", $postId);
                return $postId;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Create post error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updatePost($id, $title, $content) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            
            if (!$this->validateTitle($title) || !$this->validateContent($content)) {
                return false;
            }
            
            $stmt = $this->db->prepare("UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
            $result = $stmt->execute([$title, $content, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation("UPDATE", $id);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Update post error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deletePost($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM blog_posts WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation("DELETE", $id);
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Delete post error: " . $e->getMessage());
            return false;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$blogPost = new BlogPost();
$success = false;

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
            if (isset($_POST['title']) && isset($_POST['content'])) {
                $result = $blogPost->createPost($_POST['title'], $_POST['content']);
                $success = $result
?>