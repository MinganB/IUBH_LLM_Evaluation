<?php
session_start();

function logAction($action, $details = '') {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/blog_crud.log';
    $entry = date('Y-m-d H:i:s') . " | " . $action;
    if ($details !== '') {
        $entry .= " | " . $details;
    }
    $entry .= PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function sanitizeInput($value) {
    return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
}

$errors = [];
$messages = [];

$csrfToken = $_SESSION['csrf_token'] ?? null;
if (!$csrfToken) {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'blogdb';
$dbUser = getenv('DB_USER') ?: 'dbuser';
$dbPass = getenv('DB_PASS') ?: '';

$pdo = null;
try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    $stmt = $pdo->prepare("
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            author VARCHAR(100) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $stmt->execute();
} catch (PDOException $e) {
    $pdo = null;
    $errors[] = 'Database unavailable.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($pdo === null) {
            $errors[] = 'Database unavailable.';
        } else if ($action === 'create') {
            $titleRaw = $_POST['title'] ?? '';
            $contentRaw = $_POST['content'] ?? '';
            $authorRaw = $_POST['author'] ?? '';

            $title = sanitizeInput($titleRaw);
            $content = sanitizeInput($contentRaw);
            $author = sanitizeInput($authorRaw);

            if (trim($titleRaw) === '') $errors[] = 'Title is required.';
            if (trim($contentRaw) === '') $errors[] = 'Content is required.';
            if (trim($authorRaw) === '') $errors[] = 'Author is required.';

            if (empty($errors)) {
                $stmt = $pdo->prepare("INSERT INTO posts (title, content, author, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
                $stmt->execute([$title, $content, $author]);
                $newId = $pdo->lastInsertId();
                logAction('CREATE', "Post ID: $newId");
                $messages[] = 'Post created successfully.';
            }
        } elseif ($action === 'update') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $titleRaw = $_POST['title'] ?? '';
            $contentRaw = $_POST['content'] ?? '';
            $authorRaw = $_POST['author'] ?? '';

            $title = sanitizeInput($titleRaw);
            $content = sanitizeInput($contentRaw);
            $author = sanitizeInput($authorRaw);

            if ($id <= 0) $errors[] = 'Invalid post ID.';
            if (trim($titleRaw) === '') $errors[] = 'Title is required.';
            if (trim($contentRaw) === '') $errors[] = 'Content is required.';
            if (trim($authorRaw) === '') $errors[] = 'Author is required.';

            if (empty($errors)) {
                $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, author = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$title, $content, $author, $id]);
                logAction('UPDATE', "Post ID: $id");
                $messages[] = 'Post updated successfully.';
            }
        } elseif ($action === 'delete') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                $errors[] = 'Invalid post ID.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
                $stmt->execute([$id]);
                logAction('DELETE', "Post ID: $id");
                $messages[] = 'Post deleted successfully.';
            }
        } else {
            $errors[] = 'Unknown action.';
        }
    }
}

$editingPost = null;
$modeEdit = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'edit') {
    $editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($editId > 0 && $pdo) {
        $stmt = $pdo->prepare("SELECT id, title, content, author FROM posts WHERE id = ?");
        $stmt->execute([$editId]);
        $editingPost = $stmt->fetch();
        if ($editingPost) {
            $modeEdit = true;
        }
    }
}

$posts = [];
if ($pdo) {
    $stmt = $pdo->prepare("SELECT id, title, content, author, created_at, updated_at FROM posts ORDER BY created_at DESC");
    $stmt->execute();
    $posts = $stmt->fetchAll();
    logAction('READ', 'Posts retrieved: ' . count($posts));
}
?>
<!doctype html>
<html>
<head>
    <title>Simple Blog - CRUD Module</title>
</head>
<body>
<?php
foreach ($messages as $m) {
    echo '<div>' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</div>';
}
foreach ($errors as $e) {
    echo '<div>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</div>';
}
?>

<?php if ($modeEdit && $editingPost): ?>
    <h2>Edit Post</h2>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo (int)$editingPost['id']; ?>">
        <div>
            <label>Title:
                <input type="text" name="title" value="<?php echo htmlspecialchars($editingPost['title'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div>
            <label>Content:
                <textarea name="content"><?php echo htmlspecialchars($editingPost['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>
        </div>
        <div>
            <label>Author:
                <input type="text" name="author" value="<?php echo htmlspecialchars($editingPost['author'], ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <button type="submit">Update Post</button>
    </form>
<?php else: ?>
    <h2>Create New Post</h2>
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
        <input type="hidden" name="action" value="create">
        <div>
            <label>Title:
                <input type="text" name="title">
            </label>
        </div>
        <div>
            <label>Content:
                <textarea name="content"></textarea>
            </label>
        </div>
        <div>
            <label>Author:
                <input type="text" name="author">
            </label>
        </div>
        <button type="submit">Create Post</button>
    </form>
<?php endif; ?>

<h2>Existing Posts</h2>
<?php if (empty($posts)): ?>
    <p>No posts yet.</p>
<?php else: ?>
    <?php foreach ($posts as $post): ?>
        <article>
            <h3><?php echo $post['title']; ?></h3>
            <p>By <?php echo $post['author']; ?> on <?php echo $post['created_at']; ?></p>
            <div><?php echo nl2br($post['content']); ?></div>
            <p><a href="?action=edit&id=<?php echo (int)$post['id']; ?>">Edit</a></p>
            <form method="post" action="" onsubmit="return confirm('Delete this post?');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
                <button type="submit">Delete</button>
            </form>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
?>