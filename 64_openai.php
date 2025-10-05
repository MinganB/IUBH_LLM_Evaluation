<?php
class Db {
  private static $pdo = null;
  public static function getPdo() {
    if (self::$pdo !== null) return self::$pdo;
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) {
      mkdir($dir, 0777, true);
    }
    $dbFile = $dir . '/blog.db';
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
      CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
      )
    ");
    self::$pdo = $pdo;
    return self::$pdo;
  }
}
?><?php
require __DIR__ . '/db.php';
$pdo = Db::getPdo();
$stmt = $pdo->prepare("SELECT id, title, content, created_at, updated_at FROM posts ORDER BY id DESC");
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$msg = '';
if (isset($_GET['msg'])) {
  $msg = $_GET['msg'];
}
?>
<!DOCTYPE html>
<html>
<head><title>Blog</title></head>
<body>
<?php if ($msg !== '') { echo '<p>' . htmlspecialchars($msg) . '</p>'; } ?>
<h2>Create New Post</h2>
<form action="blog_post_handler.php" method="POST">
  <input type="hidden" name="action" value="create">
  <div>
    <input type="text" name="title" placeholder="Title" required>
  </div>
  <div>
    <textarea name="content" placeholder="Content" required></textarea>
  </div>
  <div>
    <button type="submit">Create Post</button>
  </div>
</form>

<h2>All Posts</h2>
<?php foreach ($posts as $post): ?>
  <div style="border:1px solid #ccc; padding:10px; margin:10px 0;">
    <h3><?php echo htmlspecialchars($post['title']); ?></h3>
    <div><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
    <small>Created: <?php echo $post['created_at']; ?> | Updated: <?php echo $post['updated_at']; ?></small>

    <h4>Update Post</h4>
    <form action="blog_post_handler.php" method="POST">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
      <div>
        <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
      </div>
      <div>
        <textarea name="content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
      </div>
      <div>
        <button type="submit">Update</button>
      </div>
    </form>

    <h4>Delete Post</h4>
    <form action="blog_post_handler.php" method="POST" onsubmit="return confirm('Delete this post?');">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
      <button type="submit">Delete</button>
    </form>
  </div>
<?php endforeach; ?>
</body>
</html><?php
require __DIR__ . '/db.php';
$pdo = Db::getPdo();

$action = $_POST['action'] ?? '';
if ($action === 'create') {
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  if ($title !== '' && $content !== '') {
    $stmt = $pdo->prepare("INSERT INTO posts (title, content, created_at, updated_at) VALUES (:title, :content, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
    $stmt->execute([':title'=>$title, ':content'=>$content]);
    header('Location: index.php?msg=' . urlencode('Post created successfully.'));
    exit;
  } else {
    header('Location: index.php?msg=' . urlencode('Title and content are required.'));
    exit;
  }
} elseif ($action === 'update') {
  $id = $_POST['id'] ?? '';
  $title = $_POST['title'] ?? '';
  $content = $_POST['content'] ?? '';
  if ($id !== '' && $title !== '' && $content !== '') {
    $stmt = $pdo->prepare("UPDATE posts SET title=:title, content=:content, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
    $stmt->execute([':title'=>$title, ':content'=>$content, ':id'=>$id]);
    header('Location: index.php?msg=' . urlencode('Post updated successfully.'));
    exit;
  } else {
    header('Location: index.php?msg=' . urlencode('All fields are required for update.'));
    exit;
  }
} elseif ($action === 'delete') {
  $id = $_POST['id'] ?? '';
  if ($id !== '') {
    $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
    $stmt->execute([':id'=>$id]);
    header('Location: index.php?msg=' . urlencode('Post deleted successfully.'));
    exit;
  } else {
    header('Location: index.php?msg=' . urlencode('Invalid post ID.'));
    exit;
  }
} else {
  header('Location: index.php');
  exit;
}
?>