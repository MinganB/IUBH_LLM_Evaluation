<?php
session_start();

class BlogPostManager {
    private $pdo;

    public function __construct() {
        $host = 'localhost';
        $db = 'db_users';
        $user = 'root'; // IMPORTANT: Replace with your actual database user
        $pass = '';     // IMPORTANT: Replace with your actual database password
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            error_log('PDO Connection Error: ' . $e->getMessage());
            die('Database connection failed.');
        }
    }

    public function createPost($title, $content) {
        $sql = "INSERT INTO blog_posts (title, content) VALUES (:title, :content)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['title' => $title, 'content' => $content]);
            return ['status' => 'success', 'message' => 'Post created successfully.', 'id' => $this->pdo->lastInsertId()];
        } catch (PDOException $e) {
            error_log('Create Post Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to create post.'];
        }
    }

    public function readAllPosts() {
        $sql = "SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC";
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Read All Posts Error: ' . $e->getMessage());
            return [];
        }
    }

    public function readPostById($id) {
        $sql = "SELECT id, title, content, created_at FROM blog_posts WHERE id = :id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Read Post By ID Error: ' . $e->getMessage());
            return null;
        }
    }

    public function updatePost($id, $title, $content) {
        $sql = "UPDATE blog_posts SET title = :title, content = :content WHERE id = :id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id, 'title' => $title, 'content' => $content]);
            return ['status' => 'success', 'message' => 'Post updated successfully.'];
        } catch (PDOException $e) {
            error_log('Update Post Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to update post.'];
        }
    }

    public function deletePost($id) {
        $sql = "DELETE FROM blog_posts WHERE id = :id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            if ($stmt->rowCount() > 0) {
                return ['status' => 'success', 'message' => 'Post deleted successfully.'];
            } else {
                return ['status' => 'error', 'message' => 'Post not found or already deleted.'];
            }
        } catch (PDOException $e) {
            error_log('Delete Post Error: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Failed to delete post.'];
        }
    }
}

if (basename(__FILE__) == basename($_SERVER['PHP_SELF'])) {
    $manager = new BlogPostManager();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = isset($_POST['action']) ? $_POST['action'] : '';

        switch ($action) {
            case 'create':
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $content = isset($_POST['content']) ? trim($_POST['content']) : '';
                if (!empty($title) && !empty($content)) {
                    $result = $manager->createPost($title, $content);
                    $_SESSION['message'] = $result;
                } else {
                    $_SESSION['message'] = ['status' => 'error', 'message' => 'Title and content are required.'];
                }
                break;

            case 'update':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $content = isset($_POST['content']) ? trim($_POST['content']) : '';
                if ($id && !empty($title) && !empty($content)) {
                    $result = $manager->updatePost($id, $title, $content);
                    $_SESSION['message'] = $result;
                } else {
                    $_SESSION['message'] = ['status' => 'error', 'message' => 'ID, title, and content are required for update.'];
                }
                break;

            case 'delete':
                $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if ($id) {
                    $result = $manager->deletePost($id);
                    $_SESSION['message'] = $result;
                } else {
                    $_SESSION['message'] = ['status' => 'error', 'message' => 'ID is required for delete.'];
                }
                break;

            default:
                $_SESSION['message'] = ['status' => 'error', 'message' => 'Invalid action specified.'];
                break;
        }
        header('Location: index.php');
        exit();
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';

        switch ($action) {
            case 'read_all':
                header('Content-Type: application/json');
                echo json_encode($manager->readAllPosts());
                break;
            case 'read_one':
                $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
                if ($id) {
                    header('Content-Type: application/json');
                    echo json_encode($manager->readPostById($id));
                } else {
                    header('Content-Type: application/json');
                    echo json_encode(['status' => 'error', 'message' => 'ID is required for reading a single post.']);
                }
                break;
            default:
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Unsupported GET action.']);
                break;
        }
        exit();
    }
}
?>

<?php
session_start();
require_once 'blog_post_handler.php';

$blogManager = new BlogPostManager();
$posts = $blogManager->readAllPosts();

$message = null;
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post CRUD</title>
</head>
<body>
    <h1>Blog Posts</h1>

    <?php if ($message): ?>
        <p style="color: <?php echo $message['status'] === 'success' ? 'green' : 'red'; ?>;">
            <?php echo htmlspecialchars($message['message']); ?>
        </p>
    <?php endif; ?>

    <h2>Create New Post</h2>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="5" required></textarea><br><br>
        <button type="submit">Create Post</button>
    </form>

    <h2>Existing Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <hr>
            <h3><?php echo htmlspecialchars($post['title']); ?></h3>
            <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
            <small>Posted on: <?php echo htmlspecialchars($post['created_at']); ?></small><br>

            <h3>Edit Post (ID: <?php echo htmlspecialchars($post['id']); ?>)</h3>
            <form action="blog_post_handler.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                <label for="edit_title_<?php echo htmlspecialchars($post['id']); ?>">Title:</label><br>
                <input type="text" id="edit_title_<?php echo htmlspecialchars($post['id']); ?>" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required><br><br>
                <label for="edit_content_<?php echo htmlspecialchars($post['id']); ?>">Content:</label><br>
                <textarea id="edit_content_<?php echo htmlspecialchars($post['id']); ?>" name="content" rows="3" required><?php echo htmlspecialchars($post['content']); ?></textarea><br><br>
                <button type="submit">Update Post</button>
            </form>

            <form action="blog_post_handler.php" method="POST" style="margin-top: 10px;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                <button type="submit" onclick="return confirm('Are you sure you want to delete this post?');">Delete Post</button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
?>