<?php
class Database {
    private static $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $dbname = $_ENV['DB_NAME'] ?? 'db_users';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            $charset = 'utf8mb4';
            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        }
        return self::$pdo;
    }
}
?><?php
class BlogPost {
    private static function getPDO(): PDO {
        return Database::getConnection();
    }

    public static function getAll(): array {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return $rows ?: [];
    }

    public static function create(string $title, string $content): ?int {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (:title, :content, NOW())");
        $stmt->execute([':title' => $title, ':content' => $content]);
        $id = (int)$pdo->lastInsertId();
        return $id ?: null;
    }

    public static function update(int $id, string $title, string $content): bool {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
        return $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);
    }

    public static function delete(int $id): bool {
        $pdo = self::getPDO();
        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
?><?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';

header('Content-Type: application/json');
$action = $_POST['action'] ?? $_GET['action'] ?? 'read';
$action = strtolower($action);

try {
    if ($action === 'read') {
        $posts = BlogPost::getAll();
        echo json_encode(['success' => true, 'message' => 'Posts retrieved', 'data' => $posts]);
        exit;
    }

    if ($action === 'create') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        if ($title === '' || $content === '') {
            echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
            exit;
        }
        $id = BlogPost::create($title, $content);
        if ($id !== null) {
            echo json_encode(['success' => true, 'message' => 'Post created', 'data' => ['id' => $id]]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to create post']);
        }
        exit;
    }

    if ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        if ($id <= 0 || $title === '' || $content === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid input for update']);
            exit;
        }
        $ok = BlogPost::update($id, $title, $content);
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Post updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update post']);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid post id']);
            exit;
        }
        $ok = BlogPost::delete($id);
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Post deleted']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete post']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?><?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';
$posts = BlogPost::getAll();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blog Admin</title>
</head>
<body>
    <h1>Create Blog Post</h1>
    <form id="createForm" method="POST" action="/handlers/blog_post_handler.php">
        <input type="hidden" name="action" value="create" />
        <div>
            <label>Title</label>
            <input type="text" name="title" required />
        </div>
        <div>
            <label>Content</label>
            <textarea name="content" rows="6" cols="60" required></textarea>
        </div>
        <div>
            <button type="submit">Create Post</button>
        </div>
    </form>

    <h2>Existing Posts</h2>
    <div id="postsContainer">
    <?php if (empty($posts)): ?>
        <p>No posts yet.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <article>
                <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <div><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></div>
                <small>Created at: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
                <form class="action-form update-form" data-action="update" method="POST" style="margin-top:10px;">
                    <input type="hidden" name="action" value="update" />
                    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>" />
                    <div>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" required />
                    </div>
                    <div>
                        <textarea name="content" rows="6" cols="60" required><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                    <div>
                        <button type="submit">Update Post</button>
                    </div>
                </form>
                <form class="action-form delete-form" data-action="delete" method="POST" style="margin-top:6px;">
                    <input type="hidden" name="action" value="delete" />
                    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>" />
                    <button type="submit" onclick="return confirm('Delete this post?');">Delete Post</button>
                </form>
            </article>
            <hr/>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function renderPosts(posts) {
            const container = document.getElementById('postsContainer');
            if (!posts || posts.length === 0) {
                container.innerHTML = '<p>No posts yet.</p>';
                return;
            }
            let html = '';
            posts.forEach(post => {
                html += '<article>';
                html += '<h3>' + escapeHtml(post.title) + '</h3>';
                html += '<div>' + escapeHtml(post.content).replace(/\\n/g, '<br>') + '</div>';
                html += '<small>Created at: ' + escapeHtml(post.created_at) + '</small>';
                html += '<form class="action-form update-form" data-action="update" method="POST" style="margin-top:10px;">';
                html += '<input type="hidden" name="action" value="update">';
                html += '<input type="hidden" name="id" value="' + post.id + '">';
                html += '<div><input type="text" name="title" value="' + escapeHtml(post.title) + '" required></div>';
                html += '<div><textarea name="content" rows="6" cols="60" required>' + escapeHtml(post.content) + '</textarea></div>';
                html += '<div><button type="submit">Update Post</button></div>';
                html += '</form>';
                html += '<form class="action-form delete-form" data-action="delete" method="POST" style="margin-top:6px;">';
                html += '<input type="hidden" name="action" value="delete">';
                html += '<input type="hidden" name="id" value="' + post.id + '">';
                html += '<button type="submit" onclick="return confirm(\'Delete this post?\');">Delete Post</button>';
                html += '</form>';
                html += '</article><hr/>';
            });
            container.innerHTML = html;
        }

        function loadPosts() {
            fetch('/handlers/blog_post_handler.php?action=read')
                .then(response => response.json())
                .then(obj => {
                    if (obj && obj.success) {
                        renderPosts(obj.data);
                    } else {
                        document.getElementById('postsContainer').innerHTML = '<p>Error loading posts</p>';
                    }
                })
                .catch(() => {
                    document.getElementById('postsContainer').innerHTML = '<p>Error loading posts</p>';
                });
        }

        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form.classList.contains('action-form')) {
                e.preventDefault();
                const fd = new FormData(form);
                fetch('/handlers/blog_post_handler.php', { method: 'POST', body: fd })
                    .then(res => res.json())
                    .then(data => {
                        alert(data.message || 'Operation completed');
                        if (data.success) {
                            loadPosts();
                        }
                    })
                    .catch(() => alert('Request failed'));
            }
        });

        document.getElementById('createForm').addEventListener('submit', function(e) {
            // handled by delegated submit above for consistency
        });

        loadPosts();
    });
    </script>
</body>
</html>
?>