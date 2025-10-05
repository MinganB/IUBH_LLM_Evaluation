<?php
$host = 'localhost';
$dbname = 'db_users';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $title = $_POST['title'];
                $content = $_POST['content'];
                
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (?, ?)");
                $stmt->execute([$title, $content]);
                
                header('Location: index.php');
                exit();
                break;
                
            case 'update':
                $id = $_POST['id'];
                $title = $_POST['title'];
                $content = $_POST['content'];
                
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $content, $id]);
                
                header('Location: index.php');
                exit();
                break;
                
            case 'delete':
                $id = $_POST['id'];
                
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
                
                header('Location: index.php');
                exit();
                break;
        }
    }
}
?>


<?php
$host = 'localhost';
$dbname = 'db_users';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$stmt = $pdo->query("SELECT * FROM blog_posts ORDER BY id DESC");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$editPost = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$editId]);
    $editPost = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Blog Posts</title>
</head>
<body>
    <h1>Blog Posts</h1>
    
    <h2><?php echo $editPost ? 'Edit Post' : 'Create New Post'; ?></h2>
    <form method="POST" action="blog_post_handler.php">
        <input type="hidden" name="action" value="<?php echo $editPost ? 'update' : 'create'; ?>">
        <?php if ($editPost): ?>
            <input type="hidden" name="id" value="<?php echo $editPost['id']; ?>">
        <?php endif; ?>
        
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>" required><br><br>
        
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" cols="50" required><?php echo $editPost ? htmlspecialchars($editPost['content']) : ''; ?></textarea><br><br>
        
        <input type="submit" value="<?php echo $editPost ? 'Update Post' : 'Create Post'; ?>">
        <?php if ($editPost): ?>
            <a href="index.php">Cancel</a>
        <?php endif; ?>
    </form>
    
    <h2>All Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <a href="index.php?edit=<?php echo $post['id']; ?>">Edit</a>
                <form method="POST" action="blog_post_handler.php" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
?>