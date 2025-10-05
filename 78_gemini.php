<?php

declare(strict_types=1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('LOG_FILE', __DIR__ . '/crud_log.txt');

function logOperation(string $operation, string $message, string $level = 'INFO'): void
{
    $timestamp = date('Y-m-d H:i:s');
    error_log(sprintf("[%s] [%s] %s\n", $timestamp, $level, $message), 3, LOG_FILE);
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    logOperation('Database Connection', 'Failed to connect to database: ' . $e->getMessage(), 'CRITICAL');
    exit('An unexpected error occurred. Please try again later.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';

            if (empty($title) || empty($content)) {
                exit('Title and content are required.');
            }

            $title = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            try {
                $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (?, ?)");
                $stmt->execute([$title, $content]);
                logOperation('Create', 'New blog post created with title: ' . $title);
                header('Location: blog_post_handler.php');
                exit;
            } catch (PDOException $e) {
                logOperation('Create', 'Failed to create blog post: ' . $e->getMessage(), 'ERROR');
                exit('Failed to create post. Please try again.');
            }
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';

            if (!filter_var($id, FILTER_VALIDATE_INT) || (int)$id <= 0) {
                exit('Invalid post ID.');
            }

            if (empty($title) || empty($content)) {
                exit('Title and content are required.');
            }

            $id = (int)$id;
            $title = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            try {
                $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
                $stmt->execute([$title, $content, $id]);
                if ($stmt->rowCount() > 0) {
                    logOperation('Update', 'Blog post ID ' . $id . ' updated. New title: ' . $title);
                    header('Location: blog_post_handler.php');
                    exit;
                } else {
                    exit('Post not found or no changes made.');
                }
            } catch (PDOException $e) {
                logOperation('Update', 'Failed to update blog post ID ' . $id . ': ' . $e->getMessage(), 'ERROR');
                exit('Failed to update post. Please try again.');
            }
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';

            if (!filter_var($id, FILTER_VALIDATE_INT) || (int)$id <= 0) {
                exit('Invalid post ID.');
            }

            $id = (int)$id;

            try {
                $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) {
                    logOperation('Delete', 'Blog post ID ' . $id . ' deleted.');
                    header('Location: blog_post_handler.php');
                    exit;
                } else {
                    exit('Post not found.');
                }
            } catch (PDOException $e) {
                logOperation('Delete', 'Failed to delete blog post ID ' . $id . ': ' . $e->getMessage(), 'ERROR');
                exit('Failed to delete post. Please try again.');
            }
            break;

        default:
            exit('Invalid action.');
    }
}

try {
    $stmt = $pdo->query("SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll();
    logOperation('Read', 'All blog posts retrieved.');
} catch (PDOException $e) {
    logOperation('Read', 'Failed to retrieve blog posts: ' . $e->getMessage(), 'ERROR');
    exit('Failed to retrieve posts. Please try again.');
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
    <h1>Create New Blog Post</h1>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="5" required></textarea><br><br>
        <button type="submit">Create Post</button>
    </form>

    <hr>

    <h1>Existing Blog Posts</h1>
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div style="border: 1px solid #ccc; padding: 10px; margin-bottom: 15px;">
                <h2><?php echo htmlspecialchars($post['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?></p>
                <small>Created: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></small><br>
                <small>Last Updated: <?php echo htmlspecialchars($post['updated_at'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></small>

                <h3>Update Post</h3>
                <form action="blog_post_handler.php" method="POST">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
                    <label for="update_title_<?php echo htmlspecialchars((string)$post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">Title:</label><br>
                    <input type="text" id="update_title_<?php echo htmlspecialchars((string)$post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" required><br><br>
                    <label for="update_content_<?php echo htmlspecialchars((string)$post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">Content:</label><br>
                    <textarea id="update_content_<?php echo htmlspecialchars((string)$post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" name="content" rows="3" required><?php echo htmlspecialchars($post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></textarea><br><br>
                    <button type="submit">Update Post</button>
                </form>

                <br>

                <h3>Delete Post</h3>
                <form action="blog_post_handler.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars((string)$post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
                    <button type="submit">Delete Post</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
?>