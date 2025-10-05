<?php
$logPath = __DIR__ . '/logs/blog_crud.log';
if (!is_dir(dirname($logPath))) {
    mkdir(dirname($logPath), 0700, true);
}
function log_action($message) {
    $path = __DIR__ . '/logs/blog_crud.log';
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    $ts = date('Y-m-d H:i:s');
    file_put_contents($path, $ts . "\t" . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}
function get_pdo_connection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db = getenv('DB_NAME') ?: 'blog';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $port = getenv('DB_PORT') ?: '3306';
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}
function ensure_table_exists($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}
$pdo = get_pdo_connection();
ensure_table_exists($pdo);
$posts = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, content, created_at FROM posts ORDER BY created_at DESC");
    $stmt->execute();
    $posts = $stmt->fetchAll();
} catch (Exception $e) {
    log_action("READ_ERROR: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Blog Posts</title>
</head>
<body>
<h1>Blog Posts</h1>
<?php if (isset($_GET['status'])): ?>
<?php
$st = $_GET['status'];
if ($st === 'success') {
    echo '<p>Operation completed successfully.</p>';
} else {
    echo '<p>Operation could not be completed.</p>';
}
?>
<?php endif; ?>
<form method="POST" action="blog_post_handler.php" autocomplete="off">
<input type="text" name="title" placeholder="Post Title" required>
<br>
<textarea name="content" placeholder="Write your post..." rows="6" required></textarea>
<br>
<input type="hidden" name="action" value="create">
<button type="submit">Create Post</button>
</form>
<hr>
<?php foreach ($posts as $post): ?>
<div>
<h2><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
<p><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></p>
<small>Posted on <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
<form method="POST" action="blog_post_handler.php">
<input type="hidden" name="action" value="update">
<input type="hidden" name="id" value="<?php echo $post['id']; ?>">
<input type="text" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
<br>
<textarea name="content" rows="4" required><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
<br>
<button type="submit">Update</button>
</form>
<form method="POST" action="blog_post_handler.php" onsubmit="return confirm('Delete this post?');">
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?php echo $post['id']; ?>">
<button type="submit">Delete</button>
</form>
</div>
<hr>
<?php endforeach; ?>
</body>
</html>

<?php
$logPath = __DIR__ . '/logs/blog_crud.log';
if (!is_dir(dirname($logPath))) {
    mkdir(dirname($logPath), 0700, true);
}
function log_action($message) {
    $path = __DIR__ . '/logs/blog_crud.log';
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0700, true);
    }
    $ts = date('Y-m-d H:i:s');
    file_put_contents($path, $ts . "\t" . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}
function get_pdo_connection() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db = getenv('DB_NAME') ?: 'blog';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $port = getenv('DB_PORT') ?: '3306';
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
}
function ensure_table_exists($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}
function sanitize_input($value) {
    $v = trim($value);
    $v = strip_tags($v);
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
$pdo = get_pdo_connection();
ensure_table_exists($pdo);
$action = isset($_POST['action']) ? $_POST['action'] : '';
try {
    if ($action === 'create') {
        $titleRaw = isset($_POST['title']) ? $_POST['title'] : '';
        $contentRaw = isset($_POST['content']) ? $_POST['content'] : '';
        if (empty($titleRaw) || empty($contentRaw)) {
            throw new Exception('Missing data');
        }
        $title = sanitize_input($titleRaw);
        $content = sanitize_input($contentRaw);
        $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
        $stmt->execute(['title' => $title, 'content' => $content]);
        log_action("CREATE: title=\"$title\"");
        $insertId = $pdo->lastInsertId();
        log_action("CREATE_ID: $insertId");
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $titleRaw = isset($_POST['title']) ? $_POST['title'] : '';
        $contentRaw = isset($_POST['content']) ? $_POST['content'] : '';
        if ($id <= 0 || empty($titleRaw) || empty($contentRaw)) {
            throw new Exception('Missing data');
        }
        $title = sanitize_input($titleRaw);
        $content = sanitize_input($contentRaw);
        $stmt = $pdo->prepare("UPDATE posts SET title = :title, content = :content WHERE id = :id");
        $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id]);
        log_action("UPDATE: id=$id");
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            throw new Exception('Missing id');
        }
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
        $stmt->execute(['id' => $id]);
        log_action("DELETE: id=$id");
    } else {
        throw new Exception('Invalid action');
    }
    header('Location: blog_post_module.php?status=success');
    exit;
} catch (Exception $e) {
    log_action("ERROR: " . $e->getMessage());
    header('Location: blog_post_module.php?status=error');
    exit;
}
?>