<?php
class Database {
    private $pdo;

    public function __construct() {
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbName = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            exit;
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}

class Logger {
    private static function getLogFile() {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        return $logDir . '/blog_crud.log';
    }

    public static function log($message) {
        $logFile = self::getLogFile();
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($logFile, $entry, FILE_APPEND);
    }
}

class BlogPost {
    private $pdo;

    public function __construct() {
        $db = new Database();
        $this->pdo = $db->getConnection();
    }

    private function sanitize($value) {
        if (!is_string($value)) {
            return $value;
        }
        $value = trim($value);
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public function createPost($title, $content) {
        $t = $this->sanitize($title);
        $c = $this->sanitize($content);
        $stmt = $this->pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (?, ?)");
        $stmt->execute([$t, $c]);
        $id = $this->pdo->lastInsertId();
        Logger::log("CREATE post id={$id} title='{$t}'");
        return $id;
    }

    public function readPosts() {
        $stmt = $this->pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function updatePost($id, $title, $content) {
        $id = (int)$id;
        $t = $this->sanitize($title);
        $c = $this->sanitize($content);
        $stmt = $this->pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
        $stmt->execute([$t, $c, $id]);
        if ($stmt->rowCount() > 0) {
            Logger::log("UPDATE post id={$id} title='{$t}'");
            return true;
        }
        return false;
    }

    public function deletePost($id) {
        $id = (int)$id;
        $stmt = $this->pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            Logger::log("DELETE post id={$id}");
            return true;
        }
        return false;
    }

    public function getPostById($id) {
        $stmt = $this->pdo->prepare("SELECT id, title, content, created_at FROM blog_posts WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch();
    }
}

function respond($success, $message, $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => (bool)$success, 'message' => $message, 'data' => $data]);
    exit;
}

function renderPage() {
    header('Content-Type: text/html; charset=utf-8');
    $bp = new BlogPost();
    $posts = [];
    try {
        $posts = $bp->readPosts();
    } catch (Exception $e) {
        $posts = [];
    }

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Blog Admin</title></head><body>';
    echo '<h1>Blog Posts</h1>';
    echo '<h2>Create Post</h2>';
    echo '<form method="post" action="blog_post_handler.php">';
    echo '<input type="hidden" name="action" value="create">';
    echo '<input type="text" name="title" placeholder="Title" required>';
    echo '<br>';
    echo '<textarea name="content" placeholder="Content" required></textarea>';
    echo '<br>';
    echo '<button type="submit">Create Post</button>';
    echo '</form>';

    foreach ($posts as $p) {
        $id = htmlspecialchars((string)$p['id'], ENT_QUOTES, 'UTF-8');
        $title = $p['title'];
        $content = $p['content'];
        $created = $p['created_at'];

        echo '<hr>';
        echo '<article>';
        echo '<h3>' . $title . '</h3>';
        echo '<div>' . $content . '</div>';
        echo '<small>Created at ' . $created . '</small>';

        echo '<h4>Update</h4>';
        echo '<form method="post" action="blog_post_handler.php">';
        echo '<input type="hidden" name="action" value="update">';
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<input type="text" name="title" value="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
        echo '<br>';
        echo '<textarea name="content">' . $content . '</textarea>';
        echo '<br>';
        echo '<button type="submit">Update Post</button>';
        echo '</form>';

        echo '<form method="post" action="blog_post_handler.php" onsubmit="return confirm(\'Delete this post?\');">';
        echo '<input type="hidden" name="action" value="delete">';
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<button type="submit">Delete Post</button>';
        echo '</form>';

        echo '</article>';
    }

    echo '</body></html>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $bp = new BlogPost();

    if ($action === 'create') {
        if (isset($_POST['title'], $_POST['content'])) {
            $title = $_POST['title'];
            $content = $_POST['content'];
            if (trim($title) === '' || trim($content) === '') {
                respond(false, 'Title and content are required.');
            } else {
                $bp->createPost($title, $content);
                respond(true, 'Post created successfully.');
            }
        } else {
            respond(false, 'Invalid input.');
        }
    } elseif ($action === 'update') {
        if (isset($_POST['id'], $_POST['title'], $_POST['content'])) {
            $id = $_POST['id'];
            $title = $_POST['title'];
            $content = $_POST['content'];
            $success = $bp->updatePost($id, $title, $content);
            respond($success, $success ? 'Post updated successfully.' : 'Failed to update post.');
        } else {
            respond(false, 'Invalid input.');
        }
    } elseif ($action === 'delete') {
        if (isset($_POST['id'])) {
            $id = $_POST['id'];
            $success = $bp->deletePost($id);
            respond($success, $success ? 'Post deleted successfully.' : 'Failed to delete post.');
        } else {
            respond(false, 'Invalid input.');
        }
    } elseif ($action === 'read') {
        $posts = $bp->readPosts();
        respond(true, 'Posts retrieved successfully.', $posts);
    } else {
        respond(false, 'Invalid action.');
    }
} else {
    renderPage();
}
?>