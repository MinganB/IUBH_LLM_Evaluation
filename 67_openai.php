<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/blog.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$stmt = $pdo->query("SELECT id, title, content, created_at FROM posts ORDER BY created_at DESC");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Blog</title>
</head>
<body>
  <h1>Blog Posts</h1>

  <form method="POST" action="blog_post_handler.php">
    <input type="text" name="title" placeholder="Title" required />
    <br/>
    <textarea name="content" placeholder="Content" rows="5" cols="60" required></textarea>
    <br/>
    <input type="hidden" name="action" value="create" />
    <button type="submit">Create Post</button>
  </form>

  <hr/>

  <?php foreach ($posts as $post): ?>
    <div class="post">
      <form method="POST" action="blog_post_handler.php">
        <input type="hidden" name="action" value="update" />
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">
        <input type="text" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES); ?>" required />
        <br/>
        <textarea name="content" rows="4" cols="60" required><?php echo htmlspecialchars($post['content'], ENT_QUOTES); ?></textarea>
        <br/>
        <button type="submit">Update</button>
      </form>
      <form method="POST" action="blog_post_handler.php" onsubmit="return confirm('Delete this post?');">
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES); ?>">
        <button type="submit">Delete</button>
      </form>
    </div>
    <hr/>
  <?php endforeach; ?>

</body>
</html>
<?php
// blog_post_handler.php
$pdo = new PDO('sqlite:' . __DIR__ . '/blog.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE IF NOT EXISTS posts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  content TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$action = $_POST['action'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
try {
  if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    if ($title !== '' && $content !== '') {
      $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (?, ?)");
      $stmt->execute([$title, $content]);
    }
  } elseif ($action === 'update') {
    $title = trim($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    if ($id > 0 && $title !== '' && $content !== '') {
      $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
      $stmt->execute([$title, $content, $id]);
    }
  } elseif ($action === 'delete') {
    if ($id > 0) {
      $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
      $stmt->execute([$id]);
    }
  }
} catch (Exception $e) {
}
header('Location: index.php');
exit;
?>