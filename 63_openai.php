<?php
declare(strict_types=1);

function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
function getLogFilePath() {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    return $logDir . '/blog_crud.log';
}
function logOperation(string $operation, string $details) {
    $path = getLogFilePath();
    $entry = date('Y-m-d H:i:s') . " | " . $operation . " | " . $details;
    file_put_contents($path, $entry . PHP_EOL, FILE_APPEND);
}
class Database {
    private $pdo;
    public function __construct() {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $db = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'db_user';
        $pass = getenv('DB_PASS') ?: '';
        $port = getenv('DB_PORT') ?: '3306';
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }
    public function getConnection(): PDO {
        return $this->pdo;
    }
}
class BlogPostHandler {
    private $pdo;
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    public function createPost(string $title, string $content): array {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (:title, :content, NOW())");
            $stmt->execute([':title' => $title, ':content' => $content]);
            $id = (int)$this->pdo->lastInsertId();
            logOperation('CREATE', "id=$id");
            return ['success' => true, 'message' => 'Post created'];
        } catch (Exception $e) {
            logOperation('CREATE_FAIL', $e->getMessage());
            return ['success' => false, 'message' => 'Unable to create post'];
        }
    }
    public function readPosts(): array {
        try {
            $stmt = $this->pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $rows = $stmt->fetchAll();
            return ['success' => true, 'message' => 'Posts retrieved', 'data' => $rows];
        } catch (Exception $e) {
            logOperation('READ_FAIL', $e->getMessage());
            return ['success' => false, 'message' => 'Unable to retrieve posts'];
        }
    }
    public function updatePost(int $id, string $title, string $content): array {
        try {
            $stmt = $this->pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
            $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);
            if ($stmt->rowCount() > 0) {
                logOperation('UPDATE', "id=$id");
                return ['success' => true, 'message' => 'Post updated'];
            } else {
                return ['success' => false, 'message' => 'Post not found'];
            }
        } catch (Exception $e) {
            logOperation('UPDATE_FAIL', $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update post'];
        }
    }
    public function deletePost(int $id): array {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() > 0) {
                logOperation('DELETE', "id=$id");
                return ['success' => true, 'message' => 'Post deleted'];
            } else {
                return ['success' => false, 'message' => 'Post not found'];
            }
        } catch (Exception $e) {
            logOperation('DELETE_FAIL', $e->getMessage());
            return ['success' => false, 'message' => 'Unable to delete post'];
        }
    }
}
try {
    $db = new Database();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection error']);
    exit;
}
$handler = new BlogPostHandler($pdo);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($method === 'POST' && in_array($action, ['create','update','delete'])) {
    if ($action === 'create') {
        $title = isset($_POST['title']) ? sanitize_input($_POST['title']) : '';
        $content = isset($_POST['content']) ? sanitize_input($_POST['content']) : '';
        if (empty($title) || empty($content)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }
        $result = $handler->createPost($title, $content);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = isset($_POST['title']) ? sanitize_input($_POST['title']) : '';
        $content = isset($_POST['content']) ? sanitize_input($_POST['content']) : '';
        if ($id <= 0 || empty($title) || empty($content)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }
        $result = $handler->updatePost($id, $title, $content);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid input']);
            exit;
        }
        $result = $handler->deletePost($id);
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}
if ($method === 'GET' && isset($_GET['api']) && $_GET['api'] === 'read') {
    $result = $handler->readPosts();
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Blog Module</title></head>
<body>
<h2>Create a new blog post</h2>
<form id="postForm" method="post" action="blog_module.php">
  <input type="hidden" name="action" value="create">
  <div>
    <label>Title</label><br>
    <input type="text" name="title" required>
  </div>
  <div>
    <label>Content</label><br>
    <textarea name="content" rows="6" cols="60" required></textarea>
  </div>
  <button type="submit">Create Post</button>
</form>

<hr>
<div id="posts"></div>

<script>
function loadPosts(){
  fetch('blog_module.php?api=read')
   .then(res => res.json())
   .then(data => {
     const container = document.getElementById('posts');
     container.innerHTML = '';
     if (data.success && Array.isArray(data.data)) {
       data.data.forEach(p => {
         const card = document.createElement('div');
         const title = document.createElement('h3');
         title.textContent = p.title;
         const content = document.createElement('p');
         content.textContent = p.content;
         const meta = document.createElement('small');
         meta.textContent = 'Posted on ' + p.created_at;
         card.appendChild(title);
         card.appendChild(content);
         card.appendChild(meta);
         container.appendChild(card);
       });
     } else {
       container.textContent = data.message || 'No posts found';
     }
   })
   .catch(() => {
     document.getElementById('posts').textContent = 'Error loading posts';
   });
}
document.addEventListener('DOMContentLoaded', loadPosts);
document.getElementById('postForm').addEventListener('submit', function(e){
  e.preventDefault();
  const form = e.target;
  const formData = new FormData(form);
  fetch('blog_module.php', {method: 'POST', body: formData})
   .then(r => r.json())
   .then(res => {
     alert(res.message);
     if (res.success) {
       form.reset();
       loadPosts();
     }
   });
});
</script>

</body>
</html>
?>