<?php
session_start();

function getPdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $db = getenv('DB_NAME') ?: 'blogdb';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function ensureSchema(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            author VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
}

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function getAllPosts(PDO $pdo): array {
    $stmt = $pdo->prepare('SELECT id, title, content, author, created_at, updated_at FROM posts ORDER BY created_at DESC');
    $stmt->execute();
    return $stmt->fetchAll();
}

function insertPost(PDO $pdo, string $title, string $content, string $author): void {
    $stmt = $pdo->prepare('INSERT INTO posts (title, content, author) VALUES (?, ?, ?)');
    $stmt->execute([$title, $content, $author]);
}

function updatePost(PDO $pdo, int $id, string $title, string $content, string $author): void {
    $stmt = $pdo->prepare('UPDATE posts SET title = ?, content = ?, author = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    $stmt->execute([$title, $content, $author, $id]);
}

function deletePost(PDO $pdo, int $id): void {
    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$id]);
}

$pdo = null;
$errors = [];

try {
    $pdo = getPdo();
    ensureSchema($pdo);
} catch (Exception $e) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$csrfToken = generateCsrfToken();

if ($action === 'create') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $author = isset($_POST['author']) ? trim($_POST['author']) : '';

        if ($title === '') $errors[] = 'Title is required';
        if ($content === '') $errors[] = 'Content is required';
        if ($author === '') $errors[] = 'Author is required';

        if (empty($errors)) {
            insertPost($pdo, $title, $content, $author);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

if ($action === 'update') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $author = isset($_POST['author']) ? trim($_POST['author']) : '';

        if ($id <= 0) $errors[] = 'Invalid post id';
        if ($title === '') $errors[] = 'Title is required';
        if ($content === '') $errors[] = 'Content is required';
        if ($author === '') $errors[] = 'Author is required';

        if (empty($errors)) {
            updatePost($pdo, $id, $title, $content, $author);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

if ($action === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token';
    } else {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            $errors[] = 'Invalid post id';
        } else {
            deletePost($pdo, $id);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
}

$posts = getAllPosts($pdo);

$editPost = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    if ($editId > 0) {
        $stmt = $pdo->prepare('SELECT id, title, content, author FROM posts WHERE id = ?');
        $stmt->execute([$editId]);
        $editPost = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Blog Admin - Posts</title>
</head>
<body>
    <h1>Blog Post Management</h1>

    <?php if (!empty($errors)): ?>
        <div>
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= h($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2>Create New Post</h2>
    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <input type="hidden" name="csrf_token" value="<?= h($csrfToken); ?>">
        <input type="hidden" name="action" value="create">
        <div>
            <label>Title</label><br>
            <input type="text" name="title" value="<?= isset($_POST['title']) ? h($_POST['title']) : ''; ?>" required>
        </div>
        <div>
            <label>Content</label><br>
            <textarea name="content" rows="5" cols="60" required><?= isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
        </div>
        <div>
            <label>Author</label><br>
            <input type="text" name="author" value="<?= isset($_POST['author']) ? h($_POST['author']) : ''; ?>" required>
        </div>
        <div>
            <button type="submit">Create Post</button>
        </div>
    </form>

    <?php if ($editPost): ?>
        <h2>Edit Post</h2>
        <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= (int)$editPost['id']; ?>">
            <div>
                <label>Title</label><br>
                <input type="text" name="title" value="<?= h($editPost['title']); ?>" required>
            </div>
            <div>
                <label>Content</label><br>
                <textarea name="content" rows="5" cols="60" required><?= h($editPost['content']); ?></textarea>
            </div>
            <div>
                <label>Author</label><br>
                <input type="text" name="author" value="<?= h($editPost['author']); ?>" required>
            </div>
            <div>
                <button type="submit">Update Post</button>
                <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>">Cancel</a>
            </div>
        </form>
    <?php endif; ?>

    <h2>All Posts</h2>
    <?php if (!empty($posts)): ?>
        <ul>
            <?php foreach ($posts as $p): ?>
                <li>
                    <h3><?= h($p['title']); ?></h3>
                    <div><?= nl2br(h($p['content'])); ?></div>
                    <p>By <?= h($p['author']); ?> on <?= date('Y-m-d H:i', strtotime($p['created_at'])); ?></p>
                    <p>Last updated: <?= $p['updated_at'] ? date('Y-m-d H:i', strtotime($p['updated_at'])) : '—'; ?></p>
                    <form method="get" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display:inline;">
                        <input type="hidden" name="edit_id" value="<?= (int)$p['id']; ?>">
                        <button type="submit">Edit</button>
                    </form>
                    <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="display:inline;" onsubmit="return confirm('Delete this post?');">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$p['id']; ?>">
                        <button type="submit">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>No posts found.</p>
    <?php endif; ?>
</body>
</html>
?>