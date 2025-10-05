<?php
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$dsn = "mysql:host=$host;dbname=db_users;charset=utf8mb4";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $pdo->exec("
  CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )
  ");
  $stmt = $pdo->query("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
  $posts = $stmt->fetchAll();
} catch (PDOException $e) {
  $posts = [];
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Blog Posts</title>
</head>
<body>
<?php if (isset($_GET['status'])): ?>
  <div><?php echo htmlspecialchars($_GET['status']); ?></div>
<?php endif; ?>

<h2>Create New Blog Post</h2>
<form action="blog_post_handler.php" method="post">
  <input type="hidden" name="action" value="create">
  <label>Title</label><br>
  <input type="text" name="title" required><br>
  <label>Content</label><br>
  <textarea name="content" rows="5" required></textarea><br>
  <button type="submit">Create Post</button>
</form>

<h2>All Posts</h2>
<?php foreach ($posts as $post): ?>
  <div>
    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
    <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
    <p><em><?php echo htmlspecialchars($post['created_at']); ?></em></p>

    <form action="blog_post_handler.php" method="post">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
      <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
      <textarea name="content" rows="4" required><?php echo htmlspecialchars($post['content']); ?></textarea>
      <button type="submit">Update</button>
    </form>

    <form action="blog_post_handler.php" method="post" onsubmit="return confirm('Delete this post?');">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
      <button type="submit">Delete</button>
    </form>
  </div>
  <hr>
<?php endforeach; ?>
</body>
</html>
<?php
?><?php
$host = $_ENV['DB_HOST'] ?? 'localhost';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$dsn = "mysql:host=$host;dbname=db_users;charset=utf8mb4";

try {
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  $pdo->exec("
  CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  )
  ");
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    if ($title !== '' && $content !== '') {
      $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (?, ?)");
      $stmt->execute([$title, $content]);
      header("Location: index.php?status=created");
      exit;
    } else {
      header("Location: index.php?status=error");
      exit;
    }
  } elseif ($action === 'update') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    if ($id > 0 && $title !== '' && $content !== '') {
      $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
      $stmt->execute([$title, $content, $id]);
      header("Location: index.php?status=updated");
      exit;
    } else {
      header("Location: index.php?status=error");
      exit;
    }
  } elseif ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
      $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
      $stmt->execute([$id]);
      header("Location: index.php?status=deleted");
      exit;
    } else {
      header("Location: index.php?status=error");
      exit;
    }
  } else {
    header("Location: index.php?status=error");
    exit;
  }
} catch (PDOException $e) {
  header("Location: index.php?status=error");
  exit;
}
?>