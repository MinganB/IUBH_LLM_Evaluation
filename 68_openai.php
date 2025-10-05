<?php
session_start();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'blogdb';
$user = getenv('DB_USER') ?: 'dbuser';
$pass = getenv('DB_PASS') ?: 'dbpass';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = null;
try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Exception $e) {
  http_response_code(500);
  echo 'Database connection error';
  exit;
}

function get_csrf_token() {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

$success = '';
$error = '';
$csrf_token = get_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $token = $_POST['csrf_token'] ?? '';
  if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo 'Invalid CSRF token';
    exit;
  }

  if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title === '' || $content === '') {
      $error = 'Title and content are required';
    } else {
      $stmt = $pdo->prepare('INSERT INTO blog_posts (title, content, created_at, updated_at) VALUES (:title, :content, NOW(), NOW())');
      $stmt->execute(['title' => $title, 'content' => $content]);
      $success = 'Post created';
    }
  } elseif ($action === 'update') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($id <= 0 || $title === '' || $content === '') {
      $error = 'Invalid input';
    } else {
      $stmt = $pdo->prepare('UPDATE blog_posts SET title = :title, content = :content, updated_at = NOW() WHERE id = :id');
      $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id]);
      $success = 'Post updated';
    }
  } elseif ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
      $error = 'Invalid ID';
    } else {
      $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
      $stmt->execute(['id' => $id]);
      $success = 'Post deleted';
    }
  } else {
    $error = 'Unknown action';
  }
}

$posts = [];
try {
  $stmt = $pdo->query('SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC');
  $posts = $stmt->fetchAll();
} catch (Exception $e) {
  $error = 'Failed to retrieve posts';
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Blog Posts</title>
</head>
<body>
<?php if ($success) { echo "<p>$success</p>"; } ?>
<?php if ($error) { echo "<p style='color:red;'>$error</p>"; } ?>

<form method="POST" action="blog_post_handler.php" autocomplete="off">
  <input type="hidden" name="action" value="create">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
  <h2>Create New Post</h2>
  <label>Title: <input type="text" name="title" required></label><br>
  <label>Content: <textarea name="content" rows="5" cols="60" required></textarea></label><br>
  <button type="submit">Create Post</button>
</form>

<h2>All Posts</h2>
<?php foreach ($posts as $p): ?>
  <div>
    <h3><?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
    <p><?php echo nl2br(htmlspecialchars($p['content'], ENT_QUOTES, 'UTF-8')); ?></p>
    <p><em>Created: <?php echo htmlspecialchars($p['created_at']); ?> | Updated: <?php echo htmlspecialchars($p['updated_at']); ?></em></p>
    <form method="POST" action="blog_post_handler.php">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
      <input type="text" name="title" value="<?php echo htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8'); ?>" required>
      <textarea name="content" rows="4" cols="40" required><?php echo htmlspecialchars($p['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
      <button type="submit">Update</button>
    </form>
    <form method="POST" action="blog_post_handler.php">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
      <button type="submit" onclick="return confirm('Delete this post?')">Delete</button>
    </form>
  </div>
<?php endforeach; ?>
</body>
</html>
?>