<?php
declare(strict_types=1);

class Database {
    private ?\PDO $pdo = null;

    public function getConnection(): \PDO {
        if ($this->pdo === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'db_users';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";

            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->pdo = new \PDO($dsn, $user, $pass, $options);
        }
        return $this->pdo;
    }
}

class BlogPost {
    private \PDO $db;

    public function __construct(\PDO $db) {
        $this->db = $db;
    }

    public function create(string $title, string $content): int {
        $stmt = $this->db->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
        $stmt->execute([':title' => $title, ':content' => $content]);
        return (int)$this->db->lastInsertId();
    }

    public function readAll(): array {
        $stmt = $this->db->query("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function readById(int $id) {
        $stmt = $this->db->prepare("SELECT id, title, content, created_at FROM blog_posts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function update(int $id, string $title, string $content): int {
        $stmt = $this->db->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
        $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);
        return $stmt->rowCount();
    }

    public function delete(int $id): int {
        $stmt = $this->db->prepare("DELETE FROM blog_posts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount();
    }
}

function respondJson(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
    try {
        $db = (new Database())->getConnection();
        $bp = new BlogPost($db);

        $action = strtolower($_POST['action'] ?? 'create');

        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($title === '' || $content === '') {
                respondJson(['success' => false, 'message' => 'Title and content are required.']);
            }
            $id = $bp->create($title, $content);
            $newPost = $bp->readById($id);
            respondJson(['success' => true, 'message' => 'Post created successfully', 'data' => $newPost]);
        } elseif ($action === 'read') {
            $posts = $bp->readAll();
            respondJson(['success' => true, 'message' => 'Posts retrieved', 'data' => $posts]);
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            if ($id <= 0 || $title === '' || $content === '') {
                respondJson(['success' => false, 'message' => 'Invalid input for update.']);
            }
            $affected = $bp->update($id, $title, $content);
            $updated = $bp->readById($id);
            respondJson(['success' => true, 'message' => 'Post updated', 'data' => $updated]);
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                respondJson(['success' => false, 'message' => 'Invalid id.']);
            }
            $bp->delete($id);
            respondJson(['success' => true, 'message' => 'Post deleted', 'data' => ['id' => $id]]);
        } else {
            respondJson(['success' => false, 'message' => 'Unknown action.']);
        }
    } catch (\PDOException $e) {
        respondJson(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    } catch (\Exception $e) {
        respondJson(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    // GET: Render HTML form and list of posts
    try {
        $db = (new Database())->getConnection();
        $bp = new BlogPost($db);
        $posts = $bp->readAll();
    } catch (\Exception $e) {
        $posts = [];
    }
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8" />
        <title>Blog Post CRUD</title>
    </head>
    <body>
        <h1>Create Blog Post</h1>
        <form method="POST" action="">
            <input type="hidden" name="action" value="create" />
            <div>
                <label for="title">Title:</label><br/>
                <input type="text" id="title" name="title" required />
            </div>
            <div>
                <label for="content">Content:</label><br/>
                <textarea id="content" name="content" rows="6" cols="60" required></textarea>
            </div>
            <div>
                <button type="submit">Create Post</button>
            </div>
        </form>

        <h2>Existing Posts</h2>
        <?php if (!empty($posts)): ?>
            <ul>
                <?php foreach ($posts as $p): ?>
                    <li>
                        <strong><?= htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8') ?></strong>
                        <br/>
                        <span><?= nl2br(htmlspecialchars($p['content'], ENT_QUOTES, 'UTF-8')) ?></span>
                        <br/>
                        <em>Created at: <?= htmlspecialchars($p['created_at'], ENT_QUOTES, 'UTF-8') ?></em>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No posts yet.</p>
        <?php endif; ?>
    </body>
    </html>
    <?php
}
?>