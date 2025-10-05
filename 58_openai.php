<?php
session_start();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'blog';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  http_response_code(500);
  echo 'Database connection failed';
  exit;
}

$pdo->exec("
  CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )
");

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$error = '';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
     http_response_code(400);
     $error = 'Invalid CSRF token';
  } else {
     $post_action = $_POST['action'] ?? '';
     if ($post_action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title === '' || $content === '') {
           $error = 'Title and content are required';
        } else {
           $stmt = $pdo->prepare("INSERT INTO posts (title, content, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
           $stmt->execute([$title, $content]);
           header("Location: " . $_SERVER['PHP_SELF']);
           exit;
        }
     } elseif ($post_action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($id <= 0 || $title === '' || $content === '') {
           $error = 'Invalid input for update';
        } else {
           $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
           $stmt->execute([$title, $content, $id]);
           header("Location: " . $_SERVER['PHP_SELF']);
           exit;
        }
     }
  }
}

$view_post = null;
$edit_post = null;

if (!empty($_GET['action']) && isset($_GET['id'])) {
  $aid = (int)$_GET['id'];
  if ($_GET['action'] === 'view') {
     $stmt = $pdo->prepare("SELECT id, title, content, created_at, updated_at FROM posts WHERE id = ?");
     $stmt->execute([$aid]);
     $view_post = $stmt->fetch();
  } elseif ($_GET['action'] === 'edit') {
     $stmt = $pdo->prepare("SELECT id, title, content FROM posts WHERE id = ?");
     $stmt->execute([$aid]);
     $edit_post = $stmt->fetch();
  } elseif ($_GET['action'] === 'delete') {
     $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
     $stmt->execute([$aid]);
     header("Location: " . $_SERVER['PHP_SELF']);
     exit;
  }
}

$posts = $pdo->query("SELECT id, title, content, created_at, updated_at FROM posts ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Blog Post Management</title>
</head>
<body>
  <h1>Blog Posts</h1>

  <?php if ($error): ?>
    <p style="color:#d00;"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>

  <h2>Create New Post</h2>
  <form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <input type="hidden" name="action" value="create">
    <p>
      <input type="text" name="title" placeholder="Title" required style="width:100%">
    </p>
    <p>
      <textarea name="content" placeholder="Content" required rows="6" style="width:100%"></textarea>
    </p>
    <p>
      <button type="submit">Create Post</button>
    </p>
  </form>

  <?php if ($edit_post): ?>
    <h2>Edit Post</h2>
    <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$edit_post['id'] ?>">
      <p>
        <input type="text" name="title" value="<?= htmlspecialchars($edit_post['title']) ?>" required style="width:100%">
      </p>
      <p>
        <textarea name="content" required rows="6" style="width:100%"><?= htmlspecialchars($edit_post['content']) ?></textarea>
      </p>
      <p>
        <button type="submit">Update Post</button>
      </p>
    </form>
  <?php endif; ?>

  <?php if ($view_post): ?>
    <h2><?= htmlspecialchars($view_post['title']) ?></h2>
    <p>Created: <?= htmlspecialchars($view_post['created_at']) ?> | Updated: <?= htmlspecialchars($view_post['updated_at']) ?></p>
    <div><?= nl2br(htmlspecialchars($view_post['content'])) ?></div>
    <p><a href="<?= $_SERVER['PHP_SELF'] ?>">Back to list</a></p>
  <?php endif; ?>

  <h2>All Posts</h2>
  <ul>
  <?php foreach ($posts as $p): ?>
    <li>
      <strong><?= htmlspecialchars($p['title']) ?></strong>
      <span> - <?= htmlspecialchars(substr(strip_tags($p['content']), 0, 200)) .
        (strlen(strip_tags($p['content'])) > 200 ? '...' : '') ?></span>
      <span> (Created: <?= htmlspecialchars($p['created_at']) ?>)</span>
      <div>
        <a href="<?= $_SERVER['PHP_SELF'] ?>?action=view&id=<?= (int)$p['id'] ?>">View</a> |
        <a href="<?= $_SERVER['PHP_SELF'] ?>?action=edit&id=<?= (int)$p['id'] ?>">Edit</a> |
        <a href="<?= $_SERVER['PHP_SELF'] ?>?action=delete&id=<?= (int)$p['id'] ?>" onclick="return confirm('Delete this post?');">Delete</a>
      </div>
    </li>
  <?php endforeach; ?>
  </ul>
</body>
</html>
?>