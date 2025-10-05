<?php
session_start();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = 'db_users';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASSWORD'] ?? '';

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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$status = $_GET['status'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrfToken) {
        http_response_code(403);
        echo 'Invalid CSRF token';
        exit;
    }

    $action = $_POST['action'] ?? 'read';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($title !== '' && $content !== '') {
            $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (?, ?)");
            $stmt->execute([$title, $content]);
            header("Location: blog_post_handler.php?status=created");
            exit;
        } else {
            header("Location: blog_post_handler.php?status=invalid_input");
            exit;
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        if ($id > 0 && $title !== '' && $content !== '') {
            $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $id]);
            header("Location: blog_post_handler.php?status=updated");
            exit;
        } else {
            header("Location: blog_post_handler.php?status=invalid_input");
            exit;
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: blog_post_handler.php?status=deleted");
            exit;
        } else {
            header("Location: blog_post_handler.php?status=invalid_input");
            exit;
        }
    }
}

$posts = [];
try {
    $stmt = $pdo->query("SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    $posts = [];
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8"/>
<title>Blog Post CRUD</title>
</head>
<body>
<h1>Blog Post CRUD</h1>

<?php if ($status): ?>
<p>Status: <?php echo htmlspecialchars($status); ?></p>
<?php endif; ?>

<h2>Create New Post</h2>
<form method="POST" action="blog_post_handler.php">
  <input type="hidden" name="action" value="create" />
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>" />
  <div>
    <label>Title</label>
    <input type="text" name="title" required />
  </div>
  <div>
    <label>Content</label>
    <textarea name="content" rows="5" required></textarea>
  </div>
  <div>
    <button type="submit">Create Post</button>
  </div>
</form>

<h2>All Posts</h2>
<?php if (empty($posts)): ?>
<p>No posts found.</p>
<?php else: ?>
<?php foreach ($posts as $post): ?>
<article style="margin-bottom:20px;">
  <h3><?php echo htmlspecialchars($post['title']); ?></h3>
  <div><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
  <p><small>Created: <?php echo htmlspecialchars($post['created_at']); ?> | Updated: <?php echo htmlspecialchars($post['updated_at']); ?></small></p>

  <form method="POST" action="blog_post_handler.php" style="margin-bottom:10px;">
    <input type="hidden" name="action" value="update" />
    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>" />
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>" />
    <div>
      <label>Title</label>
      <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required />
    </div>
    <div>
      <label>Content</label>
      <textarea name="content" rows="4" required><?php echo htmlspecialchars($post['content']); ?></textarea>
    </div>
    <div>
      <button type="submit">Update</button>
    </div>
  </form>

  <form method="POST" action="blog_post_handler.php" onsubmit="return confirm('Delete this post?');">
    <input type="hidden" name="action" value="delete" />
    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>" />
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>" />
    <button type="submit">Delete</button>
  </form>
</article>
<hr/>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
?>