<?php
class Database {
    private static $instance = null;

    public static function getConnection() {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'db_users';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database connection failed']);
                exit;
            }
        }
        return self::$instance;
    }
}

class BlogPost {
    public static function createPost($title, $content) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$title, $content]);
        return $pdo->lastInsertId();
    }

    public static function getPosts($limit = 100, $offset = 0) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function updatePost($id, $title, $content) {
        $pdo = Database::getConnection();
        $fields = [];
        $params = [];
        if ($title !== null) {
            $fields[] = 'title = ?';
            $params[] = $title;
        }
        if ($content !== null) {
            $fields[] = 'content = ?';
            $params[] = $content;
        }
        if (empty($fields)) {
            return false;
        }
        $params[] = $id;
        $sql = 'UPDATE blog_posts SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public static function deletePost($id) {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}

$action = $_REQUEST['action'] ?? '';
if ($action !== '') {
    header('Content-Type: application/json');
    switch ($action) {
        case 'create':
            $title = isset($_POST['title']) ? trim($_POST['title']) : '';
            $content = isset($_POST['content']) ? $_POST['content'] : '';
            if ($title === '' || $content === '') {
                echo json_encode(['success' => false, 'message' => 'Title and content are required']);
                exit;
            }
            try {
                BlogPost::createPost($title, $content);
                echo json_encode(['success' => true, 'message' => 'Post created successfully']);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'read':
            $posts = BlogPost::getPosts();
            echo json_encode(['success' => true, 'message' => json_encode($posts)]);
            break;

        case 'update':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = isset($_POST['title']) ? $_POST['title'] : null;
            $content = isset($_POST['content']) ? $_POST['content'] : null;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid id']);
                break;
            }
            if ($title === '') $title = null;
            if ($content === '') $content = null;
            if ($title === null && $content === null) {
                echo json_encode(['success' => false, 'message' => 'Nothing to update']);
                break;
            }
            try {
                $updated = BlogPost::updatePost($id, $title, $content);
                if ($updated) {
                    echo json_encode(['success' => true, 'message' => 'Post updated']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Post not found or no changes']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        case 'delete':
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid id']);
                break;
            }
            try {
                $deleted = BlogPost::deletePost($id);
                if ($deleted) {
                    echo json_encode(['success' => true, 'message' => 'Post deleted']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Post not found']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Blog Posts</title>
</head>
<body>
<h1>Blog Posts</h1>
<form id="newPostForm">
  <input type="text" name="title" id="title" placeholder="Post title" required /><br/>
  <textarea name="content" id="content" placeholder="Post content" rows="4" cols="50" required></textarea><br/>
  <button type="submit">Create Post</button>
</form>
<div id="response" style="color:red;"></div>
<div id="posts"></div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('newPostForm');
  const postsDiv = document.getElementById('posts');
  const respDiv = document.getElementById('response');

  function renderPosts(posts){
    postsDiv.innerHTML = '';
    if (!Array.isArray(posts) || posts.length === 0){
      postsDiv.innerHTML = '<p>No posts yet.</p>';
      return;
    }
    posts.forEach(function(p){
      const el = document.createElement('div');
      const title = document.createElement('h3');
      title.textContent = p.title;
      const meta = document.createElement('p');
      meta.style.color = '#666';
      meta.textContent = 'Posted on ' + p.created_at;
      const content = document.createElement('p');
      content.textContent = p.content;
      const updateBtn = document.createElement('button');
      updateBtn.textContent = 'Update';
      updateBtn.onclick = function(){
        const newTitle = prompt('New title', p.title);
        if (newTitle === null) return;
        const newContent = prompt('New content', p.content);
        if (newContent === null) return;
        const fd = new FormData();
        fd.append('action','update');
        fd.append('id', p.id);
        fd.append('title', newTitle);
        fd.append('content', newContent);
        fetch(window.location.pathname + '?action=update', {method:'POST', body: fd})
          .then(r => r.json())
          .then(data => {
            if (data.success) loadPosts();
            else respDiv.textContent = data.message;
          });
      };
      const deleteBtn = document.createElement('button');
      deleteBtn.textContent = 'Delete';
      deleteBtn.onclick = function(){
        if (!confirm('Delete this post?')) return;
        const fd = new FormData();
        fd.append('action','delete');
        fd.append('id', p.id);
        fetch(window.location.pathname + '?action=delete', {method:'POST', body: fd})
          .then(r => r.json())
          .then(data => {
            if (data.success) loadPosts();
            else respDiv.textContent = data.message;
          });
      };
      el.appendChild(title);
      el.appendChild(meta);
      el.appendChild(content);
      el.appendChild(updateBtn);
      el.appendChild(deleteBtn);
      postsDiv.appendChild(el);
    });
  }

  function loadPosts(){
    fetch(window.location.pathname + '?action=read')
      .then(r => r.json())
      .then(data => {
        if (data.success){
          const posts = JSON.parse(data.message);
          renderPosts(posts);
        } else {
          respDiv.textContent = data.message;
        }
      });
  }

  form.addEventListener('submit', function(e){
    e.preventDefault();
    const fd = new FormData(form);
    fd.append('action','create');
    fetch(window.location.pathname + '?action=create', {method:'POST', body: fd})
      .then(r => r.json())
      .then(data => {
        if (data.success){
          form.reset();
          respDiv.textContent = '';
          loadPosts();
        } else {
          respDiv.textContent = data.message;
        }
      });
  });

  loadPosts();
});
</script>
</body>
</html>
?>