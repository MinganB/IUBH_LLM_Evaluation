<?php

namespace Blog\Classes;

use PDO;
use PDOException;

class Database {
    private static $connection = null;
    private static $host = 'localhost';
    private static $db_name = 'db_users';
    private static $username = 'root';
    private static $password = '';

    private function __construct() {}

    public static function getConnection() {
        if (self::$connection === null) {
            try {
                $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$db_name . ";charset=utf8mb4";
                self::$connection = new PDO($dsn, self::$username, self::$password);
                self::$connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                die(json_encode(["success" => false, "message" => "Database connection failed."]));
            }
        }
        return self::$connection;
    }
}


namespace Blog\Classes;

use PDO;
use PDOException;

class BlogPost {
    private $conn;
    private $table_name = "blog_posts";

    public function __construct() {
        $this->conn = Database::getConnection();
    }

    public function create(string $title, string $content): array {
        if (empty($title) || empty($content)) {
            return ["success" => false, "message" => "Title and content cannot be empty."];
        }

        $query = "INSERT INTO " . $this->table_name . " (title, content) VALUES (:title, :content)";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":content", $content);

        try {
            if ($stmt->execute()) {
                return ["success" => true, "message" => "Blog post created successfully."];
            }
            return ["success" => false, "message" => "Failed to create blog post."];
        } catch (PDOException $e) {
            error_log("Error creating post: " . $e->getMessage());
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }

    public function read(?int $id = null): array {
        try {
            if ($id) {
                $query = "SELECT id, title, content, created_at FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":id", $id, PDO::PARAM_INT);
                $stmt->execute();
                $post = $stmt->fetch();

                if ($post) {
                    return ["success" => true, "message" => "Blog post retrieved.", "data" => $post];
                } else {
                    return ["success" => false, "message" => "Blog post not found."];
                }
            } else {
                $query = "SELECT id, title, content, created_at FROM " . $this->table_name . " ORDER BY created_at DESC";
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                $posts = $stmt->fetchAll();

                return ["success" => true, "message" => "Blog posts retrieved.", "data" => $posts];
            }
        } catch (PDOException $e) {
            error_log("Error reading post(s): " . $e->getMessage());
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }

    public function update(int $id, string $title, string $content): array {
        if (empty($title) || empty($content)) {
            return ["success" => false, "message" => "Title and content cannot be empty."];
        }

        $query = "UPDATE " . $this->table_name . " SET title = :title, content = :content WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":title", $title);
        $stmt->bindParam(":content", $content);
        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return ["success" => true, "message" => "Blog post updated successfully."];
                }
                return ["success" => false, "message" => "Blog post not found or no changes made."];
            }
            return ["success" => false, "message" => "Failed to update blog post."];
        } catch (PDOException $e) {
            error_log("Error updating post: " . $e->getMessage());
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }

    public function delete(int $id): array {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $id, PDO::PARAM_INT);

        try {
            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    return ["success" => true, "message" => "Blog post deleted successfully."];
                }
                return ["success" => false, "message" => "Blog post not found."];
            }
            return ["success" => false, "message" => "Failed to delete blog post."];
        } catch (PDOException $e) {
            error_log("Error deleting post: " . $e->getMessage());
            return ["success" => false, "message" => "Database error: " . $e->getMessage()];
        }
    }
}


<?php

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';

use Blog\Classes\BlogPost;

$blogPost = new BlogPost();
$response = ["success" => false, "message" => "Invalid request."];

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$returnJson = isset($_GET['_return_json']) || isset($_POST['_return_json']) || $isAjax;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $response = $blogPost->create($title, $content);
            break;

        case 'update':
            $id = $_POST['id'] ?? null;
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            if ($id && is_numeric($id)) {
                $response = $blogPost->update((int)$id, $title, $content);
            } else {
                $response = ["success" => false, "message" => "Invalid ID for update."];
            }
            break;

        case 'delete':
            $id = $_POST['id'] ?? null;
            if ($id && is_numeric($id)) {
                $response = $blogPost->delete((int)$id);
            } else {
                $response = ["success" => false, "message" => "Invalid ID for delete."];
            }
            break;

        default:
            $response = ["success" => false, "message" => "Unknown POST action."];
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'read':
            $id = $_GET['id'] ?? null;
            if ($id && is_numeric($id)) {
                $response = $blogPost->read((int)$id);
            } else {
                $response = $blogPost->read();
            }
            break;

        default:
            $response = ["success" => false, "message" => "Unknown GET action."];
            break;
    }
}

if ($returnJson) {
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    $redirectUrl = '../public/blog.php?message=' . urlencode($response['message']) . '&success=' . ($response['success'] ? '1' : '0');
    header('Location: ' . $redirectUrl);
    exit();
}


<?php

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';

use Blog\Classes\BlogPost;

$blogPostManager = new BlogPost();
$postsResult = $blogPostManager->read();
$posts = $postsResult['success'] ? $postsResult['data'] : [];
$message = $_GET['message'] ?? '';
$success = isset($_GET['success']) ? (bool)$_GET['success'] : null;

$editPostData = null;
if (isset($_GET['edit_id']) && is_numeric($_GET['edit_id'])) {
    $singlePostResult = $blogPostManager->read((int)$_GET['edit_id']);
    if ($singlePostResult['success']) {
        $editPostData = $singlePostResult['data'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blog</title>
</head>
<body>
    <h1>Simple Blog Posts</h1>

    <?php if ($message): ?>
        <p style="color: <?php echo $success ? 'green' : 'red'; ?>;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <h2>Create New Blog Post</h2>
    <form action="../handlers/blog_posts_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="5" required></textarea><br><br>
        <button type="submit">Create Post</button>
    </form>

    <?php if ($editPostData): ?>
        <h2>Edit Blog Post #<?php echo htmlspecialchars($editPostData['id']); ?></h2>
        <form action="../handlers/blog_posts_handler.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPostData['id']); ?>">
            <label for="edit_title">Title:</label><br>
            <input type="text" id="edit_title" name="title" value="<?php echo htmlspecialchars($editPostData['title']); ?>" required><br><br>
            <label for="edit_content">Content:</label><br>
            <textarea id="edit_content" name="content" rows="5" required><?php echo htmlspecialchars($editPostData['content']); ?></textarea><br><br>
            <button type="submit">Update Post</button>
            <a href="blog.php">Cancel Edit</a>
        </form>
    <?php endif; ?>

    <h2>Existing Blog Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div style="border: 1px solid #ccc; padding: 10px; margin-bottom: 10px;">
                <h3><?php echo htmlspecialchars($post['title']); ?> (ID: <?php echo htmlspecialchars($post['id']); ?>)</h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Posted on: <?php echo htmlspecialchars($post['created_at']); ?></small><br>
                <a href="blog.php?edit_id=<?php echo htmlspecialchars($post['id']); ?>">Edit</a>
                <form action="../handlers/blog_posts_handler.php" method="POST" style="display:inline-block; margin-left: 10px;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                    <button type="submit">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
?>