<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = 'db_users';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false
];

try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  http_response_code(500);
  echo "Database connection failed";
  exit;
}

$pdo->exec("
  CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  )
");

$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if ($title === '' || $content === '') {
      header('Location: blog_post_handler.php');
      exit;
    }
    $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
    $stmt->execute([':title' => $title, ':content' => $content]);
    header('Location: blog_post_handler.php');
    exit;
  } elseif ($action === 'update') {
    $id = $_POST['id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if (!ctype_digit($id) || $title === '' || $content === '') {
      header('Location: blog_post_handler.php');
      exit;
    }
    $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
    $stmt->execute([':title' => $title, ':content' => $content, ':id' => (int)$id]);
    header('Location: blog_post_handler.php');
    exit;
  } elseif ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    if (!ctype_digit($id)) {
      header('Location: blog_post_handler.php');
      exit;
    }
    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
    $stmt->execute([':id' => (int)$id]);
    header('Location: blog_post_handler.php');
    exit;
  } else {
    header('Location: blog_post_handler.php');
    exit;
  }
}

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Blog Post Manager</title></head><body>';
echo '<h1>Create a new blog post</h1>';
echo '<form method="POST" action="blog_post_handler.php">';
echo '<input type="hidden" name="action" value="create">';
echo '<label>Title: <input type="text" name="title" required></label><br>';
echo '<label>Content: <textarea name="content" required></textarea></label><br>';
echo '<button type="submit">Create Post</button>';
echo '</form>';

$stmt = $pdo->prepare("SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC");
$stmt->execute();
$posts = $stmt->fetchAll();

foreach ($posts as $post) {
  echo '<hr>';
  echo '<h2>' . htmlspecialchars($post['title']) . '</h2>';
  echo '<div>' . nl2br(htmlspecialchars($post['content'])) . '</div>';
  echo '<p><em>Created: ' . htmlspecialchars($post['created_at']) . ' | Updated: ' . htmlspecialchars($post['updated_at']) . '</em></p>';

  echo '<form method="POST" action="blog_post_handler.php">';
  echo '<input type="hidden" name="action" value="update">';
  echo '<input type="hidden" name="id" value="' . htmlspecialchars($post['id']) . '">';
  echo '<label>Title: <input type="text" name="title" value="' . htmlspecialchars($post['title']) . '"></label><br>';
  echo '<label>Content: <textarea name="content">' . htmlspecialchars($post['content']) . '</textarea></label><br>';
  echo '<button type="submit">Update</button>';
  echo '</form>';

  echo '<form method="POST" action="blog_post_handler.php" onsubmit="return confirm(\'Delete this post?\');">';
  echo '<input type="hidden" name="action" value="delete">';
  echo '<input type="hidden" name="id" value="' . htmlspecialchars($post['id']) . '">';
  echo '<button type="submit">Delete</button>';
  echo '</form>';
}
echo '</body></html>';
?>