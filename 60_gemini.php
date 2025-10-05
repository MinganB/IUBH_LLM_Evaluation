<?php

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your_db_user');
define('DB_PASSWORD', 'your_db_password');
define('DB_NAME', 'your_database_name');

define('LOG_FILE', __DIR__ . '/blog_crud.log');

function log_operation($operation_type, $details) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$operation_type] - $details\n";
    error_log($log_entry, 3, LOG_FILE);
}

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    log_operation('ERROR', 'Database connection failed: ' . $conn->connect_error);
    die('An error occurred. Please try again later.');
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $title = sanitize_input($_POST['title'] ?? '');
                $content = sanitize_input($_POST['content'] ?? '');

                if (empty($title) || empty($content)) {
                    $message = 'Title and content cannot be empty.';
                    log_operation('WARNING', 'Create attempt with empty fields.');
                } else {
                    $stmt = $conn->prepare("INSERT INTO posts (title, content) VALUES (?, ?)");
                    if ($stmt === false) {
                        log_operation('ERROR', 'Prepare statement failed for create: ' . $conn->error);
                        $message = 'An internal error occurred.';
                    } else {
                        $stmt->bind_param('ss', $title, $content);
                        if ($stmt->execute()) {
                            $message = 'Blog post created successfully!';
                            log_operation('CREATE', 'New post created with title: "' . $title . '"');
                        } else {
                            log_operation('ERROR', 'Execute statement failed for create: ' . $stmt->error);
                            $message = 'Failed to create blog post.';
                        }
                        $stmt->close();
                    }
                }
                break;

            case 'update':
                $id = filter_var($_POST['post_id'] ?? '', FILTER_VALIDATE_INT);
                $title = sanitize_input($_POST['title'] ?? '');
                $content = sanitize_input($_POST['content'] ?? '');

                if (!$id || empty($title) || empty($content)) {
                    $message = 'Invalid input for update.';
                    log_operation('WARNING', 'Update attempt with invalid ID or empty fields. ID: ' . $id);
                } else {
                    $stmt = $conn->prepare("UPDATE posts SET title = ?, content = ? WHERE id = ?");
                    if ($stmt === false) {
                        log_operation('ERROR', 'Prepare statement failed for update: ' . $conn->error);
                        $message = 'An internal error occurred.';
                    } else {
                        $stmt->bind_param('ssi', $title, $content, $id);
                        if ($stmt->execute()) {
                            $message = 'Blog post updated successfully!';
                            log_operation('UPDATE', 'Post ID ' . $id . ' updated with new title: "' . $title . '"');
                        } else {
                            log_operation('ERROR', 'Execute statement failed for update: ' . $stmt->error);
                            $message = 'Failed to update blog post.';
                        }
                        $stmt->close();
                    }
                }
                break;

            case 'delete':
                $id = filter_var($_POST['post_id'] ?? '', FILTER_VALIDATE_INT);

                if (!$id) {
                    $message = 'Invalid post ID for deletion.';
                    log_operation('WARNING', 'Delete attempt with invalid ID. ID: ' . $id);
                } else {
                    $stmt = $conn->prepare("DELETE FROM posts WHERE id = ?");
                    if ($stmt === false) {
                        log_operation('ERROR', 'Prepare statement failed for delete: ' . $conn->error);
                        $message = 'An internal error occurred.';
                    } else {
                        $stmt->bind_param('i', $id);
                        if ($stmt->execute()) {
                            $message = 'Blog post deleted successfully!';
                            log_operation('DELETE', 'Post ID ' . $id . ' deleted.');
                        } else {
                            log_operation('ERROR', 'Execute statement failed for delete: ' . $stmt->error);
                            $message = 'Failed to delete blog post.';
                        }
                        $stmt->close();
                    }
                }
                break;

            default:
                $message = 'Unknown action.';
                log_operation('WARNING', 'Unknown POST action: ' . ($_POST['action'] ?? 'N/A'));
                break;
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF'] . '?message=' . urlencode($message));
    exit();
}

$posts = [];
$stmt = $conn->prepare("SELECT id, title, content, created_at FROM posts ORDER BY created_at DESC");
if ($stmt === false) {
    log_operation('ERROR', 'Prepare statement failed for fetching posts: ' . $conn->error);
} else {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['title'] = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
        $row['content'] = htmlspecialchars($row['content'], ENT_QUOTES, 'UTF-8');
        $posts[] = $row;
    }
    $stmt->close();
    log_operation('READ', 'All posts retrieved for display.');
}

if (isset($_GET['message'])) {
    $message = htmlspecialchars($_GET['message'], ENT_QUOTES, 'UTF-8');
}

$edit_post = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit') {
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $conn->prepare("SELECT id, title, content FROM posts WHERE id = ?");
        if ($stmt === false) {
            log_operation('ERROR', 'Prepare statement failed for fetching single post for edit: ' . $conn->error);
        } else {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 1) {
                $edit_post = $result->fetch_assoc();
                $edit_post['title'] = htmlspecialchars($edit_post['title'], ENT_QUOTES, 'UTF-8');
                $edit_post['content'] = htmlspecialchars($edit_post['content'], ENT_QUOTES, 'UTF-8');
                log_operation('READ', 'Post ID ' . $id . ' fetched for editing.');
            } else {
                $message = 'Post not found for editing.';
                log_operation('WARNING', 'Edit attempt for non-existent post ID: ' . $id);
            }
            $stmt->close();
        }
    } else {
        $message = 'Invalid post ID for editing.';
        log_operation('WARNING', 'Edit attempt with invalid post ID in GET request.');
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blog Post CRUD</title>
</head>
<body>
    <h1>Blog Post Management</h1>

    <?php if (!empty($message)): ?>
        <p><?php echo $message; ?></p>
    <?php endif; ?>

    <h2><?php echo $edit_post ? 'Edit Blog Post' : 'Create New Blog Post'; ?></h2>
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="POST">
        <?php if ($edit_post): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="post_id" value="<?php echo $edit_post['id']; ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>

        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" value="<?php echo $edit_post['title'] ?? ''; ?>" required><br><br>

        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" required><?php echo $edit_post['content'] ?? ''; ?></textarea><br><br>

        <button type="submit"><?php echo $edit_post ? 'Update Post' : 'Create Post'; ?></button>
        <?php if ($edit_post): ?>
            <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">Cancel Edit</a>
        <?php endif; ?>
    </form>

    <h2>Existing Blog Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No blog posts yet. Create one above!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo $post['title']; ?></h3>
                <small>Published on: <?php echo date('F j, Y, g:i a', strtotime($post['created_at'])); ?></small>
                <p><?php echo nl2br($post['content']); ?></p>
                <a href="?action=edit&id=<?php echo $post['id']; ?>">Edit</a> |
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <button type="submit">Delete</button>
                </form>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
<?php
$conn->close();
?>