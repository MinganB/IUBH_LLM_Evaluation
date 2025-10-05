<?php
class DatabaseConnection {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }
    
    public function getPDO() {
        return $this->pdo;
    }
}

class BlogPost {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createTable();
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
    
    public function create($title, $content) {
        $sql = "INSERT INTO blog_posts (title, content) VALUES (:title, :content)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':title' => $title, ':content' => $content]);
    }
    
    public function readAll() {
        $sql = "SELECT * FROM blog_posts ORDER BY created_at DESC";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function readById($id) {
        $sql = "SELECT * FROM blog_posts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function update($id, $title, $content) {
        $sql = "UPDATE blog_posts SET title = :title, content = :content WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id, ':title' => $title, ':content' => $content]);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM blog_posts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
?>


<?php
require_once 'blog_post.php';

$db = new DatabaseConnection();
$blogPost = new BlogPost($db->getPDO());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            if (!empty($_POST['title']) && !empty($_POST['content'])) {
                $blogPost->create($_POST['title'], $_POST['content']);
                header('Location: index.php?message=created');
                exit;
            }
            break;
            
        case 'update':
            if (!empty($_POST['id']) && !empty($_POST['title']) && !empty($_POST['content'])) {
                $blogPost->update($_POST['id'], $_POST['title'], $_POST['content']);
                header('Location: index.php?message=updated');
                exit;
            }
            break;
            
        case 'delete':
            if (!empty($_POST['id'])) {
                $blogPost->delete($_POST['id']);
                header('Location: index.php?message=deleted');
                exit;
            }
            break;
    }
}

header('Location: index.php');
exit;
?>


<?php
require_once 'blog_post.php';

$db = new DatabaseConnection();
$blogPost = new BlogPost($db->getPDO());

$posts = $blogPost->readAll();
$editPost = null;

if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editPost = $blogPost->readById($_GET['edit']);
}

$message = '';
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'created':
            $message = 'Blog post created successfully!';
            break;
        case 'updated':
            $message = 'Blog post updated successfully!';
            break;
        case 'deleted':
            $message = 'Blog post deleted successfully!';
            break;
    }
}
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
        <div><?php echo htmlspecialchars($message); ?></div>
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
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>">
        </div>
        
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" required rows="10"><?php echo $editPost ? htmlspecialchars($editPost['content']) : ''; ?></textarea>
        </div>
        
        <button type="submit"><?php echo $editPost ? 'Update' : 'Create'; ?> Blog Post</button>
        <?php if ($editPost): ?>
            <a href="index.php">Cancel</a>
        <?php endif; ?>
    </form>
    
    <h2>Existing Blog Posts</h2>
    
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Created: <?php echo htmlspecialchars($post['created_at']); ?></small>
                <?php if ($post['updated_at'] !== $post['created_at']): ?>
                    <small>Updated: <?php echo htmlspecialchars($post['updated_at']); ?></small>
                <?php endif; ?>
                
                <div>
                    <a href="index.php?edit=<?php echo $post['id']; ?>">Edit</a>
                    <form action="blog_post_handler.php" method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                        <button type="submit" onclick="return confirm('Are you sure you want to delete this post?')">Delete</button>
                    </form>
                </div>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
?>