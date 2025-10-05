<?php
$logFile = __DIR__ . '/blog_crud.log';

function log_crud($action, $details = '', $logPath = null) {
    if (!$logPath) { $logPath = __DIR__ . '/blog_crud.log'; }
    $entry = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($action) . ($details !== '' ? ' - ' . $details : '');
    if ($fp = @fopen($logPath, 'a')) {
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $entry . PHP_EOL);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'blogdb';
$user = getenv('DB_USER') ?: 'bloguser';
$password = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    log_crud('ERROR', 'DB connection failed: ' . substr($e->getMessage(), 0, 200), $logFile);
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Blog Admin</title></head><body>";
    echo "<p>An error occurred. Please try again later.</p>";
    echo "</body></html>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    if ($action === 'create') {
        $titleRaw = isset($_POST['title']) ? trim($_POST['title']) : '';
        $contentRaw = isset($_POST['content']) ? trim($_POST['content']) : '';

        $title = strip_tags($titleRaw);
        $content = strip_tags($contentRaw);

        if ($title === '' || $content === '') {
            // Invalid input, ignore and continue
            log_crud('CREATE_VALIDATION_FAIL', 'title or content empty', $logFile);
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (?, ?)");
                $stmt->execute([$title, $content]);
                $newId = $pdo->lastInsertId();
                log_crud('CREATE', 'Post ID ' . $newId, $logFile);
            } catch (PDOException $e) {
                log_crud('ERROR', 'CREATE failed: ' . substr($e->getMessage(), 0, 200), $logFile);
            }
        }
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $titleRaw = isset($_POST['title']) ? trim($_POST['title']) : '';
        $contentRaw = isset($_POST['content']) ? trim($_POST['content']) : '';

        $title = strip_tags($titleRaw);
        $content = strip_tags($contentRaw);

        if ($id > 0 && $title !== '' && $content !== '') {
            try {
                $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $content, $id]);
                log_crud('UPDATE', 'Post ID ' . $id, $logFile);
            } catch (PDOException $e) {
                log_crud('ERROR', 'UPDATE failed: ' . substr($e->getMessage(), 0, 200), $logFile);
            }
        } else {
            log_crud('UPDATE_VALIDATION_FAIL', 'Invalid input or ID', $logFile);
        }
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
                $stmt->execute([$id]);
                log_crud('DELETE', 'Post ID ' . $id, $logFile);
            } catch (PDOException $e) {
                log_crud('ERROR', 'DELETE failed: ' . substr($e->getMessage(), 0, 200), $logFile);
            }
        } else {
            log_crud('DELETE_VALIDATION_FAIL', 'Invalid ID', $logFile);
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT id, title, content, created_at, updated_at FROM posts ORDER BY created_at DESC");
    $stmt->execute();
    $posts = $stmt->fetchAll();
    log_crud('READ', 'posts_count=' . count($posts), $logFile);
} catch (PDOException $e) {
    log_crud('ERROR', 'READ failed: ' . substr($e->getMessage(), 0, 200), $logFile);
    $posts = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Blog Posts Administration</title>
</head>
<body>
<h1>Blog Posts Administration</h1>

<h2>Create New Post</h2>
<form method="post" action="">
    <input type="hidden" name="action" value="create">
    <label>Title:<br>
        <input type="text" name="title" required maxlength="255" style="width: 100%;">
    </label><br><br>
    <label>Content:<br>
        <textarea name="content" rows="6" required style="width: 100%;"></textarea>
    </label><br><br>
    <button type="submit">Create Post</button>
</form>

<hr>

<h2>Existing Posts</h2>
<?php if (empty($posts)): ?>
<p>No posts found.</p>
<?php else: ?>
<?php foreach ($posts as $post): ?>
<div style="border:1px solid #ccc; padding:10px; margin-bottom:10px;">
    <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
    <div><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></div>
    <p style="color:#666; font-size:12px;">
        Created: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?>
        <?php if ($post['updated_at']): ?>
        | Updated: <?php echo htmlspecialchars($post['updated_at'], ENT_QUOTES, 'UTF-8'); ?>
        <?php endif; ?>
    </p>

    <form method="post" action="" style="margin-top:8px;">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <label>Title:
            <input type="text" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255" style="width: 60%;">
        </label><br><br>
        <label>Content:
            <textarea name="content" rows="4" style="width: 60%;"><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
        </label><br><br>
        <button type="submit" style="margin-right:8px;">Update</button>
    </form>

    <form method="post" action="" onsubmit="return confirm('Delete this post?');" style="margin-top:8px;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <button type="submit" style="background:#f44; color:white;">Delete</button>
    </form>
</div>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
?>