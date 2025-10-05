<?php
namespace Classes;
class DB {
    private static $pdo;
    public static function getConnection() {
        if (self::$pdo instanceof \PDO) {
            return self::$pdo;
        }
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: '';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        self::$pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ]);
        return self::$pdo;
    }
}
namespace Classes;
class BlogPost {
    private $db;
    public function __construct($pdo = null) {
        $this->db = $pdo ?: \Classes\DB::getConnection();
    }
    public function create($title, $content) {
        $stmt = $this->db->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (:title, :content, NOW())");
        $stmt->execute([':title' => $title, ':content' => $content]);
        return $this->db->lastInsertId();
    }
    public function fetchAll() {
        $stmt = $this->db->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    public function update($id, $title, $content) {
        $stmt = $this->db->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
        $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);
        return $stmt->rowCount() > 0;
    }
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM blog_posts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
namespace Handlers;
class BlogHandler {
    private $postModel;
    public function __construct($pdo = null) {
        $this->postModel = new \Classes\BlogPost($pdo);
    }
    public function create($title, $content) {
        return $this->postModel->create($title, $content);
    }
    public function getAll() {
        return $this->postModel->fetchAll();
    }
    public function update($id, $title, $content) {
        return $this->postModel->update($id, $title, $content);
    }
    public function delete($id) {
        return $this->postModel->delete($id);
    }
}
namespace {
    function respond($success, $message, $data = null) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
        exit;
    }

    $hostPath = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        try {
            $pdo = \Classes\DB::getConnection();
            $handler = new \Handlers\BlogHandler($pdo);
            if ($action === 'create') {
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $content = isset($_POST['content']) ? trim($_POST['content']) : '';
                if ($title === '' || $content === '') {
                    respond(false, 'Title and content are required');
                }
                $title = strip_tags($title);
                $id = $handler->create($title, $content);
                respond(true, 'Post created', ['id' => $id]);
            } elseif ($action === 'update') {
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $content = isset($_POST['content']) ? trim($_POST['content']) : '';
                if ($id <= 0 || $title === '' || $content === '') {
                    respond(false, 'Invalid input');
                }
                $title = strip_tags($title);
                $ok = $handler->update($id, $title, $content);
                if ($ok) {
                    respond(true, 'Post updated');
                } else {
                    respond(false, 'Post not found or no changes');
                }
            } elseif ($action === 'delete') {
                $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                if ($id <= 0) {
                    respond(false, 'Invalid id');
                }
                $ok = $handler->delete($id);
                if ($ok) {
                    respond(true, 'Post deleted');
                } else {
                    respond(false, 'Post not found');
                }
            } else {
                respond(false, 'Invalid action');
            }
        } catch (\PDOException $e) {
            respond(false, 'Database error: ' . $e->getMessage());
        } catch (\Exception $e) {
            respond(false, 'Error: ' . $e->getMessage());
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_posts') {
        try {
            $pdo = \Classes\DB::getConnection();
            $handler = new \Handlers\BlogHandler($pdo);
            $posts = $handler->getAll();
            respond(true, 'Posts retrieved', $posts);
        } catch (\PDOException $e) {
            respond(false, 'Database error: ' . $e->getMessage());
        } catch (\Exception $e) {
            respond(false, 'Error: ' . $e->getMessage());
        }
    } else {
        echo <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Blog Admin</title>
</head>
<body>
<h1>Create Blog Post</h1>
<form method="POST" action="$hostPath">
<input type="hidden" name="action" value="create">
<label>Title:<input type="text" name="title" required></label><br>
<label>Content:<textarea name="content" rows="6" cols="60" required></textarea></label><br>
<button type="submit">Publish</button>
</form>
<h2>Blog Posts</h2>
<div id="posts">Loading posts...</div>
<script>
async function loadPosts(){
  try{
    const res = await fetch("$hostPath?action=get_posts");
    const data = await res.json();
    const container = document.getElementById('posts');
    container.innerHTML = '';
    if(!data.success){
      container.innerHTML = '<p>' + data.message + '</p>';
      return;
    }
    const posts = data.data || [];
    if (posts.length === 0){
      container.innerHTML = '<p>No posts yet.</p>';
      return;
    }
    posts.forEach(function(p){
      var div = document.createElement('div');
      div.innerHTML = '<h3>' + escapeHtml(p.title) + '</h3>' +
                       '<small>' + escapeHtml(p.created_at) + '</small>' +
                       '<p>' + nl2br(escapeHtml(p.content)) + '</p><hr>';
      container.appendChild(div);
    });
  } catch (e){
    document.getElementById('posts').innerHTML = '<p>Error loading posts.</p>';
  }
}
function escapeHtml(str){
  return String(str).replace(/[&<>"']/g, function(m){
    switch(m){
      case '&': return '&amp;';
      case '<': return '&lt;';
      case '>': return '&gt;';
      case '"': return '&quot;';
      case "'": return '&#039;';
      default: return m;
    }
  });
}
function nl2br(str){
  return String(str).replace(/\\n/g, '<br>');
}
window.addEventListener('load', loadPosts);
</script>
</body>
</html>
HTML;
    }
}
?>