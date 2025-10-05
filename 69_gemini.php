<?php

// config.php
// This file defines global configuration settings for the blog application.
// In a real production environment, sensitive data like database credentials
// should be managed securely (e.g., environment variables, a separate config file
// outside the web root).
define('DB_HOST', 'localhost');
define('DB_NAME', 'blog_db');
define('DB_USER', 'blog_user');
define('DB_PASS', 'your_secure_password'); // IMPORTANT: Change this to a strong, unique password
define('LOG_FILE', __DIR__ . '/crud_operations.log');
define('ERROR_REDIRECT', 'index.php?status=error');
define('SUCCESS_REDIRECT', 'index.php?status=success');

// helpers.php
// This file contains utility functions for database connection, logging,
// input sanitization, and redirection.
function get_db_connection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        log_operation('ERROR', 'Database connection failed: ' . $e->getMessage());
        redirect(ERROR_REDIRECT . '&code=db_connect_failed');
    }
}

function log_operation($operation_type, $details) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = sprintf("[%s] %s: %s" . PHP_EOL, $timestamp, $operation_type, $details);
    file_put_contents(LOG_FILE, $log_message, FILE_APPEND | LOCK_EX);
}

function sanitize_and_escape($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validate_id($id) {
    $id = filter_var($id, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        return false;
    }
    return $id;
}

function redirect($url) {
    header('Location: ' . $url);
    exit();
}

// blog_post_handler.php
// This script handles all CRUD operations (Create, Update, Delete) via POST requests.
require_once 'config.php';
require_once 'helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(ERROR_REDIRECT . '&code=invalid_request_method');
}

$action = isset($_POST['action']) ? sanitize_and_escape($_POST['action']) : '';
$pdo = get_db_connection();

switch ($action) {
    case 'create':
        $title = isset($_POST['title']) ? sanitize_and_escape($_POST['title']) : '';
        $content = isset($_POST['content']) ? sanitize_and_escape($_POST['content']) : '';

        if (empty($title) || empty($content)) {
            log_operation('VALIDATION_ERROR', 'Create: Title or content cannot be empty.');
            redirect(ERROR_REDIRECT . '&code=empty_fields');
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':content', $content, PDO::PARAM_STR);
            $stmt->execute();
            log_operation('CREATE', 'New post created with ID: ' . $pdo->lastInsertId());
            redirect(SUCCESS_REDIRECT . '&msg=post_created');
        } catch (PDOException $e) {
            log_operation('ERROR', 'Create: ' . $e->getMessage());
            redirect(ERROR_REDIRECT . '&code=create_failed');
        }
        break;

    case 'update':
        $id = isset($_POST['id']) ? validate_id($_POST['id']) : false;
        $title = isset($_POST['title']) ? sanitize_and_escape($_POST['title']) : '';
        $content = isset($_POST['content']) ? sanitize_and_escape($_POST['content']) : '';

        if (!$id || empty($title) || empty($content)) {
            log_operation('VALIDATION_ERROR', 'Update: Invalid ID or empty fields.');
            redirect(ERROR_REDIRECT . '&code=invalid_data_update');
        }

        try {
            $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
            $stmt->bindValue(':title', $title, PDO::PARAM_STR);
            $stmt->bindValue(':content', $content, PDO::PARAM_STR);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                log_operation('UPDATE', 'Post updated with ID: ' . $id);
                redirect(SUCCESS_REDIRECT . '&msg=post_updated');
            } else {
                log_operation('INFO', 'Update: Post with ID ' . $id . ' not found or no changes made.');
                redirect(ERROR_REDIRECT . '&code=post_not_found_or_no_change');
            }
        } catch (PDOException $e) {
            log_operation('ERROR', 'Update: ' . $e->getMessage());
            redirect(ERROR_REDIRECT . '&code=update_failed');
        }
        break;

    case 'delete':
        $id = isset($_POST['id']) ? validate_id($_POST['id']) : false;

        if (!$id) {
            log_operation('VALIDATION_ERROR', 'Delete: Invalid ID.');
            redirect(ERROR_REDIRECT . '&code=invalid_id_delete');
        }

        try {
            $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                log_operation('DELETE', 'Post deleted with ID: ' . $id);
                redirect(SUCCESS_REDIRECT . '&msg=post_deleted');
            } else {
                log_operation('INFO', 'Delete: Post with ID ' . $id . ' not found.');
                redirect(ERROR_REDIRECT . '&code=post_not_found_delete');
            }
        } catch (PDOException $e) {
            log_operation('ERROR', 'Delete: ' . $e->getMessage());
            redirect(ERROR_REDIRECT . '&code=delete_failed');
        }
        break;

    default:
        log_operation('WARNING', 'Unknown action received: ' . $action);
        redirect(ERROR_REDIRECT . '&code=unknown_action');
        break;
}

