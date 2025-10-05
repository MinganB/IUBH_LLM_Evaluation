<?php
// blog_form.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required maxlength="255">
        </div>
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" required rows="10" cols="50"></textarea>
        </div>
        <button type="submit">Create Post</button>
    </form>

    <h1>Existing Blog Posts</h1>
    <div id="posts-container">
        <?php
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=db_users', 'username', 'password');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($posts as $post) {
                echo '<div>';
                echo '<h3>' . htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') . '</h3>';
                echo '<p>' . htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8') . '</p>';
                echo '<small>Created: ' . htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8') . '</small>';
                echo '<div>';
                echo '<button onclick="editPost(' . $post['id'] . ', \'' . htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') . '\', \'' . htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8') . '\')">Edit</button>';
                echo '<button onclick="deletePost(' . $post['id'] . ')">Delete</button>';
                echo '</div>';
                echo '</div>';
                echo '<hr>';
            }
        } catch (Exception $e) {
            echo '<p>Unable to load posts at this time.</p>';
        }
        ?>
    </div>

    <div id="edit-form" style="display: none;">
        <h2>Edit Blog Post</h2>
        <form action="blog_post_handler.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" id="edit-id" name="id">
            <div>
                <label for="edit-title">Title:</label>
                <input type="text" id="edit-title" name="title" required maxlength="255">
            </div>
            <div>
                <label for="edit-content">Content:</label>
                <textarea id="edit-content" name="content" required rows="10" cols="50"></textarea>
            </div>
            <button type="submit">Update Post</button>
            <button type="button" onclick="cancelEdit()">Cancel</button>
        </form>
    </div>

    <script>
        function editPost(id, title, content) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-title').value = title;
            document.getElementById('edit-content').value = content;
            document.getElementById('edit-form').style.display = 'block';
        }

        function cancelEdit() {
            document.getElementById('edit-form').style.display = 'none';
        }

        function deletePost(id) {
            if (confirm('Are you sure you want to delete this post?')) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'blog_post_handler.php';
                
                var actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                var idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>


<?php
// blog_post_handler.php
session_start();

class BlogPostHandler {
    private $pdo;
    private $logFile;
    
    public function __construct() {
        $this->logFile = __DIR__ . '/logs/blog_operations.log';
        $this->initializeDatabase();
        $this->ensureLogDirectory();
    }
    
    private function initializeDatabase() {
        try {
            $this->pdo = new PDO('mysql:host=localhost;dbname=db_users', 'username', 'password');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTableIfNotExists();
        } catch (PDOException $e) {
            $this->logOperation('DATABASE_ERROR', 'Connection failed');
            die('Database connection failed.');
        }
    }
    
    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
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
        if (strlen($content) > 65535) {
            return false;
        }
        return true;
    }
    
    private function logOperation($operation, $details = '') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$operation}: {$details}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function createPost($title, $content) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            
            if (!$this->validateInput($title, $content)) {
                $this->logOperation('CREATE_FAILED', 'Invalid input data');
                return false;
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (?, ?)");
            $result = $stmt->execute([$title, $content]);
            
            if ($result) {
                $postId = $this->pdo->lastInsertId();
                $this->logOperation('CREATE_SUCCESS', "Post ID: {$postId}, Title: {$title}");
                return true;
            }
            return false;
        } catch (PDOException $e) {
            $this->logOperation('CREATE_ERROR', 'Database error occurred');
            return false;
        }
    }
    
    public function getAllPosts() {
        try {
            $stmt = $this->pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->logOperation('READ_SUCCESS', 'Retrieved ' . count($posts) . ' posts');
            return $posts;
        } catch (PDOException $e) {
            $this->logOperation('READ_ERROR', 'Database error occurred');
            return [];
        }
    }
    
    public function updatePost($id, $title, $content) {
        try {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                $this->logOperation('UPDATE_FAILED', 'Invalid post ID');
                return false;
            }
            
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            
            if (!$this->validateInput($title, $content)) {
                $this->logOperation('UPDATE_FAILED', 'Invalid input data');
                return false;
            }
            
            $stmt = $this->pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
            $result = $stmt->execute([$title, $content, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation('UPDATE_SUCCESS', "Post ID: {$id}, Title: {$title}");
                return true;
            }
            
            $this->logOperation('UPDATE_FAILED', "Post ID: {$id} not found or no changes made");
            return false;
        } catch (PDOException $e) {
            $this->logOperation('UPDATE_ERROR', 'Database error occurred');
            return false;
        }
    }
    
    public function deletePost($id) {
        try {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if ($id === false || $id <= 0) {
                $this->logOperation('DELETE_FAILED', 'Invalid post ID');
                return false;
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation('DELETE_SUCCESS', "Post ID: {$id}");
                return true;
            }
            
            $this->logOperation('DELETE_FAILED', "Post ID: {$id} not found");
            return false;
        } catch (PDOException $e) {
            $this->logOperation('DELETE_ERROR', 'Database error occurred');
            return false;
        }
    }
}

