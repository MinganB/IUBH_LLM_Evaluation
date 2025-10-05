<?php

$host = 'localhost';
$db   = 'blog_db';
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

$post_to_edit = null;
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $post_id = $_POST['post_id'] ?? null;

    if (empty($title)) {
        $errors[] = 'Title is required.';
    }
    if (empty($content)) {
        $errors[] = 'Content is required.';
    }

    if (empty($errors)) {
        if ($post_id) {
            $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $post_id]);
            $message = 'Post updated successfully!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (?, ?)");
            $stmt->execute([$title, $content]);
            $message = 'Post created successfully!';
        }
        header('Location: ' . $_SERVER['PHP_SELF'] . '?message=' . urlencode($message));
        exit;
    }
}

if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: ' . $_SERVER['PHP_SELF'] . '?message=' . urlencode('Post deleted successfully!'));
    exit;
}

if (isset($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT id, title, content FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $post_to_edit = $stmt->fetch();
    if (!$post_to_edit) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?message=' . urlencode('Post not found!'));
        exit;
    }
}

if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message']);
}

$stmt = $pdo->query("SELECT id, title, content, created_at FROM posts ORDER BY created_at DESC");
$posts = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Management</title>
</head>
<body>
    <h1>Blog Post Management</h1>

    <?php if (!empty($message)): ?>
        <p style="color: green; font-weight: bold;"><?php echo $message; ?></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2><?php echo $post_to_edit ? 'Edit Post' : 'Create New Post'; ?></h2>
    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <?php if ($post_to_edit): ?>
            <input type="hidden" name="post_id" value="<?php echo htmlspecialchars($post_to_edit['id']); ?>">
        <?php endif; ?>
        <p>
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post_to_edit['title'] ?? ''); ?>" size="50" required>
        </p>
        <p>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="10" cols="70" required><?php echo htmlspecialchars($post_to_edit['content'] ?? ''); ?></textarea>
        </p>
        <p>
            <button type="submit"><?php echo $post_to_edit ? 'Update Post' : 'Create Post'; ?></button>
            <?php if ($post_to_edit): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Cancel Edit</a>
            <?php endif; ?>
        </p>
    </form>

    <h2>Existing Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No posts found. Create one above!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <p>
                    <small>Posted on: <?php echo htmlspecialchars($post['created_at']); ?></small> |
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?edit=<?php echo htmlspecialchars($post['id']); ?>">Edit</a> |
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?delete=<?php echo htmlspecialchars($post['id']); ?>" onclick="return confirm('Are you sure you want to delete this post?');">Delete</a>
                </p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
?>