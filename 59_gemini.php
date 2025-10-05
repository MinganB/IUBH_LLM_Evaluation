<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'blog_db');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function redirect($url, $message = null, $type = null) {
    if ($message && $type) {
        $_SESSION['message'] = ['text' => $message, 'type' => $type];
    }
    header("Location: " . $url);
    exit();
}

function escape_html($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('index.php', 'Invalid CSRF token.', 'error');
    }

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        redirect('index.php', 'Title and content cannot be empty.', 'error');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
        $stmt->execute([':title' => $title, ':content' => $content]);
        redirect('index.php', 'Post created successfully!', 'success');
    } catch (PDOException $e) {
        error_log("Create Post Error: " . $e->getMessage());
        redirect('index.php', 'Error creating post.', 'error');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('index.php', 'Invalid CSRF token.', 'error');
    }

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!$id || empty($title) || empty($content)) {
        redirect('index.php', 'Invalid input for updating post.', 'error');
    }

    try {
        $stmt = $pdo->prepare("UPDATE posts SET title = :title, content = :content, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);
        redirect('index.php', 'Post updated successfully!', 'success');
    } catch (PDOException $e) {
        error_log("Update Post Error: " . $e->getMessage());
        redirect('index.php', 'Error updating post.', 'error');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect('index.php', 'Invalid CSRF token.', 'error');
    }

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    if (!$id) {
        redirect('index.php', 'Invalid post ID for deletion.', 'error');
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
        $stmt->execute([':id' => $id]);
        redirect('index.php', 'Post deleted successfully!', 'success');
    } catch (PDOException $e) {
        error_log("Delete Post Error: " . $e->getMessage());
        redirect('index.php', 'Error deleting post.', 'error');
    }
}

$posts = [];
try {
    $stmt = $pdo->query("SELECT id, title, content, created_at, updated_at FROM posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch Posts Error: " . $e->getMessage());
    $_SESSION['message'] = ['text' => 'Error retrieving posts.', 'type' => 'error'];
}

$post_to_edit = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit') {
    $id = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT);
    if ($id) {
        try {
            $stmt = $pdo->prepare("SELECT id, title, content FROM posts WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $post_to_edit = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Fetch Post for Edit Error: " . $e->getMessage());
            redirect('index.php', 'Error retrieving post for editing.', 'error');
        }
    } else {
        redirect('index.php', 'Invalid post ID for editing.', 'error');
    }
}

$csrf_token = generate_csrf_token();

$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Management</title>
</head>
<body>
    <h1>Blog Post Management</h1>

    <?php if ($message): ?>
        <p><?php echo escape_html($message['text']); ?></p>
    <?php endif; ?>

    <?php if ($post_to_edit): ?>
        <h2>Edit Blog Post</h2>
        <form action="index.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo escape_html($post_to_edit['id']); ?>">
            <label for="title_edit">Title:</label><br>
            <input type="text" id="title_edit" name="title" value="<?php echo escape_html($post_to_edit['title']); ?>" required size="50"><br><br>
            <label for="content_edit">Content:</label><br>
            <textarea id="content_edit" name="content" rows="10" cols="80" required><?php echo escape_html($post_to_edit['content']); ?></textarea><br><br>
            <button type="submit">Update Post</button>
            <a href="index.php">Cancel Edit</a>
        </form>
    <?php else: ?>
        <h2>Add New Blog Post</h2>
        <form action="index.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">
            <input type="hidden" name="action" value="create">
            <label for="title_new">Title:</label><br>
            <input type="text" id="title_new" name="title" required size="50"><br><br>
            <label for="content_new">Content:</label><br>
            <textarea id="content_new" name="content" rows="10" cols="80" required></textarea><br><br>
            <button type="submit">Create Post</button>
        </form>
    <?php endif; ?>

    <hr>

    <h2>Existing Blog Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($posts as $post): ?>
                <li>
                    <h3><?php echo escape_html($post['title']); ?></h3>
                    <p>
                        <?php echo nl2br(escape_html($post['content'])); ?>
                    </p>
                    <p>
                        <small>Posted on: <?php echo escape_html($post['created_at']); ?>
                        <?php if ($post['created_at'] !== $post['updated_at']): ?>
                            (Last updated: <?php echo escape_html($post['updated_at']); ?>)
                        <?php endif; ?>
                        </small>
                    </p>
                    <a href="index.php?action=edit&id=<?php echo escape_html($post['id']); ?>">Edit</a> |
                    <form action="index.php" method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                        <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo escape_html($post['id']); ?>">
                        <button type="submit" style="background:none; border:none; padding:0; color:blue; text-decoration:underline; cursor:pointer;">Delete</button>
                    </form>
                </li>
                <hr>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

</body>
</html>
?>