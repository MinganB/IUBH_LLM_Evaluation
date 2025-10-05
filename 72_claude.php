<?php
// classes/Database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'your_username';
    private $password = 'your_password';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
// classes/Logger.php
class Logger {
    private $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/../logs/blog_operations.log';
        $this->ensureLogDirectory();
    }

    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function log($operation, $details = '') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] Operation: {$operation} - {$details}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
?>


<?php
// classes/BlogPost.php
require_once 'Database.php';
require_once 'Logger.php';

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
        if (empty($title) || empty($content)) {
            return false;
        }
        if (strlen($title) > 255) {
            return false;
        }
        return true;
    }

    public function create($title, $content) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);

            if (!$this->validateInput($title, $content)) {
                $this->logger->log('CREATE_FAILED', 'Invalid input validation');
                return ['success' => false, 'message' => 'Invalid input provided'];
            }

            $sql = "INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$title, $content]);

            $this->logger->log('CREATE_SUCCESS', "Post created with title: {$title}");
            return ['success' => true, 'message' => 'Blog post created successfully'];

        } catch (Exception $e) {
            $this->logger->log('CREATE_ERROR', 'Database error occurred');
            return ['success' => false, 'message' => 'Failed to create blog post'];
        }
    }

    public function read($id = null) {
        try {
            if ($id) {
                $sql = "SELECT id, title, content, created_at FROM blog_posts WHERE id = ?";
                $stmt = $this->db->getConnection()->prepare($sql);
                $stmt->execute([$id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    $this->logger->log('READ_SUCCESS', "Post retrieved with ID: {$id}");
                    return ['success' => true, 'message' => 'Post retrieved successfully', 'data' => $result];
                } else {
                    $this->logger->log('READ_FAILED', "Post not found with ID: {$id}");
                    return ['success' => false, 'message' => 'Post not found'];
                }
            } else {
                $sql = "SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC";
                $stmt = $this->db->getConnection()->prepare($sql);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $this->logger->log('READ_ALL_SUCCESS', 'All posts retrieved');
                return ['success' => true, 'message' => 'Posts retrieved successfully', 'data' => $results];
            }

        } catch (Exception $e) {
            $this->logger->log('READ_ERROR', 'Database error occurred');
            return ['success' => false, 'message' => 'Failed to retrieve blog posts'];
        }
    }

    public function update($id, $title, $content) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);

            if (!$this->validateInput($title, $content)) {
                $this->logger->log('UPDATE_FAILED', "Invalid input validation for ID: {$id}");
                return ['success' => false, 'message' => 'Invalid input provided'];
            }

            $sql = "UPDATE blog_posts SET title = ?, content = ? WHERE id = ?";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$title, $content, $id]);

            if ($stmt->rowCount() > 0) {
                $this->logger->log('UPDATE_SUCCESS', "Post updated with ID: {$id}");
                return ['success' => true, 'message' => 'Blog post updated successfully'];
            } else {
                $this->logger->log('UPDATE_FAILED', "Post not found with ID: {$id}");
                return ['success' => false, 'message' => 'Post not found or no changes made'];
            }

        } catch (Exception $e) {
            $this->logger->log('UPDATE_ERROR', "Database error occurred for ID: {$id}");
            return ['success' => false, 'message' => 'Failed to update blog post'];
        }
    }

    public function delete($id) {
        try {
            $sql = "DELETE FROM blog_posts WHERE id = ?";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                $this->logger->log('DELETE_SUCCESS', "Post deleted with ID: {$id}");
                return ['success' => true, 'message' => 'Blog post deleted successfully'];
            } else {
                $this->logger->log('DELETE_FAILED', "Post not found with ID: {$id}");
                return ['success' => false, 'message' => 'Post not found'];
            }

        } catch (Exception $e) {
            $this->logger->log('DELETE_ERROR', "Database error occurred for ID: {$id}");
            return ['success' => false, 'message' => 'Failed to delete blog post'];
        }
    }
}
?>


<?php
// handlers/blog_post_handler.php
header('Content-Type: application/json');
require_once '../classes/BlogPost.php';

$blogPost = new BlogPost();
$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $response = $blogPost->create($title, $content);
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            if (is_numeric($id)) {
                $response = $blogPost->update($id, $title, $content);
            } else {
                $response = ['success' => false, 'message' => 'Invalid post ID'];
            }
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';
            if (is_numeric($id)) {
                $response = $blogPost->delete($id);
            } else {
                $response = ['success' => false, 'message' => 'Invalid post ID'];
            }
            break;

        case 'read':
            $id = $_POST['id'] ?? null;
            if ($id && is_numeric($id)) {
                $response = $blogPost->read($id);
            } elseif ($id === null) {
                $response = $blogPost->read();
            } else {
                $response = ['success' => false, 'message' => 'Invalid post ID'];
            }
            break;

        default:
            $response = ['success' => false, 'message' => 'Invalid action'];
            break;
    }
}

echo json_encode($response);
?>


<?php
// public/create_post.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Blog Post</title>
</head>
<body>
    <form action="../handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" required maxlength="255">
        
        <label for="content">Content:</label>
        <textarea id="content" name="content" required rows="10"></textarea>
        
        <input type="submit" value="Create Post">
    </form>
</body>
</html>


<?php
// public/view_posts.php
require_once '../classes/BlogPost.php';

$blogPost = new BlogPost();
$result = $blogPost->read();
$posts = $result['success'] ? $result['data'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Posts</title>
</head>
<body>
    <h1>Blog Posts</h1>
    
    <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h2><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                <small>Created: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
                
                <form action="../handlers/blog_post_handler.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="submit" value="Delete" onclick="return confirm('Are you sure?')">
                </form>
                
                <a href="edit_post.php?id=<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">Edit</a>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No blog posts found.</p>
    <?php endif; ?>
    
    <a href="create_post.php">Create New Post</a>
</body>
</html>


<?php
// public/edit_post.php
require_once '../classes/BlogPost.php';

$blogPost = new BlogPost();
$post = null;

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $result = $blogPost->read($_GET['id']);
    if ($result['success']) {
        $post = $result['data'];
    }
}

if (!$post) {
    header('Location: view_posts.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Blog Post</title>
</head>
<body>
    <h1>Edit Blog Post</h1>
    
    <form action="../handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
        
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
        
        <label for="content">Content:</label>
        <textarea id="content" name="content" required rows="10"><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        
        <input type="submit" value="Update Post">
    </form>
    
    <a href="view_posts.php">Back to Posts</a>
</body>
</html>
?>