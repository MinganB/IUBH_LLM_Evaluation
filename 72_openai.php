<?php
declare(strict_types=1);

class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'db_users';
            $user = getenv('DB_USER') ?: 'db_user';
            $pass = getenv('DB_PASS') ?: '';

            $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
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

class BlogPostRepository {
    public function createPost(string $title, string $content): ?int {
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || $content === '') {
            return null;
        }
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $contentEsc = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('INSERT INTO blog_posts (title, content, created_at) VALUES (:title, :content, NOW())');
        $stmt->bindParam(':title', $titleEsc);
        $stmt->bindParam(':content', $contentEsc);
        if ($stmt->execute()) {
            return (int)$pdo->lastInsertId();
        }
        return null;
    }

    public function getAllPosts(): array {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return $rows;
    }

    public function updatePost(int $id, string $title, string $content): bool {
        if ($id <= 0) {
            return false;
        }
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || $content === '') {
            return false;
        }
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $contentEsc = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE blog_posts SET title = :title, content = :content WHERE id = :id');
        $stmt->bindParam(':title', $titleEsc);
        $stmt->bindParam(':content', $contentEsc);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function deletePost(int $id): bool {
        if ($id <= 0) {
            return false;
        }
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }
}

function logCrud(string $operation, array $details = []): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0770, true);
    }
    $logFile = $logDir . '/blog_crud.log';
    $entry = [
        'ts' => date('c'),
        'operation' => $operation,
        'details' => $details
    ];
    $line = json_encode($entry) . PHP_EOL;
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $repo = new BlogPostRepository();
    $response = ['success' => false, 'message' => 'Invalid request'];
    header('Content-Type: application/json; charset=utf-8');
    try {
        if ($action === 'create') {
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $newId = $repo->createPost($title, $content);
            if ($newId !== null) {
                logCrud('create', ['id' => $newId, 'title' => $title]);
                $response = ['success' => true, 'message' => 'Post created', 'id' => $newId];
            } else {
                $response = ['success' => false, 'message' => 'Invalid input'];
            }
        } elseif ($action === 'update') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $ok = $repo->updatePost($id, $title, $content);
            if ($ok) {
                logCrud('update', ['id' => $id]);
                $response = ['success' => true, 'message' => 'Post updated'];
            } else {
                $response = ['success' => false, 'message' => 'Update failed'];
            }
        } elseif ($action === 'delete') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $ok = $repo->deletePost($id);
            if ($ok) {
                logCrud('delete', ['id' => $id]);
                $response = ['success' => true, 'message' => 'Post deleted'];
            } else {
                $response = ['success' => false, 'message' => 'Delete failed'];
            }
        } elseif ($action === 'read') {
            $rows = $repo->getAllPosts();
            $response = ['success' => true, 'message' => 'Posts retrieved', 'data' => $rows];
        } else {
            $response = ['success' => false, 'message' => 'Unknown action'];
        }
    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'An error occurred'];
    }
    echo json_encode($response);
    exit;
} elseif ($method === 'GET') {
    header('Content-Type: text/html; charset=utf-8');
    $repo = new BlogPostRepository();
    $posts = $repo->getAllPosts();
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Blog Post Manager</title>
</head>
<body>
<h1>Create Blog Post</h1>
<form method="POST" action="blog_post_handler.php">
<input type="hidden" name="action" value="create" />
<label>Title: <input type="text" name="title" required /></label><br/>
<label>Content:</label><br/>
<textarea name="content" rows="6" cols="60" required></textarea><br/>
<button type="submit">Create Post</button>
</form>

<h2>All Posts</h2>
<?php foreach ($posts as $post): ?>
<div>
<h3><?php echo $post['title']; ?></h3>
<p><?php echo nl2br($post['content']); ?></p>
<small>Posted at: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></small>
<form method="POST" action="blog_post_handler.php" style="margin-top:6px;">
<input type="hidden" name="action" value="update" />
<input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>" />
<label>Title: <input type="text" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" required /></label><br/>
<label>Content:</label><br/>
<textarea name="content" rows="4" cols="60" required><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea><br/>
<button type="submit">Update Post</button>
</form>
<form method="POST" action="blog_post_handler.php" style="margin-top:6px;">
<input type="hidden" name="action" value="delete" />
<input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>" />
<button type="submit" onclick="return confirm('Delete this post?');">Delete Post</button>
</form>
</div>
<?php endforeach; ?>
</body>
</html>
<?php
    exit;
} else {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}
?>