<?php
$logFile = __DIR__ . '/blog_crud.log';

function logOperation($message) {
    global $logFile;
    $timestamp = (new DateTime())->format('Y-m-d H:i:s');
    $line = "[$timestamp] $message" . PHP_EOL;
    if (is_writable(dirname($logFile))) {
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}

function sanitizeInput($data) {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function getPdo() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'db_users';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        logOperation('DB connection error: ' . $e->getMessage());
        return null;
    }
}

$pdo = getPdo();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle POST actions: create, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create') {
        $title = isset($_POST['title']) ? sanitizeInput($_POST['title']) : '';
        $content = isset($_POST['content']) ? sanitizeInput($_POST['content']) : '';

        if ($pdo && $title !== '' && $content !== '') {
            try {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$title, $content]);
                logOperation('CREATE post id=' . $pdo->lastInsertId());
            } catch (Exception $e) {
                logOperation('CREATE error: ' . $e->getMessage());
            }
        } else {
            logOperation('CREATE validation failed: missing title or content or DB unavailable');
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $title = isset($_POST['title']) ? sanitizeInput($_POST['title']) : '';
        $content = isset($_POST['content']) ? sanitizeInput($_POST['content']) : '';

        if ($pdo && $id > 0 && $title !== '' && $content !== '') {
            try {
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $content, $id]);
                logOperation('UPDATE post id=' . $id);
            } catch (Exception $e) {
                logOperation('UPDATE error: ' . $e->getMessage());
            }
        } else {
            logOperation('UPDATE validation failed for id=' . $id);
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if ($pdo && $id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
                logOperation('DELETE post id=' . $id);
            } catch (Exception $e) {
                logOperation('DELETE error: ' . $e->getMessage());
            }
        } else {
            logOperation('DELETE validation failed for id=' . $id);
        }

        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Retrieve all posts for display
$posts = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
        $stmt->execute();
        $posts = $stmt->fetchAll();
    } catch (Exception $e) {
        logOperation('READ error: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Blog Post Management</title>
</head>
<body>
    <h1>Blog Post Management</h1>

    <section>
        <h2>Create New Post</h2>
        <form method="post" action="blog_post_handler.php" novalidate>
            <input type="hidden" name="action" value="create">
            <label>
                Title:
                <input type="text" name="title" required>
            </label><br><br>
            <label>
                Content:
                <textarea name="content" rows="6" cols="60" required></textarea>
            </label><br><br>
            <button type="submit">Create Post</button>
        </form>
    </section>

    <section>
        <h2>All Posts</h2>
        <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
                <div style="border:1px solid #ddd; padding:12px; margin:12px 0;">
                    <h3><?php echo $post['title']; ?></h3>
                    <div><?php echo nl2br($post['content']); ?></div>
                    <small>Posted on: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>

                    <details style="margin-top:8px;">
                        <summary>Update</summary>
                        <form method="post" action="blog_post_handler.php" style="margin-top:8px;">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                            <label>
                                Title:
                                <input type="text" name="title" value="<?php echo $post['title']; ?>" required>
                            </label><br><br>
                            <label>
                                Content:
                                <textarea name="content" rows="6" cols="60" required><?php echo $post['content']; ?></textarea>
                            </label><br><br>
                            <button type="submit">Update</button>
                        </form>
                    </details>

                    <form method="post" action="blog_post_handler.php" style="margin-top:8px;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                        <button type="submit" onclick="return confirm('Delete this post?')">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No posts found.</p>
        <?php endif; ?>
    </section>
</body>
</html>
?>