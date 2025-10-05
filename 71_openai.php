<?php
class Database {
    private static $instance = null;
    private function __construct() {}
    public static function getConnection() {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME') ?: 'db_users';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASSWORD') ?: '';
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            self::$instance = new PDO($dsn, $user, $pass, $options);
        }
        return self::$instance;
    }
}
?><?php
require_once __DIR__ . '/Database.php';
class BlogPost {
    public static function getAll(): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return $rows;
    }

    public static function create(string $title, string $content): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
        $stmt->execute(['title' => $title, 'content' => $content]);
        $id = (int)$pdo->lastInsertId();
        return ['id' => $id];
    }

    public static function update(int $id, string $title, string $content): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
        $stmt->execute(['title' => $title, 'content' => $content, 'id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id): bool {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
?><?php
require_once __DIR__ . '/../classes/BlogPost.php';
header('Content-Type: application/json');
$inputMethod = $_SERVER['REQUEST_METHOD'] ?? 'POST';
if ($inputMethod === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'list') {
        $posts = BlogPost::getAll();
        echo json_encode(['success' => true, 'message' => 'Posts retrieved', 'data' => $posts]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Invalid GET action']);
    exit;
}
$action = isset($_POST['action']) ? strtolower(trim($_POST['action'])) : '';
if ($action === 'create') {
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    if ($title === '' || $content === '') {
        echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
        exit;
    }
    $title = substr($title, 0, 255);
    $result = BlogPost::create($title, $content);
    echo json_encode(['success' => true, 'message' => 'Post created successfully', 'data' => ['id' => $result['id']]]);
    exit;
}
if ($action === 'update') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    if ($id <= 0 || $title === '' || $content === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid id, title, or content.']);
        exit;
    }
    $updated = BlogPost::update($id, $title, $content);
    if ($updated) {
        echo json_encode(['success' => true, 'message' => 'Post updated.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Post not found or no changes made.']);
    }
    exit;
}
if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid id.']);
        exit;
    }
    $deleted = BlogPost::delete($id);
    if ($deleted) {
        echo json_encode(['success' => true, 'message' => 'Post deleted.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Post not found.']);
    }
    exit;
}
echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?><?php
require_once __DIR__ . '/../classes/BlogPost.php';
try {
    $posts = BlogPost::getAll();
} catch (Exception $e) {
    $posts = [];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Blog Admin</title>
</head>
<body>
<h1>Create a new blog post</h1>
<form action="../handlers/blog_post_handler.php" method="POST" id="createPostForm">
  <input type="hidden" name="action" value="create">
  <label>Title: <input type="text" name="title" required></label><br>
  <label>Content:<br><textarea name="content" rows="6" cols="60" required></textarea></label><br>
  <button type="submit">Create Post</button>
</form>

<h2>Existing Posts</h2>
<div id="posts">
<?php foreach ($posts as $p): ?>
  <div class="post">
    <h3><?php echo htmlspecialchars($p['title']); ?></h3>
    <p><?php echo nl2br(htmlspecialchars($p['content'])); ?></p>
    <small>Posted on <?php echo htmlspecialchars($p['created_at']); ?></small>
    <form action="../handlers/blog_post_handler.php" method="POST" style="margin-top:8px;">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
      <input type="text" name="title" value="<?php echo htmlspecialchars($p['title']); ?>" required>
      <textarea name="content" rows="4" cols="60" required><?php echo htmlspecialchars($p['content']); ?></textarea>
      <button type="submit">Update</button>
    </form>
    <form action="../handlers/blog_post_handler.php" method="POST" onsubmit="return confirm('Delete this post?');" style="margin-top:4px;">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
      <button type="submit">Delete</button>
    </form>
  </div>
  <hr>
<?php endforeach; ?>
</div>
</body>
</html>
?>