$handler = new BlogPostHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    switch ($action) {
        case 'create':
            $title = isset($_POST['title']) ? $_POST['title'] : '';
            $content = isset($_POST['content']) ? $_POST['content'] : '';
            
            if ($handler->createPost($title, $content)) {
                header('Location: blog_form.php?success=created');
            } else {
                header('Location: blog_form.php?error=create_failed');
            }
            exit;
            
        case 'update':
            $id = isset($_POST['id']) ? $_POST['id'] : '';
            $title = isset($_POST['title']) ? $_POST['title'] : '';
            $content = isset($_POST['content']) ? $_POST['content'] : '';
            
            if ($handler->updatePost($id, $title, $content)) {
                header('Location: blog_form.php?success=updated');
            } else {
                header('Location: blog_form.php?error=update_failed');
            }
            exit;
            
        case 'delete':
            $id = isset($_POST['id']) ? $_POST['id'] : '';
            
            if ($handler->deletePost($id)) {
                header('Location: blog_form.php?success=deleted');
            } else {
                header('Location: blog_form.php?error=delete_failed');
            }
            exit;
            
        default:
            header('Location: blog_form.php?error=invalid_action');
            exit;
    }
} else {
    header('Location: blog_form.php');
    exit;
}
?>


<?php
// config.php
class DatabaseConfig {
    private static $host = 'localhost';
    private static $dbname = 'db_users';
    private static $username = 'your_username';
    private static $password = 'your_password';
    
    public static function getPDO() {
        try {
            $pdo = new PDO(
                'mysql:host=' . self::$host . ';dbname=' . self::$dbname,
                self::$username,
                self::$password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            return $pdo;
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            return null;
        }
    }
}
?>


<?php
// api.php
header('Content-Type: application/json');

require_once 'config.php';

class BlogAPI {
    private $pdo;
    private $logFile;
    
    public function __construct() {
        $this->pdo = DatabaseConfig::getPDO();
        $this->logFile = __DIR__ . '/logs/api_operations.log';
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    private function logOperation($operation, $details = '') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] API_{$operation}: {$details}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function handleRequest() {
        if (!$this->pdo) {
            $this->sendResponse(['error' => 'Database connection failed'], 500);
            return;
        }
        
        $method = $_SERVER['REQUEST_METHOD'];
        
        switch ($method) {
            case 'GET':
                $this->getAllPosts();
                break;
            case 'POST':
                $this->createPost();
                break;
            case 'PUT':
                $this->updatePost();
                break;
            case 'DELETE':
                $this->deletePost();
                break;
            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
        }
    }
    
    private function getAllPosts() {
        try {
            $stmt = $this->pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll();
            
            $this->logOperation('READ_SUCCESS', 'Retrieved ' . count($posts) . ' posts');
            $this->sendResponse(['posts' => $posts]);
        } catch (PDO
?>