<?php
function getDbConnection() {
    $driver = getenv('DB_DRIVER');
    $pdo = null;
    if ($driver && in_array(strtolower($driver), ['mysql', 'mariadb'], true)) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'blog';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } else {
        $dbPath = __DIR__ . '/data/blog.db';
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $dsn = 'sqlite:' . $dbPath;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}
function initDatabase(PDO $pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $sql = "CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )";
    } else {
        $sql = "CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )";
    }
    $pdo->exec($sql);
}
function sanitizeInput($value) {
    if ($value === null) return null;
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}
function logOperation($operation, $details = '') {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/blog_crud.log';
    $entry = date('Y-m-d H:i:s') . " - " . strtoupper($operation) . " - " . $details . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
$pdo = null;
$posts = [];
try {
    $pdo = getDbConnection();
    initDatabase($pdo);
    $stmt = $pdo->prepare("SELECT id, title, content, created_at, updated_at FROM posts ORDER BY created_at DESC");
    $stmt->execute();
    $posts = $stmt->fetchAll();
    logOperation('READ', 'posts_count=' . count($posts));
} catch (Exception $e) {
    $posts = [];
    logOperation('READ', 'error');
}
?><!DOCTYPE html>
<html>
<head>
    <title>Blog Admin</title>
</head>
<body>
<?php
$status = $_GET['status'] ?? '';
if ($status) {
    echo '<p>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</p>';
}
?>
<h1>Create New Post</h1>
<form action="blog_post_handler.php" method="POST">
    <input type="hidden" name="action" value="create">
    <label for="title">Title</label><br>
    <input type="text" id="title" name="title" required maxlength="255"><br><br>
    <label for="content">Content</label><br>
    <textarea id="content" name="content" rows="6" cols="60" required></textarea><br><br>
    <button type="submit">Create Post</button>
</form>

<hr>

<h2>All Posts</h2>
<?php foreach ($posts as $post): ?>
<article style="margin-bottom:20px;">
    <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
    <div><?php echo nl2br($post['content']); ?></div>
    <small>Created: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?> | Updated: <?php echo htmlspecialchars($post['updated_at'], ENT_QUOTES, 'UTF-8'); ?></small>

    <form action="blog_post_handler.php" method="POST" style="margin-top:8px;">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <div>
            <label for="title_<?php echo $post['id']; ?>">Title</label>
            <input type="text" id="title_<?php echo $post['id']; ?>" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
        </div>
        <div>
            <label for="content_<?php echo $post['id']; ?>">Content</label>
            <textarea id="content_<?php echo $post['id']; ?>" name="content" rows="6" cols="60" required><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <button type="submit">Update</button>
    </form>

    <form action="blog_post_handler.php" method="POST" style="margin-top:4px;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" onclick="return confirm('Delete this post?')">Delete</button>
    </form>
</article>
<hr>
<?php endforeach; ?>
</body>
</html> <?php
function getDbConnectionPostHandler() {
    return getDbConnection();
}
?><?php
function getDbConnectionForHandler() {
    return getDbConnection();
}
?><?php
// blog_post_handler.php content will follow in the next file when loaded separately
?><?php
// End of index.php
?><?php
// blog_post_handler.php
?><?php
function getDbConnectionHandler() {
    $driver = getenv('DB_DRIVER');
    $pdo = null;
    if ($driver && in_array(strtolower($driver), ['mysql','mariadb'], true)) {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'blog';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } else {
        $dbPath = __DIR__ . '/data/blog.db';
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
       	}
        $dsn = 'sqlite:' . $dbPath;
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }
    return $pdo;
}
function initDatabaseHandler(PDO $pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $sql = "CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )";
    } else {
        $sql = "CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )";
    }
    $pdo->exec($sql);
}
function sanitizeInputHandler($value) {
    if ($value === null) return null;
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}
function logOperationHandler($operation, $details = '') {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/blog_crud.log';
    $entry = date('Y-m-d H:i:s') . " - " . strtoupper($operation) . " - " . $details . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
$action = $_POST['action'] ?? '';
$pdo = null;
try {
    $pdo = getDbConnectionHandler();
    initDatabaseHandler($pdo);
    if ($action === 'create') {
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $sanTitle = sanitizeInputHandler($title);
        $sanContent = sanitizeInputHandler($content);
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("INSERT INTO posts (title, content, created_at, updated_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sanTitle, $sanContent, $now, $now]);
        $id = $pdo->lastInsertId();
        logOperationHandler('CREATE', 'id=' . $id . ', title=' . $sanTitle);
        header('Location: index.php?status=created');
        exit;
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        if (!is_numeric($id)) {
            header('Location: index.php?status=error');
            exit;
        }
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        $sanTitle = sanitizeInputHandler($title);
        $sanContent = sanitizeInputHandler($content);
        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, updated_at = ? WHERE id = ?");
        $stmt->execute([$sanTitle, $sanContent, $now, $id]);
        logOperationHandler('UPDATE', 'id=' . $id);
        header('Location: index.php?status=updated');
        exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if (!is_numeric($id)) {
            header('Location: index.php?status=error');
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        logOperationHandler('DELETE', 'id=' . $id);
        header('Location: index.php?status=deleted');
        exit;
    } else {
        header('Location: index.php?status=invalid');
        exit;
    }
} catch (Exception $e) {
    logOperationHandler('ERROR', $e->getMessage());
    header('Location: index.php?status=error');
    exit;
}
?>