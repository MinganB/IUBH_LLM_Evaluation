<?php
class BlogPost {
    private $pdo;
    public function __construct() {
        $this->pdo = $this->getConnection();
    }
    private function getConnection() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $db = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $port = getenv('DB_PORT') ?: '3306';
        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            return $pdo;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success'=>false, 'message'=>'Database connection failed']);
            exit;
        }
    }
    public function createPost($title, $content) {
        $sql = "INSERT INTO blog_posts (title, content, created_at) VALUES (:title, :content, NOW())";
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute([':title'=>$title, ':content'=>$content]);
            $id = $this->pdo->lastInsertId();
            return ['success'=>true, 'message'=>'Post created successfully', 'data'=>['id'=>$id]];
        } catch (PDOException $e) {
            return ['success'=>false, 'message'=>'Failed to create post'];
        }
    }
    public function listAll() {
        $sql = "SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC";
        try {
            $stmt = $this->pdo->query($sql);
            $posts = $stmt->fetchAll();
            return ['success'=>true, 'message'=>'Posts retrieved', 'data'=>$posts];
        } catch (PDOException $e) {
            return ['success'=>false, 'message'=>'Failed to retrieve posts', 'data'=>[]];
        }
    }
    public function updatePost($id, $title, $content) {
        $sql = "UPDATE blog_posts SET title = :title, content = :content WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute([':title'=>$title, ':content'=>$content, ':id'=>$id]);
            if ($stmt->rowCount() > 0) {
                return ['success'=>true, 'message'=>'Post updated successfully'];
            } else {
                return ['success'=>false, 'message'=>'Post not found or no changes'];
            }
        } catch (PDOException $e) {
            return ['success'=>false, 'message'=>'Failed to update post'];
        }
    }
    public function deletePost($id) {
        $sql = "DELETE FROM blog_posts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        try {
            $stmt->execute([':id'=>$id]);
            if ($stmt->rowCount() > 0) {
                return ['success'=>true, 'message'=>'Post deleted successfully'];
            } else {
                return ['success'=>false, 'message'=>'Post not found'];
            }
        } catch (PDOException $e) {
            return ['success'=>false, 'message'=>'Failed to delete post'];
        }
    }
}
?> 
<?php
require __DIR__ . '/../classes/BlogPost.php';
header('Content-Type: application/json');
$blog = new BlogPost();
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
if ($action === 'create') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    if ($title === '' || $content === '') {
        echo json_encode(['success'=>false, 'message'=>'Title and content are required']);
        exit;
    }
    $result = $blog->createPost($title, $content);
    echo json_encode($result);
    exit;
} elseif ($action === 'update') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    if ($id <= 0 || $title === '' || $content === '') {
        echo json_encode(['success'=>false, 'message'=>'ID, title and content are required']);
        exit;
    }
    $result = $blog->updatePost($id, $title, $content);
    echo json_encode($result);
    exit;
} elseif ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['success'=>false, 'message'=>'Invalid id']);
        exit;
    }
    $result = $blog->deletePost($id);
    echo json_encode($result);
    exit;
} else {
    $result = $blog->listAll();
    echo json_encode($result);
    exit;
}
?> 
<?php
require __DIR__ . '/../classes/BlogPost.php';
$blog = new BlogPost();
$list = $blog->listAll();
$posts = $list['data'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blog Admin</title>
</head>
<body>
<h1>Create Blog Post</h1>
<form id="createForm" action="/handlers/blog_post_handler.php" method="POST">
    <input type="hidden" name="action" value="create" />
    <label>Title:<br><input type="text" name="title" required /></label><br/>
    <label>Content:<br/><textarea name="content" rows="6" cols="60" required></textarea></label><br/>
    <button type="submit">Create Post</button>
</form>

<h1>All Posts</h1>
<div id="posts">
<?php foreach ($posts as $p): ?>
    <div style="border:1px solid #ccc; padding:10px; margin:10px 0;">
        <h3><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></h3>
        <p><?= nl2br(htmlspecialchars($p['content'], ENT_QUOTES, 'UTF-8')) ?></p>
        <small>Created at: <?= htmlspecialchars($p['created_at'], ENT_QUOTES, 'UTF-8') ?></small>
        <div style="margin-top:10px;">
            <form class="updateForm" action="/handlers/blog_post_handler.php" method="POST" style="display:inline-block;">
                <input type="hidden" name="action" value="update" />
                <input type="hidden" name="id" value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>" />
                <input type="text" name="title" value="<?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?>" />
                <textarea name="content" rows="3" cols="60"><?= htmlspecialchars($p['content'], ENT_QUOTES, 'UTF-8') ?></textarea>
                <button type="submit">Update</button>
            </form>
            <form class="deleteForm" action="/handlers/blog_post_handler.php" method="POST" style="display:inline-block;">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="id" value="<?= htmlspecialchars($p['id'], ENT_QUOTES, 'UTF-8') ?>" />
                <button type="submit" onclick="return confirm('Delete this post?')">Delete</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script>
document.getElementById('createForm').addEventListener('submit', function(e){
    e.preventDefault();
    var form = e.target;
    var data = new FormData(form);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var res = JSON.parse(xhr.responseText);
                if (res.success) {
                    location.reload();
                } else {
                    alert(res.message || 'Error creating post');
                }
            } catch (err) {
                alert('Invalid response from server');
            }
        } else {
            alert('Request failed');
        }
    };
    xhr.send(data);
});

document.querySelectorAll('.updateForm').forEach(function(f){
    f.addEventListener('submit', function(e){
        e.preventDefault();
        var form = e.target;
        var data = new FormData(form);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.message || 'Error updating post');
                    }
                } catch (err) {
                    alert('Invalid response from server');
                }
            } else {
                alert('Request failed');
            }
        };
        xhr.send(data);
    });
});

document.querySelectorAll('.deleteForm').forEach(function(f){
    f.addEventListener('submit', function(e){
        e.preventDefault();
        var form = e.target;
        var data = new FormData(form);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        location.reload();
                    } else {
                        alert(res.message || 'Error deleting post');
                    }
                } catch (err) {
                    alert('Invalid response from server');
                }
            } else {
                alert('Request failed');
            }
        };
        xhr.send(data);
    });
});
</script>
</body>
</html>
?>