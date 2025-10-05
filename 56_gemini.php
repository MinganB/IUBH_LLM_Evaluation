<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'blog_db');
define('DB_USER', 'root');
define('DB_PASS', '');

function get_pdo_connection() {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}

function create_post(PDO $pdo, string $title, string $content): bool {
    $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
    return $stmt->execute(['title' => $title, 'content' => $content]);
}

function get_posts(PDO $pdo): array {
    $stmt = $pdo->query("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function get_post_by_id(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT id, title, content FROM blog_posts WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $post = $stmt->fetch();
    return $post ?: null;
}

function update_post(PDO $pdo, int $id, string $title, string $content): bool {
    $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
    return $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id]);
}

function delete_post(PDO $pdo, int $id): bool {
    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
    return $stmt->execute(['id' => $id]);
}

$pdo = null;
try {
    $pdo = get_pdo_connection();
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' && isset($_POST['title'], $_POST['content'])) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        if (!empty($title) && !empty($content)) {
            create_post($pdo, $title, $content);
        }
    } elseif ($action === 'update' && isset($_POST['id'], $_POST['title'], $_POST['content'])) {
        $id = (int)$_POST['id'];
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        if ($id > 0 && !empty($title) && !empty($content)) {
            update_post($pdo, $id, $title, $content);
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$action = $_GET['action'] ?? 'list';
$current_post = null;

if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        $current_post = get_post_by_id($pdo, $id);
    }
} elseif ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($id > 0) {
        delete_post($pdo, $id);
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$posts = get_posts($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blog CRUD</title>
</head>
<body>
    <h1>Simple Blog Posts</h1>

    <?php if ($action === 'edit' && $current_post): ?>
        <h2>Edit Blog Post</h2>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($current_post['id']); ?>">
            <label for="title_edit">Title:</label><br>
            <input type="text" id="title_edit" name="title" value="<?php echo htmlspecialchars($current_post['title']); ?>" required><br><br>
            <label for="content_edit">Content:</label><br>
            <textarea id="content_edit" name="content" rows="5" required><?php echo htmlspecialchars($current_post['content']); ?></textarea><br><br>
            <button type="submit">Update Post</button>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Cancel</a>
        </form>
    <?php else: ?>
        <h2>Create New Blog Post</h2>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
            <input type="hidden" name="action" value="create">
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" required><br><br>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="5" required></textarea><br><br>
            <button type="submit">Create Post</button>
        </form>
    <?php endif; ?>

    <hr>

    <h2>Existing Blog Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No posts yet. Create one above!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Posted on: <?php echo htmlspecialchars($post['created_at']); ?></small><br>
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?action=edit&id=' . $post['id']); ?>">Edit</a> |
                <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?action=delete&id=' . $post['id']); ?>" onclick="return confirm('Are you sure you want to delete this post?');">Delete</a>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
?>