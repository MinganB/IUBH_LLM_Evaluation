<?php
$host = 'localhost';
$dbname = 'db_users';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            
            if (!empty($title) && !empty($content)) {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$title, $content]);
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            break;
            
        case 'update':
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            
            if ($id && !empty($title) && !empty($content)) {
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$title, $content, $id]);
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            break;
            
        case 'delete':
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            break;
    }
}

$stmt = $pdo->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
$posts = $stmt->fetchAll();

$editPost = null;
if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT);
    if ($editId) {
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
        $stmt->execute([$editId]);
        $editPost = $stmt->fetch();
    }
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
    
    <form method="POST" action="blog_post_handler.php">
        <input type="hidden" name="action" value="<?php echo $editPost ? 'update' : 'create'; ?>">
        <?php if ($editPost): ?>
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id']); ?>">
        <?php endif; ?>
        
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>" required>
        </div>
        
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" required><?php echo $editPost ? htmlspecialchars($editPost['content']) : ''; ?></textarea>
        </div>
        
        <button type="submit"><?php echo $editPost ? 'Update Post' : 'Create Post'; ?></button>
        <?php if ($editPost): ?>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Cancel</a>
        <?php endif; ?>
    </form>
    
    <h2>Existing Posts</h2>
    
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Created: <?php echo htmlspecialchars($post['created_at']); ?>
                <?php if ($post['updated_at']): ?>
                    | Updated: <?php echo htmlspecialchars($post['updated_at']); ?>
                <?php endif; ?>
                </small>
                
                <div>
                    <a href="<?php echo $_SERVER['PHP_SELF'] . '?edit=' . $post['id']; ?>">Edit</a>
                    
                    <form method="POST" action="blog_post_handler.php" style="display: inline;">
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


<?php
$host = 'localhost';
$dbname = 'db_users';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            
            if (!empty($title) && !empty($content)) {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$title, $content]);
            }
            break;
            
        case 'update':
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            
            if ($id && !empty($title) && !empty($content)) {
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$title, $content, $id]);
            }
            break;
            
        case 'delete':
            $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
            
            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
            }
            break;
            
        case 'read':
            $stmt = $pdo->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
            $posts = $stmt->fetchAll();
            echo json_encode($posts);
            exit;
    }
    
    header("Location: index.php");
    exit;
}
?>



sql
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL
);
?>