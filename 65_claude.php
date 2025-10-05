<?php
class BlogPostManager {
    private $pdo;
    
    public function __construct($host, $dbname, $username, $password) {
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->createTable();
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS blog_posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }
    
    public function createPost($title, $content) {
        $title = trim($title);
        $content = trim($content);
        
        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required");
        }
        
        if (strlen($title) > 255) {
            throw new Exception("Title must be 255 characters or less");
        }
        
        $sql = "INSERT INTO blog_posts (title, content) VALUES (:title, :content)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':title' => $title, ':content' => $content]);
    }
    
    public function getAllPosts() {
        $sql = "SELECT * FROM blog_posts ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getPostById($id) {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            throw new Exception("Invalid post ID");
        }
        
        $sql = "SELECT * FROM blog_posts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }
    
    public function updatePost($id, $title, $content) {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            throw new Exception("Invalid post ID");
        }
        
        $title = trim($title);
        $content = trim($content);
        
        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required");
        }
        
        if (strlen($title) > 255) {
            throw new Exception("Title must be 255 characters or less");
        }
        
        $sql = "UPDATE blog_posts SET title = :title, content = :content WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);
    }
    
    public function deletePost($id) {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            throw new Exception("Invalid post ID");
        }
        
        $sql = "DELETE FROM blog_posts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
?>


<?php
require_once 'BlogPostManager.php';

session_start();

$host = 'localhost';
$dbname = 'blog_db';
$username = 'your_username';
$password = 'your_password';

try {
    $blogManager = new BlogPostManager($host, $dbname, $username, $password);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create':
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';
                
                if ($blogManager->createPost($title, $content)) {
                    $success = "Blog post created successfully!";
                }
                break;
                
            case 'update':
                $id = $_POST['id'] ?? '';
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';
                
                if ($blogManager->updatePost($id, $title, $content)) {
                    $success = "Blog post updated successfully!";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? '';
                
                if ($blogManager->deletePost($id)) {
                    $success = "Blog post deleted successfully!";
                }
                break;
        }
    }
    
    $posts = $blogManager->getAllPosts();
    $editPost = null;
    
    if (isset($_GET['edit']) && filter_var($_GET['edit'], FILTER_VALIDATE_INT)) {
        $editPost = $blogManager->getPostById($_GET['edit']);
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Manager</title>
</head>
<body>
    <h1>Blog Post Manager</h1>
    
    <?php if (isset($success)): ?>
        <div><strong><?php echo htmlspecialchars($success); ?></strong></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div><strong>Error: <?php echo htmlspecialchars($error); ?></strong></div>
    <?php endif; ?>
    
    <h2><?php echo $editPost ? 'Edit Blog Post' : 'Create New Blog Post'; ?></h2>
    
    <form action="blog_post_handler.php" method="POST">
        <?php if ($editPost): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id']); ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>
        
        <div>
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" required maxlength="255" 
                   value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>">
        </div><br>
        
        <div>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" required rows="10" cols="50"><?php echo $editPost ? htmlspecialchars($editPost['content']) : ''; ?></textarea>
        </div><br>
        
        <input type="submit" value="<?php echo $editPost ? 'Update Post' : 'Create Post'; ?>">
        <?php if ($editPost): ?>
            <a href="blog_post_handler.php">Cancel</a>
        <?php endif; ?>
    </form>
    
    <h2>All Blog Posts</h2>
    
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Created: <?php echo htmlspecialchars($post['created_at']); ?></small>
                <?php if ($post['updated_at'] !== $post['created_at']): ?>
                    <small> | Updated: <?php echo htmlspecialchars($post['updated_at']); ?></small>
                <?php endif; ?>
                <br><br>
                
                <a href="?edit=<?php echo htmlspecialchars($post['id']); ?>">Edit</a> |
                
                <form action="blog_post_handler.php" method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                    <input type="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this post?');">
                </form>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
?>