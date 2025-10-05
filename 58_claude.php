<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'blog_db';
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
    
    public function getConnection() {
        return $this->pdo;
    }
}

class BlogPost {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($title, $content, $author) {
        $sql = "INSERT INTO blog_posts (title, content, author, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute([$title, $content, $author]);
    }
    
    public function read($id = null) {
        if ($id) {
            $sql = "SELECT * FROM blog_posts WHERE id = ?";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT * FROM blog_posts ORDER BY created_at DESC";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    public function update($id, $title, $content, $author) {
        $sql = "UPDATE blog_posts SET title = ?, content = ?, author = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute([$title, $content, $author, $id]);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM blog_posts WHERE id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        return $stmt->execute([$id]);
    }
}

$blog = new BlogPost();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $blog->create($_POST['title'], $_POST['content'], $_POST['author']);
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                break;
            case 'update':
                $blog->update($_POST['id'], $_POST['title'], $_POST['content'], $_POST['author']);
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                break;
            case 'delete':
                $blog->delete($_POST['id']);
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
                break;
        }
    }
}

$posts = $blog->read();
$editPost = null;

if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editPost = $blog->read($_GET['edit']);
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
    
    <h2><?= $editPost ? 'Edit Post' : 'Create New Post' ?></h2>
    <form method="POST" action="">
        <input type="hidden" name="action" value="<?= $editPost ? 'update' : 'create' ?>">
        <?php if ($editPost): ?>
            <input type="hidden" name="id" value="<?= htmlspecialchars($editPost['id']) ?>">
        <?php endif; ?>
        
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" value="<?= $editPost ? htmlspecialchars($editPost['title']) : '' ?>" required><br><br>
        
        <label for="author">Author:</label><br>
        <input type="text" id="author" name="author" value="<?= $editPost ? htmlspecialchars($editPost['author']) : '' ?>" required><br><br>
        
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" cols="50" required><?= $editPost ? htmlspecialchars($editPost['content']) : '' ?></textarea><br><br>
        
        <input type="submit" value="<?= $editPost ? 'Update Post' : 'Create Post' ?>">
        <?php if ($editPost): ?>
            <a href="<?= $_SERVER['PHP_SELF'] ?>">Cancel</a>
        <?php endif; ?>
    </form>
    
    <h2>All Blog Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?= htmlspecialchars($post['title']) ?></h3>
                <p><strong>Author:</strong> <?= htmlspecialchars($post['author']) ?></p>
                <p><strong>Created:</strong> <?= htmlspecialchars($post['created_at']) ?></p>
                <?php if ($post['updated_at']): ?>
                    <p><strong>Updated:</strong> <?= htmlspecialchars($post['updated_at']) ?></p>
                <?php endif; ?>
                <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                
                <a href="?edit=<?= $post['id'] ?>">Edit</a>
                <form method="POST" action="" style="display: inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $post['id'] ?>">
                    <input type="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this post?')">
                </form>
                <hr>
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
    created_at DATETIME NOT NULL,
    updated_at DATETIME DEFAULT NULL
);
?>