<?php
class BlogPost {
    private $pdo;
    private $logFile;
    
    public function __construct() {
        $this->connectDatabase();
        $this->logFile = __DIR__ . '/../logs/blog_operations.log';
        $this->ensureLogDirectory();
    }
    
    private function connectDatabase() {
        try {
            $this->pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            $this->logOperation('DATABASE_CONNECTION_ERROR', 0);
            throw new Exception('Database connection failed');
        }
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    private function logOperation($operation, $postId = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] Operation: {$operation}";
        if ($postId) {
            $logEntry .= " | Post ID: {$postId}";
        }
        $logEntry .= PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
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
                return ['success' => false, 'message' => 'Invalid input provided'];
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$title, $content]);
            
            if ($result) {
                $postId = $this->pdo->lastInsertId();
                $this->logOperation('CREATE', $postId);
                return ['success' => true, 'message' => 'Blog post created successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to create blog post'];
            }
        } catch (Exception $e) {
            $this->logOperation('CREATE_ERROR');
            return ['success' => false, 'message' => 'An error occurred while creating the post'];
        }
    }
    
    public function read() {
        try {
            $stmt = $this->pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll();
            
            foreach ($posts as &$post) {
                $post['title'] = htmlspecialchars_decode($post['title'], ENT_QUOTES);
                $post['content'] = htmlspecialchars_decode($post['content'], ENT_QUOTES);
            }
            
            $this->logOperation('READ_ALL');
            return ['success' => true, 'message' => 'Posts retrieved successfully', 'data' => $posts];
        } catch (Exception $e) {
            $this->logOperation('READ_ERROR');
            return ['success' => false, 'message' => 'Failed to retrieve posts'];
        }
    }
    
    public function update($id, $title, $content) {
        try {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if (!$id) {
                return ['success' => false, 'message' => 'Invalid post ID'];
            }
            
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            
            if (!$this->validateInput($title, $content)) {
                return ['success' => false, 'message' => 'Invalid input provided'];
            }
            
            $stmt = $this->pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
            $result = $stmt->execute([$title, $content, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation('UPDATE', $id);
                return ['success' => true, 'message' => 'Blog post updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Post not found or no changes made'];
            }
        } catch (Exception $e) {
            $this->logOperation('UPDATE_ERROR', $id ?? null);
            return ['success' => false, 'message' => 'An error occurred while updating the post'];
        }
    }
    
    public function delete($id) {
        try {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if (!$id) {
                return ['success' => false, 'message' => 'Invalid post ID'];
            }
            
            $stmt = $this->pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation('DELETE', $id);
                return ['success' => true, 'message' => 'Blog post deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Post not found'];
            }
        } catch (Exception $e) {
            $this->logOperation('DELETE_ERROR', $id ?? null);
            return ['success' => false, 'message' => 'An error occurred while deleting the post'];
        }
    }
    
    public function getById($id) {
        try {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if (!$id) {
                return ['success' => false, 'message' => 'Invalid post ID'];
            }
            
            $stmt = $this->pdo->prepare("SELECT id, title, content, created_at FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            
            if ($post) {
                $post['title'] = htmlspecialchars_decode($post['title'], ENT_QUOTES);
                $post['content'] = htmlspecialchars_decode($post['content'], ENT_QUOTES);
                $this->logOperation('READ_SINGLE', $id);
                return ['success' => true, 'message' => 'Post retrieved successfully', 'data' => $post];
            } else {
                return ['success' => false, 'message' => 'Post not found'];
            }
        } catch (Exception $e) {
            $this->logOperation('READ_SINGLE_ERROR', $id ?? null);
            return ['success' => false, 'message' => 'Failed to retrieve post'];
        }
    }
}
?>


<?php
require_once __DIR__ . '/../classes/BlogPost.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Only POST requests allowed']);
    exit;
}

try {
    $blogPost = new BlogPost();
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $result = $blogPost->create($title, $content);
            break;
            
        case 'read':
            $result = $blogPost->read();
            break;
            
        case 'update':
            $id = $_POST['id'] ?? '';
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $result = $blogPost->update($id, $title, $content);
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? '';
            $result = $blogPost->delete($id);
            break;
            
        case 'get_by_id':
            $id = $_POST['id'] ?? '';
            $result = $blogPost->getById($id);
            break;
            
        default:
            $result = ['success' => false, 'message' => 'Invalid action'];
            break;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>


html
<!DOCTYPE html>
<html>
<head>
    <title>Blog Management</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form id="createForm" action="/handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required maxlength="255">
        </div>
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" required rows="10" cols="50"></textarea>
        </div>
        <div>
            <button type="submit">Create Post</button>
        </div>
    </form>

    <h1>Update Blog Post</h1>
    <form id="updateForm" action="/handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="update">
        <div>
            <label for="update_id">Post ID:</label>
            <input type="number" id="update_id" name="id" required min="1">
        </div>
        <div>
            <label for="update_title">Title:</label>
            <input type="text" id="update_title" name="title" required maxlength="255">
        </div>
        <div>
            <label for="update_content">Content:</label>
            <textarea id="update_content" name="content" required rows="10" cols="50"></textarea>
        </div>
        <div>
            <button type="submit">Update Post</button>
        </div>
    </form>

    <h1>Delete Blog Post</h1>
    <form id="deleteForm" action="/handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="delete">
        <div>
            <label for="delete_id">Post ID:</label>
            <input type="number" id="delete_id" name="id" required min="1">
        </div>
        <div>
            <button type="submit">Delete Post</button>
        </div>
    </form>

    <h1>Get All Posts</h1>
    <form id="readForm" action="/handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="read">
        <div>
            <button type="submit">Get All Posts</button>
        </div>
    </form>

    <h1>Get Single Post</h1>
    <form id="getByIdForm" action="/handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="get_by_id">
        <div>
            <label for="get_id">Post ID:</label>
            <input type="number" id="get_id" name="id" required min="1">
        </div>
        <div>
            <button type="submit">Get Post</button>
        </div>
    </form>

    <div id="response"></div>

    <script>
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(form);
                
                fetch('/handlers/blog_post_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('response').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                })
                .catch(error => {
                    document.getElementById('response').innerHTML = '<p>Error: ' + error.message + '</p>';
                });
            });
        });
    </script>
</body>
</html>


sql
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
?>