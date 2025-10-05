<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', '');

$pdo = null;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error connecting to database: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';

            if ($title && $content) {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
                $stmt->execute([':title' => $title, ':content' => $content]);
            }
            break;

        case 'update':
            $id = $_POST['id'] ?? null;
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';

            if ($id && $title && $content) {
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
                $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);
            }
            break;

        case 'delete':
            $id = $_POST['id'] ?? null;

            if ($id) {
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
                $stmt->execute([':id' => $id]);
            }
            break;
    }

    header('Location: blog_post_handler.php');
    exit;
}

$posts = [];
try {
    $stmt = $pdo->query("SELECT id, title, content FROM blog_posts ORDER BY id DESC");
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    $posts = [['title' => 'Error', 'content' => 'Could not retrieve posts.']];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post CRUD</title>
</head>
<body>
    <h1>Blog Posts</h1>

    <h2>Create New Post</h2>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="5" required></textarea><br><br>
        <button type="submit">Create Post</button>
    </form>

    <h2>Existing Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <hr>
            <h3><?php echo htmlspecialchars($post['title']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>

            <form action="blog_post_handler.php" method="POST" style="display:inline-block; margin-right: 10px;">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                <label for="update_title_<?php echo htmlspecialchars($post['id']); ?>">Title:</label>
                <input type="text" id="update_title_<?php echo htmlspecialchars($post['id']); ?>" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required><br>
                <label for="update_content_<?php echo htmlspecialchars($post['id']); ?>">Content:</label>
                <textarea id="update_content_<?php echo htmlspecialchars($post['id']); ?>" name="content" rows="3" required><?php echo htmlspecialchars($post['content']); ?></textarea><br>
                <button type="submit">Update Post</button>
            </form>

            <form action="blog_post_handler.php" method="POST" style="display:inline-block;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                <button type="submit" onclick="return confirm('Are you sure you want to delete this post?');">Delete Post</button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
?>