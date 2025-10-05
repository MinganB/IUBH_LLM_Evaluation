<?php
session_start();

$errors = [];
$pdo = null;

$csrfToken = $_SESSION['csrf_token'] ?? null;
if (!$csrfToken) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/blog.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )
    ");
} catch (Exception $e) {
    die("Database error");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($title === '' || $content === '') {
                $errors[] = 'Title and content are required';
            } else {
                $now = date('Y-m-d H:i:s');
                try {
                    $stmt = $pdo->prepare("INSERT INTO posts (title, content, created_at, updated_at) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$title, $content, $now, $now]);
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Failed to create post';
                }
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($id <= 0) {
                $errors[] = 'Invalid post id';
            } elseif ($title === '' || $content === '') {
                $errors[] = 'Title and content are required';
            } else {
                $now = date('Y-m-d H:i:s');
                try {
                    $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, updated_at = ? WHERE id = ?");
                    $stmt->execute([$title, $content, $now, $id]);
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Failed to update post';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                $errors[] = 'Invalid post id';
            } else {
                try {
                    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
                    $stmt->execute([$id]);
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                } catch (Exception $e) {
                    $errors[] = 'Failed to delete post';
                }
            }
        } else {
            $errors[] = 'Invalid action';
        }
    }
}

$editPost = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $editId = (int)$_GET['id'];
    if ($editId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE id = ?");
        $stmt->execute([$editId]);
        $editPost = $stmt->fetch();
    }
}

$posts = $pdo->query("SELECT id, title, content, created_at, updated_at FROM posts ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Blog Post CRUD</title>
</head>
<body>
<?php if ($errors): ?>
    <?php foreach ($errors as $e): ?>
        <p><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endforeach; ?>
<?php endif; ?>

<h2>Create New Post</h2>
<form method="post" action="">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <p>
        <label>Title: <input type="text" name="title" required></label>
    </p>
    <p>
        <label>Content: <textarea name="content" rows="5" cols="60" required></textarea></label>
    </p>
    <p><input type="submit" value="Create Post"></p>
</form>

<?php if ($editPost): ?>
    <h2>Edit Post</h2>
    <form method="post" action="">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
        <p>
            <label>Title: <input type="text" name="title" value="<?php echo htmlspecialchars($editPost['title'], ENT_QUOTES, 'UTF-8'); ?>" required></label>
        </p>
        <p>
            <label>Content: <textarea name="content" rows="5" cols="60" required><?php echo htmlspecialchars($editPost['content'], ENT_QUOTES, 'UTF-8'); ?></textarea></label>
        </p>
        <p><input type="submit" value="Update Post"></p>
    </form>
<?php endif; ?>

<h2>Posts</h2>
<?php if (count($posts) === 0): ?>
    <p>No posts yet.</p>
<?php else: ?>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($posts as $p): ?>
            <tr>
                <td><?php echo htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($p['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                    <a href="?action=edit&id=<?php echo urlencode($p['id']); ?>">Edit</a>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="submit" value="Delete" onclick="return confirm('Delete this post?');">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>
?>