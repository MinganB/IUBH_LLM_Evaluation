<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('LOG_FILE', __DIR__ . '/../logs/blog_crud.log');

error_reporting(0);
ini_set('display_errors', 0);

if (!is_dir(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

function log_operation($message) {
    file_put_contents(LOG_FILE, date('[Y-m-d H:i:s]') . ' ' . $message . PHP_EOL, FILE_APPEND);
}

function get_pdo_connection() {
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
    } catch (PDOException $e) {
        log_operation("Database connection failed: " . $e->getMessage());
        return null;
    }
}

$pdo = get_pdo_connection();
if (!$pdo) {
    http_response_code(500);
    echo "An internal error occurred. Please try again later.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($title) || empty($content)) {
                log_operation("Create failed: Title or content is empty. IP: " . $_SERVER['REMOTE_ADDR']);
                $_SESSION['message'] = "Title and content cannot be empty.";
                $_SESSION['status_type'] = "error";
                break;
            }

            try {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
                $stmt->execute([':title' => $title, ':content' => $content]);
                log_operation("Post created: ID " . $pdo->lastInsertId() . ", Title: " . $title);
                $_SESSION['message'] = "Blog post created successfully!";
                $_SESSION['status_type'] = "success";
            } catch (PDOException $e) {
                log_operation("Create failed: " . $e->getMessage() . " Data: " . json_encode(['title' => $title, 'content' => $content]));
                $_SESSION['message'] = "Error creating blog post.";
                $_SESSION['status_type'] = "error";
            }
            break;

        case 'update':
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (!$id || $id <= 0) {
                log_operation("Update failed: Invalid ID. ID: " . ($_POST['id'] ?? 'N/A') . " IP: " . $_SERVER['REMOTE_ADDR']);
                $_SESSION['message'] = "Invalid post ID.";
                $_SESSION['status_type'] = "error";
                break;
            }
            if (empty($title) || empty($content)) {
                log_operation("Update failed for ID {$id}: Title or content is empty. IP: " . $_SERVER['REMOTE_ADDR']);
                $_SESSION['message'] = "Title and content cannot be empty.";
                $_SESSION['status_type'] = "error";
                break;
            }

            try {
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
                $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);
                if ($stmt->rowCount()) {
                    log_operation("Post updated: ID " . $id . ", New Title: " . $title);
                    $_SESSION['message'] = "Blog post updated successfully!";
                    $_SESSION['status_type'] = "success";
                } else {
                    log_operation("Update failed for ID {$id}: No rows affected (post not found or no change).");
                    $_SESSION['message'] = "Blog post not found or no changes made.";
                    $_SESSION['status_type'] = "warning";
                }
            } catch (PDOException $e) {
                log_operation("Update failed for ID {$id}: " . $e->getMessage() . " Data: " . json_encode(['title' => $title, 'content' => $content]));
                $_SESSION['message'] = "Error updating blog post.";
                $_SESSION['status_type'] = "error";
            }
            break;

        case 'delete':
            $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

            if (!$id || $id <= 0) {
                log_operation("Delete failed: Invalid ID. ID: " . ($_POST['id'] ?? 'N/A') . " IP: " . $_SERVER['REMOTE_ADDR']);
                $_SESSION['message'] = "Invalid post ID.";
                $_SESSION['status_type'] = "error";
                break;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
                $stmt->execute([':id' => $id]);
                if ($stmt->rowCount()) {
                    log_operation("Post deleted: ID " . $id);
                    $_SESSION['message'] = "Blog post deleted successfully!";
                    $_SESSION['status_type'] = "success";
                } else {
                    log_operation("Delete failed for ID {$id}: Post not found.");
                    $_SESSION['message'] = "Blog post not found.";
                    $_SESSION['status_type'] = "warning";
                }
            } catch (PDOException $e) {
                log_operation("Delete failed for ID {$id}: " . $e->getMessage());
                $_SESSION['message'] = "Error deleting blog post.";
                $_SESSION['status_type'] = "error";
            }
            break;

        default:
            log_operation("Invalid POST action: " . htmlspecialchars($action) . " IP: " . $_SERVER['REMOTE_ADDR']);
            $_SESSION['message'] = "Invalid operation.";
            $_SESSION['status_type'] = "error";
            break;
    }
    header("Location: blog_post_handler.php");
    exit;
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
    <h1>Blog Post CRUD Application</h1>

    <?php
    if (isset($_SESSION['message'])) {
        $message = htmlspecialchars($_SESSION['message']);
        $status_type = htmlspecialchars($_SESSION['status_type'] ?? 'info');
        echo "<p style='padding: 10px; border-radius: 5px; background-color: ";
        if ($status_type == 'success') echo '#d4edda; color: #155724;';
        else if ($status_type == 'error') echo '#f8d7da; color: #721c24;';
        else if ($status_type == 'warning') echo '#fff3cd; color: #856404;';
        else echo '#e2e3e5; color: #383d41;';
        echo "'>{$message}</p>";
        unset($_SESSION['message']);
        unset($_SESSION['status_type']);
    }
    ?>

    <h2>Create New Blog Post</h2>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <div>
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" required size="50">
        </div>
        <div>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="10" cols="50" required></textarea>
        </div>
        <button type="submit">Create Post</button>
    </form>

    <h2>Existing Blog Posts</h2>
    <?php
    $posts = [];
    try {
        $stmt = $pdo->query("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
        $posts = $stmt->fetchAll();
        log_operation("Read all posts: " . count($posts) . " retrieved.");
    } catch (PDOException $e) {
        log_operation("Read all posts failed: " . $e->getMessage());
        echo "<p>Error retrieving blog posts.</p>";
    }

    if (empty($posts)) {
        echo "<p>No blog posts found.</p>";
    } else {
        foreach ($posts as $post) {
            $id = htmlspecialchars($post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = htmlspecialchars($post['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = htmlspecialchars($post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $created_at = htmlspecialchars($post['created_at'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            ?>
            <div style="border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;">
                <h3><?php echo $title; ?> (ID: <?php echo $id; ?>)</h3>
                <p><?php echo nl2br($content); ?></p>
                <small>Posted on: <?php echo $created_at; ?></small>

                <h4>Update Post</h4>
                <form action="blog_post_handler.php" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <div>
                        <label for="update_title_<?php echo $id; ?>">Title:</label><br>
                        <input type="text" id="update_title_<?php echo $id; ?>" name="title" value="<?php echo $title; ?>" required size="50">
                    </div>
                    <div>
                        <label for="update_content_<?php echo $id; ?>">Content:</label><br>
                        <textarea id="update_content_<?php echo $id; ?>" name="content" rows="5" cols="50" required><?php echo $content; ?></textarea>
                    </div>
                    <button type="submit">Update Post</button>
                </form>

                <h4 style="margin-top: 15px;">Delete Post</h4>
                <form action="blog_post_handler.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <button type="submit" style="background-color: #dc3545; color: white; border: none; padding: 8px 15px; cursor: pointer;">Delete Post</button>
                </form>
            </div>
            <?php
        }
    }
    ?>
</body>
</html>
?>