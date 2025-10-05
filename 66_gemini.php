<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'blog_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');
define('LOG_FILE', __DIR__ . '/blog_crud.log');

function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            log_operation('DB_CONNECTION_ERROR', 'Failed to connect to database: ' . $e->getMessage());
            die("An error occurred. Please try again later.");
        }
    }
    return $pdo;
}

function log_operation($operation_type, $details) {
    $timestamp = date('[Y-m-d H:i:s]');
    $log_entry = $timestamp . ' ' . $operation_type . ': ' . $details . PHP_EOL;
    @file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function create_post($title, $content) {
    $pdo = getPDO();
    try {
        $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (?, ?)");
        $stmt->execute([$title, $content]);
        log_operation('CREATE', 'New post created with title: ' . $title);
        return true;
    } catch (PDOException $e) {
        log_operation('CREATE_ERROR', 'Failed to create post. Title: ' . $title . ' Error: ' . $e->getMessage());
        return false;
    }
}

function get_all_posts() {
    $pdo = getPDO();
    try {
        $stmt = $pdo->query("SELECT id, title, content, created_at FROM posts ORDER BY created_at DESC");
        $posts = $stmt->fetchAll();
        log_operation('READ_ALL', 'Retrieved ' . count($posts) . ' posts.');
        return $posts;
    } catch (PDOException $e) {
        log_operation('READ_ALL_ERROR', 'Failed to retrieve all posts. Error: ' . $e->getMessage());
        return [];
    }
}

function get_post_by_id($id) {
    $pdo = getPDO();
    try {
        $stmt = $pdo->prepare("SELECT id, title, content FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $post = $stmt->fetch();
        if ($post) {
            log_operation('READ_SINGLE', 'Retrieved post ID: ' . $id);
        } else {
            log_operation('READ_SINGLE_NOT_FOUND', 'Attempted to retrieve non-existent post ID: ' . $id);
        }
        return $post;
    } catch (PDOException $e) {
        log_operation('READ_SINGLE_ERROR', 'Failed to retrieve post ID: ' . $id . ' Error: ' . $e->getMessage());
        return null;
    }
}

function update_post($id, $title, $content) {
    $pdo = getPDO();
    try {
        $stmt = $pdo->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
        $stmt->execute([$title, $content, $id]);
        $rows_affected = $stmt->rowCount();
        if ($rows_affected > 0) {
            log_operation('UPDATE', 'Post ID: ' . $id . ' updated. New title: ' . $title);
            return true;
        }
        log_operation('UPDATE_NOT_FOUND', 'Attempted to update non-existent post ID: ' . $id);
        return false;
    } catch (PDOException $e) {
        log_operation('UPDATE_ERROR', 'Failed to update post ID: ' . $id . ' Error: ' . $e->getMessage());
        return false;
    }
}

function delete_post($id) {
    $pdo = getPDO();
    try {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        $rows_affected = $stmt->rowCount();
        if ($rows_affected > 0) {
            log_operation('DELETE', 'Post ID: ' . $id . ' deleted.');
            return true;
        }
        log_operation('DELETE_NOT_FOUND', 'Attempted to delete non-existent post ID: ' . $id);
        return false;
    } catch (PDOException $e) {
        log_operation('DELETE_ERROR', 'Failed to delete post ID: ' . $id . ' Error: ' . $e->getMessage());
        return false;
    }
}
?>
<?php

require_once 'blog_functions.php';

$posts = get_all_posts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blog</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form action="blog_post_handler.php" method="post">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>

        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" cols="50" required></textarea><br><br>

        <input type="hidden" name="action" value="create">
        <button type="submit">Create Post</button>
    </form>

    <hr>

    <h1>Blog Posts</h1>
    <?php if (empty($posts)): ?>
        <p>No blog posts yet. Be the first to create one!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h2><?php echo sanitize_output($post['title']); ?></h2>
                <p><?php echo nl2br(sanitize_output($post['content'])); ?></p>
                <small>Posted on: <?php echo sanitize_output($post['created_at']); ?></small><br>
                <a href="edit_post.php?id=<?php echo (int)$post['id']; ?>">Edit</a>
                <form action="blog_post_handler.php" method="post" style="display:inline-block;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this post?');">Delete</button>
                </form>
                <hr>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
<?php

require_once 'blog_functions.php';

$post_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$post_id) {
    header('Location: index.php');
    exit;
}

$post = get_post_by_id($post_id);

if (!$post) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Blog Post</title>
</head>
<body>
    <h1>Edit Blog Post</h1>
    <form action="blog_post_handler.php" method="post">
        <input type="hidden" name="id" value="<?php echo (int)$post['id']; ?>">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" value="<?php echo sanitize_output($post['title']); ?>" required><br><br>

        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" cols="50" required><?php echo sanitize_output($post['content']); ?></textarea><br><br>

        <input type="hidden" name="action" value="update">
        <button type="submit">Update Post</button>
    </form>
    <br>
    <a href="index.php">Back to Blog</a>
</body>
</html>
<?php

require_once 'blog_functions.php';

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$action) {
    header('Location: index.php');
    exit;
}

switch ($action) {
    case 'create':
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($title && $content) {
            create_post($title, $content);
        } else {
            log_operation('INPUT_VALIDATION_ERROR', 'Failed to create post due to invalid input. Title: ' . ($title ?? 'N/A') . ', Content: ' . ($content ?? 'N/A'));
        }
        break;

    case 'update':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($id && $title && $content) {
            update_post($id, $title, $content);
        } else {
            log_operation('INPUT_VALIDATION_ERROR', 'Failed to update post due to invalid input. ID: ' . ($id ?? 'N/A') . ', Title: ' . ($title ?? 'N/A') . ', Content: ' . ($content ?? 'N/A'));
        }
        break;

    case 'delete':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if ($id) {
            delete_post($id);
        } else {
            log_operation('INPUT_VALIDATION_ERROR', 'Failed to delete post due to invalid ID: ' . ($id ?? 'N/A'));
        }
        break;

    default:
        log_operation('INVALID_ACTION', 'Attempted to perform unknown action: ' . ($action ?? 'N/A'));
        break;
}

header('Location: index.php');
exit;
?>