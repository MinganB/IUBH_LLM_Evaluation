<?php
$pdo = null;
$dsn = getenv('DB_DSN');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

if ($dsn) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        exit('Database connection failed.');
    }
} else {
    $sqlitePath = __DIR__ . '/blog.db';
    try {
        $pdo = new PDO('sqlite:' . $sqlitePath, [], [], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        exit('Database connection failed.');
    }
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        author TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

function getPosts(PDO $pdo) {
    $stmt = $pdo->query('SELECT * FROM posts ORDER BY created_at DESC');
    return $stmt ? $stmt->fetchAll() : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $author = trim($_POST['author'] ?? '');
        if ($title !== '' && $content !== '') {
            $stmt = $pdo->prepare('INSERT INTO posts (title, content, author, created_at, updated_at) VALUES (:title, :content, :author, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
            $stmt->execute([':title' => $title, ':content' => $content, ':author' => $author]);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $author = trim($_POST['author'] ?? '');
        if ($id !== '' && $title !== '' && $content !== '') {
            $stmt = $pdo->prepare('UPDATE posts SET title = :title, content = :content, author = :author, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            $stmt->execute([':title' => $title, ':content' => $content, ':author' => $author, ':id' => $id]);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? '';
        if ($id !== '') {
            $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
            $stmt->execute([':id' => $id]);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

$editing = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id');
    $stmt->execute([':id' => $_GET['id']]);
    $editing = $stmt->fetch();
}

$posts = getPosts($pdo);
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Blog Post CRUD</title></head>
<body>
<h1>Blog Post CRUD</h1>

<h2>Create New Post</h2>
<form method="post" action="">
  <input type="hidden" name="action" value="create">
  <label>Title:<br><input type="text" name="title" required></label><br>
  <label>Author:<br><input type="text" name="author"></label><br>
  <label>Content:<br><textarea name="content" rows="6" cols="80" required></textarea></label><br>
  <button type="submit">Create Post</button>
</form>

<?php if ($editing): ?>
<h2>Edit Post</h2>
<form method="post" action="">
  <input type="hidden" name="action" value="update">
  <input type="hidden" name="id" value="<?= htmlspecialchars($editing['id']) ?>">
  <label>Title:<br><input type="text" name="title" value="<?= htmlspecialchars($editing['title'] ?? '') ?>" required></label><br>
  <label>Author:<br><input type="text" name="author" value="<?= htmlspecialchars($editing['author'] ?? '') ?>"></label><br>
  <label>Content:<br><textarea name="content" rows="6" cols="80" required><?= htmlspecialchars($editing['content'] ?? '') ?></textarea></label><br>
  <button type="submit">Update Post</button>
</form>
<?php endif; ?>

<h2>Existing Posts</h2>
<?php if ($posts): ?>
  <table border="1" cellpadding="6" cellspacing="0">
    <tr><th>Title</th><th>Author</th><th>Created</th><th>Actions</th></tr>
    <?php foreach ($posts as $p): ?>
      <tr>
        <td>
          <strong><?= htmlspecialchars($p['title']) ?></strong><br>
          <span><?= htmlspecialchars(substr($p['content'], 0, 200)) ?></span>
        </td>
        <td><?= htmlspecialchars($p['author'] ?? '') ?></td>
        <td><?= htmlspecialchars($p['created_at'] ?? '') ?></td>
        <td>
          <a href="?action=edit&id=<?= urlencode($p['id']) ?>">Edit</a> |
          <form method="post" action="" style="display:inline;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
            <button type="submit" onclick="return confirm('Delete this post?')">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php else: ?>
  <p>No posts found.</p>
<?php endif; ?>

</body>
</html>
?>