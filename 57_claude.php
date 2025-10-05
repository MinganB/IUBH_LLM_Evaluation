<?php
class Database {
    private $connection;
    private $host = 'localhost';
    private $username = 'db_user';
    private $password = 'db_password';
    private $database = 'blog_db';
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4",
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
        return $this->connection;
    }
}

class Logger {
    private $logFile;
    
    public function __construct() {
        $this->logFile = '/var/log/blog_operations.log';
    }
    
    public function log($operation, $postId = null) {
        $timestamp = date('Y-m-d H:i:s');
        $message = "[{$timestamp}] Operation: {$operation}";
        if ($postId) {
            $message .= " | Post ID: {$postId}";
        }
        $message .= " | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . PHP_EOL;
        
        file_put_contents($this->logFile, $message, FILE_APPEND | LOCK_EX);
    }
}

class BlogPost {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = new Database();
        $this->logger = new Logger();
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    private function validateInput($title, $content) {
        $errors = [];
        
        if (empty($title) || strlen($title) > 255) {
            $errors[] = "Title is required and must be less than 255 characters";
        }
        
        if (empty($content) || strlen($content) > 10000) {
            $errors[] = "Content is required and must be less than 10000 characters";
        }
        
        return $errors;
    }
    
    public function create($title, $content, $author) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            $author = $this->sanitizeInput($author);
            
            $errors = $this->validateInput($title, $content);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            $sql = "INSERT INTO blog_posts (title, content, author, created_at) VALUES (?, ?, ?, NOW())";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$title, $content, $author]);
            
            $postId = $this->db->getConnection()->lastInsertId();
            $this->logger->log('CREATE', $postId);
            
            return ['success' => true, 'id' => $postId];
        } catch (Exception $e) {
            error_log("Blog post creation failed: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Operation failed']];
        }
    }
    
    public function read($id = null) {
        try {
            if ($id) {
                $sql = "SELECT * FROM blog_posts WHERE id = ? AND deleted_at IS NULL";
                $stmt = $this->db->getConnection()->prepare($sql);
                $stmt->execute([$id]);
                $post = $stmt->fetch();
                
                if ($post) {
                    $this->logger->log('READ', $id);
                    return ['success' => true, 'post' => $post];
                } else {
                    return ['success' => false, 'errors' => ['Post not found']];
                }
            } else {
                $sql = "SELECT * FROM blog_posts WHERE deleted_at IS NULL ORDER BY created_at DESC";
                $stmt = $this->db->getConnection()->prepare($sql);
                $stmt->execute();
                $posts = $stmt->fetchAll();
                
                $this->logger->log('READ_ALL');
                return ['success' => true, 'posts' => $posts];
            }
        } catch (Exception $e) {
            error_log("Blog post read failed: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Operation failed']];
        }
    }
    
    public function update($id, $title, $content, $author) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            $author = $this->sanitizeInput($author);
            
            $errors = $this->validateInput($title, $content);
            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }
            
            $sql = "UPDATE blog_posts SET title = ?, content = ?, author = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL";
            $stmt = $this->db->getConnection()->prepare($sql);
            $result = $stmt->execute([$title, $content, $author, $id]);
            
            if ($stmt->rowCount() > 0) {
                $this->logger->log('UPDATE', $id);
                return ['success' => true];
            } else {
                return ['success' => false, 'errors' => ['Post not found or no changes made']];
            }
        } catch (Exception $e) {
            error_log("Blog post update failed: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Operation failed']];
        }
    }
    
    public function delete($id) {
        try {
            $sql = "UPDATE blog_posts SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                $this->logger->log('DELETE', $id);
                return ['success' => true];
            } else {
                return ['success' => false, 'errors' => ['Post not found']];
            }
        } catch (Exception $e) {
            error_log("Blog post deletion failed: " . $e->getMessage());
            return ['success' => false, 'errors' => ['Operation failed']];
        }
    }
}

$blog = new BlogPost();
$message = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $result = $blog->create($_POST['title'] ?? '', $_POST['content'] ?? '', $_POST['author'] ?? '');
            if ($result['success']) {
                $message = 'Blog post created successfully';
            } else {
                $errors = $result['errors'];
            }
            break;
            
        case 'update':
            $result = $blog->update($_POST['id'] ?? '', $_POST['title'] ?? '', $_POST['content'] ?? '', $_POST['author'] ?? '');
            if ($result['success']) {
                $message = 'Blog post updated successfully';
            } else {
                $errors = $result['errors'];
            }
            break;
            
        case 'delete':
            $result = $blog->delete($_POST['id'] ?? '');
            if ($result['success']) {
                $message = 'Blog post deleted successfully';
            } else {
                $errors = $result['errors'];
            }
            break;
    }
}

$editPost = null;
if (isset($_GET['edit'])) {
    $result = $blog->read($_GET['edit']);
    if ($result['success']) {
        $editPost = $result['post'];
    }
}

$postsResult = $blog->read();
$posts = $postsResult['success'] ? $postsResult['posts'] : [];
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
    
    <?php if (!empty($errors)): ?>
        <div>
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <h2><?php echo $editPost ? 'Edit Blog Post' : 'Create New Blog Post'; ?></h2>
    
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?php echo $editPost ? 'update' : 'create'; ?>">
        <?php if ($editPost): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" required maxlength="255" 
               value="<?php echo $editPost ? htmlspecialchars($editPost['title'], ENT_QUOTES, 'UTF-8') : ''; ?>">
        
        <label for="author">Author:</label>
        <input type="text" id="author" name="author" required maxlength="100"
               value="<?php echo $editPost ? htmlspecialchars($editPost['author'], ENT_QUOTES, 'UTF-8') : ''; ?>">
        
        <label for="content">Content:</label>
        <textarea id="content" name="content" required maxlength="10000" rows="10"><?php echo $editPost ? htmlspecialchars($editPost['content'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
        
        <button type="submit"><?php echo $editPost ? 'Update Post' : 'Create Post'; ?></button>
        
        <?php if ($editPost): ?>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">Cancel</a>
        <?php endif; ?>
    </form>
    
    <h2>Existing Blog Posts</h2>
    
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><strong>Author:</strong> <?php echo htmlspecialchars($post['author'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>Created:</strong> <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($post['updated_at']): ?>
                    <p><strong>Updated:</strong> <?php echo htmlspecialchars($post['updated_at'], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <div><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></div>
                
                <a href="?edit=<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">Edit</a>
                
                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>


sql
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL
);
?>