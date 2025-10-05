<?php

$host = 'localhost';
$db   = 'db_users';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
                $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);

                if ($title && $content) {
                    $stmt = $pdo->prepare('INSERT INTO blog_posts (title, content) VALUES (?, ?)');
                    $stmt->execute([$title, $content]);
                }
                break;

            case 'update':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
                $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);

                if ($id && $title && $content) {
                    $stmt = $pdo->prepare('UPDATE blog_posts SET title = ?, content = ? WHERE id = ?');
                    $stmt->execute([$title, $content, $id]);
                }
                break;

            case 'delete':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

                if ($id) {
                    $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = ?');
                    $stmt->execute([$id]);
                }
                break;
        }
    }
    header('Location: blog_post_handler.php');
    exit;
}

$posts = $pdo->query('SELECT * FROM blog_posts ORDER BY created_at DESC')->fetchAll();

$editPost = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        $editPost = $stmt->fetch();
    }
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

    <?php if ($editPost): ?>
        <h2>Edit Blog Post</h2>
        <form action="blog_post_handler.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id']); ?>">
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($editPost['title']); ?>" required><br><br>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="10" cols="50" required><?php echo htmlspecialchars($editPost['content']); ?></textarea><br><br>
            <button type="submit">Update Post</button>
            <a href="blog_post_handler.php">Cancel</a>
        </form>
        <hr>
    <?php else: ?>
        <h2>Create New Blog Post</h2>
        <form action="blog_post_handler.php" method="POST">
            <input type="hidden" name="action" value="create">
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" required><br><br>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="10" cols="50" required></textarea><br><br>
            <button type="submit">Create Post</button>
        </form>
        <hr>
    <?php endif; ?>

    <h2>All Blog Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div style="border: 1px solid #ccc; padding: 10px; margin-bottom: 15px;">
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Posted on: <?php echo htmlspecialchars($post['created_at']); ?></small><br>
                <a href="blog_post_handler.php?action=edit&id=<?php echo htmlspecialchars($post['id']); ?>">Edit</a> | 
                <form action="blog_post_handler.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this post?');">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
?>