// index.php
// This is the main page for the blog, displaying existing posts and a form for creating new ones.
require_once 'config.php';
require_once 'helpers.php';

$pdo = get_db_connection();
$posts = [];

try {
    $stmt = $pdo->query("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll();
    log_operation('READ', 'All blog posts retrieved.');
} catch (PDOException $e) {
    log_operation('ERROR', 'Failed to retrieve posts: ' . $e->getMessage());
    redirect(ERROR_REDIRECT . '&code=fetch_posts_failed');
}

$status_message = '';
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        $msg = isset($_GET['msg']) ? $_GET['msg'] : 'Operation successful.';
        switch ($msg) {
            case 'post_created': $status_message = 'Blog post created successfully.'; break;
            case 'post_updated': $status_message = 'Blog post updated successfully.'; break;
            case 'post_deleted': $status_message = 'Blog post deleted successfully.'; break;
            default: $status_message = 'Operation successful.'; break;
        }
    } elseif ($_GET['status'] === 'error') {
        $code = isset($_GET['code']) ? $_GET['code'] : 'An unknown error occurred.';
        $status_message = 'Error: An issue occurred. Please try again. Code: ' . $code;
        // In a production environment, you would map error codes to user-friendly messages
        // and avoid exposing the actual codes.
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
    <h1>Blog Posts</h1>

    <?php if (!empty($status_message)): ?>
        <p><?php echo $status_message; ?></p>
    <?php endif; ?>

    <h2>Create New Blog Post</h2>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <div>
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" required>
        </div>
        <div>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="5" required></textarea>
        </div>
        <div>
            <button type="submit">Create Post</button>
        </div>
    </form>

    <h2>Existing Blog Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No blog posts found. Be the first to create one!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <article>
                <h3><?php echo $post['title']; ?></h3>
                <p><?php echo nl2br($post['content']); ?></p>
                <small>Published on: <?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></small><br>
                <a href="edit.php?id=<?php echo $post['id']; ?>">Edit</a>
                <form action="blog_post_handler.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this post?');">Delete</button>
                </form>
            </article>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

<?php

// edit.php
// This script provides a form to update an existing blog post.
require_once 'config.php';
require_once 'helpers.php';

$id = isset($_GET['id']) ? validate_id($_GET['id']) : false;

if (!$id) {
    log_operation('VALIDATION_ERROR', 'Edit: Invalid or missing ID.');
    redirect(ERROR_REDIRECT . '&code=invalid_post_id');
}

$pdo = get_db_connection();
$post = null;

try {
    $stmt = $pdo->prepare("SELECT id, title, content FROM blog_posts WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $post = $stmt->fetch();

    if (!$post) {
        log_operation('INFO', 'Edit: Post with ID ' . $id . ' not found.');
        redirect(ERROR_REDIRECT . '&code=post_not_found');
    }
    log_operation('READ', 'Post retrieved for editing with ID: ' . $id);
} catch (PDOException $e) {
    log_operation('ERROR', 'Edit: Failed to retrieve post ' . $id . ': ' . $e->getMessage());
    redirect(ERROR_REDIRECT . '&code=fetch_post_failed');
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
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
        <div>
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" value="<?php echo $post['title']; ?>" required>
        </div>
        <div>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="5" required><?php echo $post['content']; ?></textarea>
        </div>
        <div>
            <button type="submit">Update Post</button>
            <a href="index.php">Cancel</a>
        </div>
    </form>
</body>
</html>
?>