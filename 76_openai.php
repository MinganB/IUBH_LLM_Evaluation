<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = 'db_users';
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
  $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )");
  $stmt = $pdo->query("SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC");
  $posts = $stmt->fetchAll();
} catch (PDOException $e) {
  die("DB error: ".$e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Blog Posts</title>
</head>
<body>
<h2>Create New Post</h2>
<form action="blog_post_handler.php" method="POST">
  <input type="hidden" name="action" value="create">
  <label>Title:
    <input type="text" name="title" required>
  </label><br>
  <label>Content:
    <textarea name="content" rows="6" cols="60" required></textarea>
  </label><br>
  <button type="submit">Create Post</button>
</form>

<h2>Existing Posts</h2>
<?php if ($posts): ?>
  <?php foreach ($posts as $post): ?>
    <div>
      <h3><?=htmlspecialchars($post['title'])?></h3>
      <p><?=nl2br(htmlspecialchars($post['content']))?></p>
      <small>Created: <?=htmlspecialchars($post['created_at'])?> Updated: <?=htmlspecialchars($post['updated_at'])?></small>
      <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?=htmlspecialchars($post['id'])?>">
        <label>Title:
          <input type="text" name="title" value="<?=htmlspecialchars($post['title'])?>" required>
        </label><br>
        <label>Content:
          <textarea name="content" rows="4" cols="60" required><?=htmlspecialchars($post['content'])?></textarea>
        </label><br>
        <button type="submit">Update</button>
      </form>
      <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?=htmlspecialchars($post['id'])?>">
        <button type="submit" onclick="return confirm('Delete this post?')">Delete</button>
      </form>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <p>No posts yet.</p>
<?php endif; ?>
</body>
</html>

<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = 'db_users';
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
  $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )");
} catch (PDOException $e) {
  http_response_code(500);
  exit("DB error: ".$e->getMessage());
}

$action = $_POST['action'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['create','update','delete'])) {
  try {
    if ($action === 'create') {
      $title = $_POST['title'] ?? '';
      $content = $_POST['content'] ?? '';
      if (trim($title) === '' || trim($content) === '') {
        header('Location: index.php?status=error&message=Missing+title+or+content');
        exit;
      }
      $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
      $stmt->execute(['title' => $title, 'content' => $content]);
    } elseif ($action === 'update') {
      $id = (int)($_POST['id'] ?? 0);
      $title = $_POST['title'] ?? '';
      $content = $_POST['content'] ?? '';
      if ($id <= 0 || trim($title) === '' || trim($content) === '') {
        header('Location: index.php?status=error&message=Invalid+input');
        exit;
      }
      $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content, updated_at = NOW() WHERE id = :id");
      $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id]);
    } elseif ($action === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      if ($id <= 0) {
        header('Location: index.php?status=error&message=Invalid+id');
        exit;
      }
      $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
      $stmt->execute(['id' => $id]);
    }
    header('Location: index.php?status=success&action='.$action);
    exit;
  } catch (PDOException $e) {
    header('Location: index.php?status=error&message='.urlencode($e->getMessage()));
    exit;
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'read') {
  try {
    $stmt = $pdo->query("SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'posts' => $posts]);
    exit;
  } catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
  }
}
header('Location: index.php');
exit;
?>