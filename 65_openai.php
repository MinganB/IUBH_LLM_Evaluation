<?php
session_start();

function getDb() {
  static $pdo;
  if ($pdo) return $pdo;
  $dbPath = __DIR__ . '/blog.db';
  $pdo = new PDO('sqlite:' . $dbPath);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
  )");
  return $pdo;
}

function ensureCsrfToken() {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function setFlash($msg) {
  $_SESSION['flash'] = $msg;
}
function getFlash() {
  $f = $_SESSION['flash'] ?? '';
  unset($_SESSION['flash']);
  return $f;
}
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo 'Invalid CSRF token';
    exit;
  }
  $action = $_POST['action'] ?? '';
  $pdo = getDb();
  try {
    if ($action === 'create') {
      $title = trim($_POST['title'] ?? '');
      $content = trim($_POST['content'] ?? '');
      if ($title === '' || $content === '') {
        setFlash('Title and content are required.');
      } else {
        $stmt = $pdo->prepare('INSERT INTO posts (title, content, created_at, updated_at) VALUES (?, ?, datetime("now"), datetime("now"))');
        $stmt->execute([$title, $content]);
        setFlash('Post created.');
      }
    } elseif ($action === 'update') {
      $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      $title = trim($_POST['title'] ?? '');
      $content = trim($_POST['content'] ?? '');
      if ($id <= 0 || $title === '' || $content === '') {
        setFlash('Invalid data for update.');
      } else {
        $stmt = $pdo->prepare('UPDATE posts SET title = ?, content = ?, updated_at = datetime("now") WHERE id = ?');
        $stmt->execute([$title, $content, $id]);
        setFlash('Post updated.');
      }
    } elseif ($action === 'delete') {
      $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      if ($id <= 0) {
        setFlash('Invalid post id.');
      } else {
        $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
        $stmt->execute([$id]);
        setFlash('Post deleted.');
      }
    } else {
      setFlash('Unknown action.');
    }
  } catch (Exception $e) {
    setFlash('Error: ' . $e->getMessage());
  }
  header('Location: blog_post_handler.php');
  exit;
}
$pdo = getDb();
$rows = $pdo->query('SELECT id, title, content, created_at, updated_at FROM posts ORDER BY created_at DESC')->fetchAll();
$csrf = ensureCsrfToken();
$flash = getFlash();
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Blog Post CRUD</title></head>
<body>
<?php if ($flash): ?>
<div><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<h2>Create New Post</h2>
<form method="POST" action="blog_post_handler.php">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
  <input type="hidden" name="action" value="create">
  <div>
     <label>Title</label><br>
     <input type="text" name="title" required>
  </div>
  <div>
     <label>Content</label><br>
     <textarea name="content" rows="5" cols="60" required></textarea>
  </div>
  <div>
     <button type="submit">Create Post</button>
  </div>
</form>

<h2>All Posts</h2>
<?php if (empty($rows)): ?>
<p>No posts yet.</p>
<?php else: ?>
<?php foreach ($rows as $post): ?>
<article>
  <form method="POST" action="blog_post_handler.php">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
    <div>
      <label>Title</label><br>
      <input type="text" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div>
      <label>Content</label><br>
      <textarea name="content" rows="6" cols="60" required><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
    </div>
    <div>
      <button type="submit">Update</button>
    </div>
  </form>
  <form method="POST" action="blog_post_handler.php" onsubmit="return confirm('Delete this post?');">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
    <button type="submit">Delete</button>
  </form>
  <div>
    Created: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?> |
    Updated: <?php echo htmlspecialchars($post['updated_at'], ENT_QUOTES, 'UTF-8'); ?>
  </div>
</article>
<?php endforeach; ?>
<?php endif; ?>
</body>
</html>
?>