<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'blog_db';
    private $username = 'db_user';
    private $password = 'db_password';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

class BlogPost {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function create($title, $content, $author) {
        $sql = "INSERT INTO posts (title, content, author, created_at) VALUES (:title, :content, :author, NOW())";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':title' => htmlspecialchars(trim($title), ENT_QUOTES, 'UTF-8'),
            ':content' => htmlspecialchars(trim($content), ENT_QUOTES, 'UTF-8'),
            ':author' => htmlspecialchars(trim($author), ENT_QUOTES, 'UTF-8')
        ]);
    }
    
    public function read($id = null) {
        if ($id) {
            $sql = "SELECT * FROM posts WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        } else {
            $sql = "SELECT * FROM posts ORDER BY created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll();
        }
    }
    
    public function update($id, $title, $content, $author) {
        $sql = "UPDATE posts SET title = :title, content = :content, author = :author WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':title' => htmlspecialchars(trim($title), ENT_QUOTES, 'UTF-8'),
            ':content' => htmlspecialchars(trim($content), ENT_QUOTES, 'UTF-8'),
            ':author' => htmlspecialchars(trim($author), ENT_QUOTES, 'UTF-8')
        ]);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM posts WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

session_start();

$blogPost = new BlogPost();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                if (!empty($_POST['title']) && !empty($_POST['content']) && !empty($_POST['author'])) {
                    if ($blogPost->create($_POST['title'], $_POST['content'], $_POST['author'])) {
                        $message = 'Post created successfully';
                    } else {
                        $error = 'Failed to create post';
                    }
                } else {
                    $error = 'All fields are required';
                }
                break;
                
            case 'update':
                if (!empty($_POST['id']) && !empty($_POST['title']) && !empty($_POST['content']) && !empty($_POST['author'])) {
                    if ($blogPost->update($_POST['id'], $_POST['title'], $_POST['content'], $_POST['author'])) {
                        $message = 'Post updated successfully';
                    } else {
                        $error = 'Failed to update post';
                    }
                } else {
                    $error = 'All fields are required';
                }
                break;
                
            case 'delete':
                if (!empty($_POST['id'])) {
                    if ($blogPost->delete($_POST['id'])) {
                        $message = 'Post deleted successfully';
                    } else {
                        $error = 'Failed to delete post';
                    }
                } else {
                    $error = 'Post ID is required';
                }
                break;
        }
    }
}

$editPost = null;
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
        <div id="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div id="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <h2><?php echo $editPost ? 'Edit Post' : 'Create New Post'; ?></h2>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="<?php echo $editPost ? 'update' : 'create'; ?>">
        <?php if ($editPost): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id']); ?>">
        <?php endif; ?>
        
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required maxlength="255" value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>">
        </div>
        
        <div>
            <label for="author">Author:</label>
            <input type="text" id="author" name="author" required maxlength="100" value="<?php echo $editPost ? htmlspecialchars($editPost['author']) : ''; ?>">
        </div>
        
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" required rows="10" cols="50"><?php echo $editPost ? htmlspecialchars($editPost['content']) : ''; ?></textarea>
        </div>
        
        <div>
            <input type="submit" value="<?php echo $editPost ? 'Update Post' : 'Create Post'; ?>">
            <?php if ($editPost): ?>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
    
    <h2>Existing Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><strong>Author:</strong> <?php echo htmlspecialchars($post['author']); ?></p>
                <p><strong>Created:</strong> <?php echo htmlspecialchars($post['created_at']); ?></p>
                <div><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
                
                <div>
                    <a href="?edit=<?php echo urlencode($post['id']); ?>">Edit</a>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                        <input type="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this post?');">
                    </form>
                </div>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>


sql
CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    author VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_created_at (created_at)
);
?>