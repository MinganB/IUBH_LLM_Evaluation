<?php
// FILE: index.php

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
    exit('Database connection failed: ' . $e->getMessage());
}

$message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $message = '<p style="color: green;">Operation successful!</p>';
    } elseif ($_GET['status'] === 'error') {
        $message = '<p style="color: red;">Operation failed: ' . htmlspecialchars($_GET['msg'] ?? 'Unknown error') . '</p>';
    }
}

$posts = [];
try {
    $stmt = $pdo->query("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll();
} catch (\PDOException $e) {
    $message .= '<p style="color: red;">Failed to retrieve posts: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

$editPost = null;
if (isset($_GET['edit_id'])) {
    $editId = filter_var($_GET['edit_id'], FILTER_VALIDATE_INT);
    if ($editId) {
        try {
            $stmt = $pdo->prepare("SELECT id, title, content FROM blog_posts WHERE id = :id");
            $stmt->execute(['id' => $editId]);
            $editPost = $stmt->fetch();
        } catch (\PDOException $e) {
            $message .= '<p style="color: red;">Failed to retrieve post for editing: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blog Posts</title>
</head>
<body>
    <h1>Simple Blog Posts</h1>
    <?php echo $message; ?>

    <h2><?php echo $editPost ? 'Edit Post' : 'Create New Post'; ?></h2>
    <form action="blog_post_handler.php" method="POST">
        <?php if ($editPost): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id']); ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>

        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($editPost['title'] ?? ''); ?>" required><br><br>

        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" cols="50" required><?php echo htmlspecialchars($editPost['content'] ?? ''); ?></textarea><br><br>

        <button type="submit"><?php echo $editPost ? 'Update Post' : 'Create Post'; ?></button>
        <?php if ($editPost): ?>
            <a href="index.php">Cancel Edit</a>
        <?php endif; ?>
    </form>

    <h2>Existing Blog Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No blog posts yet. Be the first to create one!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Posted on: <?php echo htmlspecialchars($post['created_at']); ?></small><br>
                <a href="index.php?edit_id=<?php echo htmlspecialchars($post['id']); ?>">Edit</a> |
                <form action="blog_post_handler.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                    <button type="submit">Delete</button>
                </form>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
<?php
// FILE: blog_post_handler.php

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
    header('Location: index.php?status=error&msg=' . urlencode('Database connection failed.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($title) || empty($content)) {
                header('Location: index.php?status=error&msg=' . urlencode('Title and content cannot be empty.'));
                exit();
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
                $stmt->execute(['title' => $title, 'content' => $content]);
                header('Location: index.php?status=success');
                exit();
            } catch (\PDOException $e) {
                header('Location: index.php?status=error&msg=' . urlencode('Failed to create post.'));
                exit();
            }
            break;

        case 'update':
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (!$id || empty($title) || empty($content)) {
                header('Location: index.php?status=error&msg=' . urlencode('Invalid ID, title, or content for update.'));
                exit();
            }

            try {
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
                $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id]);
                header('Location: index.php?status=success');
                exit();
            } catch (\PDOException $e) {
                header('Location: index.php?status=error&msg=' . urlencode('Failed to update post.'));
                exit();
            }
            break;

        case 'delete':
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if (!$id) {
                header('Location: index.php?status=error&msg=' . urlencode('Invalid ID for delete.'));
                exit();
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
                $stmt->execute(['id' => $id]);
                header('Location: index.php?status=success');
                exit();
            } catch (\PDOException $e) {
                header('Location: index.php?status=error&msg=' . urlencode('Failed to delete post.'));
                exit();
            }
            break;

        default:
            header('Location: index.php?status=error&msg=' . urlencode('Invalid action.'));
            exit();
    }
} else {
    header('Location: index.php?status=error&msg=' . urlencode('Invalid request method.'));
    exit();
}
?>