<?php

class BlogPost {
    private $pdo;
    private $table = 'blog_posts';
    private $db_name = 'db_users';

    public function __construct() {
        $host = 'localhost';
        $user = 'root';
        $password = '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$this->db_name;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $password, $options);
        } catch (\PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            header('Content-Type: application/json');
            die(json_encode(["success" => false, "message" => "Database connection error."]));
        }
    }

    public function createPost($title, $content) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO $this->table (title, content) VALUES (:title, :content)");
            $stmt->execute([':title' => $title, ':content' => $content]);
            return ["success" => true, "message" => "Blog post created successfully.", "id" => $this->pdo->lastInsertId()];
        } catch (\PDOException $e) {
            error_log("Error creating post: " . $e->getMessage());
            return ["success" => false, "message" => "Error creating blog post: " . $e->getMessage()];
        }
    }

    public function getPosts() {
        try {
            $stmt = $this->pdo->query("SELECT id, title, content, created_at FROM $this->table ORDER BY created_at DESC");
            $posts = $stmt->fetchAll();
            return ["success" => true, "data" => $posts];
        } catch (\PDOException $e) {
            error_log("Error retrieving posts: " . $e->getMessage());
            return ["success" => false, "message" => "Error retrieving blog posts: " . $e->getMessage()];
        }
    }

    public function getPostById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, title, content, created_at FROM $this->table WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $post = $stmt->fetch();
            if ($post) {
                return ["success" => true, "data" => $post];
            } else {
                return ["success" => false, "message" => "Blog post not found."];
            }
        } catch (\PDOException $e) {
            error_log("Error retrieving post by ID: " . $e->getMessage());
            return ["success" => false, "message" => "Error retrieving blog post by ID: " . $e->getMessage()];
        }
    }

    public function updatePost($id, $title, $content) {
        try {
            $stmt = $this->pdo->prepare("UPDATE $this->table SET title = :title, content = :content WHERE id = :id");
            $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);
            if ($stmt->rowCount() > 0) {
                return ["success" => true, "message" => "Blog post updated successfully."];
            } else {
                return ["success" => false, "message" => "No blog post found with the given ID, or no changes made."];
            }
        } catch (\PDOException $e) {
            error_log("Error updating post: " . $e->getMessage());
            return ["success" => false, "message" => "Error updating blog post: " . $e->getMessage()];
        }
    }

    public function deletePost($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM $this->table WHERE id = :id");
            $stmt->execute([':id' => $id]);
            if ($stmt->rowCount() > 0) {
                return ["success" => true, "message" => "Blog post deleted successfully."];
            } else {
                return ["success" => false, "message" => "No blog post found with the given ID."];
            }
        } catch (\PDOException $e) {
            error_log("Error deleting post: " . $e->getMessage());
            return ["success" => false, "message" => "Error deleting blog post: " . $e->getMessage()];
        }
    }
}
?>

<?php
require_once __DIR__ . '/../classes/BlogPost.php';

header('Content-Type: application/json');

$response = ["success" => false, "message" => "Invalid request."];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blogPost = new BlogPost();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($title) || empty($content)) {
                $response = ["success" => false, "message" => "Title and content cannot be empty."];
            } else {
                $response = $blogPost->createPost($title, $content);
            }
            break;

        case 'update':
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if ($id === false || $id <= 0) {
                $response = ["success" => false, "message" => "Invalid post ID for update."];
            } elseif (empty($title) || empty($content)) {
                $response = ["success" => false, "message" => "Title and content cannot be empty."];
            } else {
                $response = $blogPost->updatePost($id, $title, $content);
            }
            break;

        case 'delete':
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if ($id === false || $id <= 0) {
                $response = ["success" => false, "message" => "Invalid post ID for delete."];
            } else {
                $response = $blogPost->deletePost($id);
            }
            break;

        default:
            $response = ["success" => false, "message" => "Unknown action."];
            break;
    }
}

echo json_encode($response);
?>

<?php
require_once __DIR__ . '/../classes/BlogPost.php';

$blogPostManager = new BlogPost();

$posts_response = $blogPostManager->getPosts();
$posts = [];
if ($posts_response['success']) {
    $posts = $posts_response['data'];
} else {
    $posts_error_message = $posts_response['message'];
}

$edit_post = null;
if (isset($_GET['edit_id']) && filter_var($_GET['edit_id'], FILTER_VALIDATE_INT)) {
    $edit_id = $_GET['edit_id'];
    $edit_response = $blogPostManager->getPostById($edit_id);
    if ($edit_response['success']) {
        $edit_post = $edit_response['data'];
    } else {
        $edit_error_message = $edit_response['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Manager</title>
</head>
<body>
    <h1>Blog Post Manager</h1>

    <?php if (isset($edit_post)): ?>
        <h2>Update Blog Post (ID: <?php echo htmlspecialchars($edit_post['id']); ?>)</h2>
        <form action="../handlers/blog_post_handler.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($edit_post['id']); ?>">
            <div>
                <label for="update_title">Title:</label><br>
                <input type="text" id="update_title" name="title" value="<?php echo htmlspecialchars($edit_post['title']); ?>" required>
            </div>
            <div>
                <label for="update_content">Content:</label><br>
                <textarea id="update_content" name="content" rows="10" cols="50" required><?php echo htmlspecialchars($edit_post['content']); ?></textarea>
            </div>
            <div>
                <button type="submit">Update Post</button>
                <a href="index.php">Cancel</a>
            </div>
        </form>
        <?php if (isset($edit_error_message)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($edit_error_message); ?></p>
        <?php endif; ?>
        <hr>
    <?php else: ?>
        <h2>Create New Blog Post</h2>
        <form action="../handlers/blog_post_handler.php" method="POST">
            <input type="hidden" name="action" value="create">
            <div>
                <label for="create_title">Title:</label><br>
                <input type="text" id="create_title" name="title" required>
            </div>
            <div>
                <label for="create_content">Content:</label><br>
                <textarea id="create_content" name="content" rows="10" cols="50" required></textarea>
            </div>
            <div>
                <button type="submit">Create Post</button>
            </div>
        </form>
        <hr>
    <?php endif; ?>

    <h2>Existing Blog Posts</h2>
    <?php if (isset($posts_error_message)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($posts_error_message); ?></p>
    <?php elseif (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($posts as $post): ?>
                <li>
                    <h3><?php echo htmlspecialchars($post['title']); ?> (ID: <?php echo htmlspecialchars($post['id']); ?>)</h3>
                    <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                    <p><small>Created at: <?php echo htmlspecialchars($post['created_at']); ?></small></p>
                    <a href="index.php?edit_id=<?php echo htmlspecialchars($post['id']); ?>">Edit</a> |
                    <form action="../handlers/blog_post_handler.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                        <button type="submit" style="background:none; border:none; color:blue; cursor:pointer; padding:0; font-size:inherit; text-decoration:underline;">Delete</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
?>