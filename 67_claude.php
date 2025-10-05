<?php
class BlogPost {
    private $pdo;
    
    public function __construct() {
        $this->pdo = new PDO('sqlite:blog.db');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createTable();
    }
    
    private function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS blog_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }
    
    public function create($title, $content) {
        $sql = "INSERT INTO blog_posts (title, content) VALUES (:title, :content)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        return $stmt->execute();
    }
    
    public function read($id = null) {
        if ($id) {
            $sql = "SELECT * FROM blog_posts WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT * FROM blog_posts ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    public function update($id, $title, $content) {
        $sql = "UPDATE blog_posts SET title = :title, content = :content, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        return $stmt->execute();
    }
    
    public function delete($id) {
        $sql = "DELETE FROM blog_posts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
?>


<?php
require_once 'BlogPost.php';

$blogPost = new BlogPost();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                if (!empty($_POST['title']) && !empty($_POST['content'])) {
                    $blogPost->create($_POST['title'], $_POST['content']);
                    header('Location: index.php');
                    exit;
                }
                break;
            
            case 'update':
                if (!empty($_POST['id']) && !empty($_POST['title']) && !empty($_POST['content'])) {
                    $blogPost->update($_POST['id'], $_POST['title'], $_POST['content']);
                    header('Location: index.php');
                    exit;
                }
                break;
            
            case 'delete':
                if (!empty($_POST['id'])) {
                    $blogPost->delete($_POST['id']);
                    header('Location: index.php');
                    exit;
                }
                break;
        }
    }
}

header('Location: index.php');
exit;
?>


<?php
require_once 'BlogPost.php';

$blogPost = new BlogPost();
$posts = $blogPost->read();
$editPost = null;

if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $editPost = $blogPost->read($_GET['edit']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Blog Post Management</title>
</head>
<body>
    <h1>Blog Post Management</h1>
    
    <h2><?php echo $editPost ? 'Edit Post' : 'Create New Post'; ?></h2>
    <form method="POST" action="blog_post_handler.php">
        <?php if ($editPost): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id']); ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>
        
        <div>
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>" required>
        </div>
        <br>
        
        <div>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="10" cols="50" required><?php echo $editPost ? htmlspecialchars($editPost['content']) : ''; ?></textarea>
        </div>
        <br>
        
        <button type="submit"><?php echo $editPost ? 'Update Post' : 'Create Post'; ?></button>
        <?php if ($editPost): ?>
            <a href="index.php">Cancel</a>
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
                <small>Created: <?php echo $post['created_at']; ?> | Updated: <?php echo $post['updated_at']; ?></small>
                <br><br>
                
                <a href="index.php?edit=<?php echo $post['id']; ?>">Edit</a>
                
                <form method="POST" action="blog_post_handler.php" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this post?')">Delete</button>
                </form>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
?>