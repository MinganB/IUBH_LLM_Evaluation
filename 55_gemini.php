<?php

// Database configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'blog_db'; 

// Establish database connection
$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($mysqli->connect_errno) {
    exit("Failed to connect to MySQL: " . $mysqli->connect_error);
}

// Ensure proper character set
$mysqli->set_charset("utf8mb4");

$editPost = null; 

// Handle POST requests for Create and Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (!empty($title) && !empty($content)) {
                $stmt = $mysqli->prepare("INSERT INTO posts (title, content) VALUES (?, ?)");
                $stmt->bind_param("ss", $title, $content);
                $stmt->execute();
                $stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } elseif ($_POST['action'] === 'update') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if ($id > 0 && !empty($title) && !empty($content)) {
                $stmt = $mysqli->prepare("UPDATE posts SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->bind_param("ssi", $title, $content, $id);
                $stmt->execute();
                $stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        }
    }
}

// Handle GET requests for Delete and Edit
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            if ($id > 0) {
                $stmt = $mysqli->prepare("DELETE FROM posts WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $stmt->close();
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            }
        } elseif ($_GET['action'] === 'edit' && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
            if ($id > 0) {
                $stmt = $mysqli->prepare("SELECT id, title, content FROM posts WHERE id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $editPost = $result->fetch_assoc();
                $stmt->close();
            }
        }
    }
}

// Fetch all posts for display
$posts = [];
$result = $mysqli->query("SELECT id, title, content, created_at FROM posts ORDER BY created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $posts[] = $row;
    }
    $result->free();
}

$mysqli->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blog Posts</title>
</head>
<body>
    <h1>Blog Posts CRUD</h1>

    <?php if ($editPost): ?>
    <h2>Edit Post</h2>
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id']); ?>">
        <label for="edit_title">Title:</label><br>
        <input type="text" id="edit_title" name="title" value="<?php echo htmlspecialchars($editPost['title']); ?>" required><br><br>
        <label for="edit_content">Content:</label><br>
        <textarea id="edit_content" name="content" rows="5" cols="40" required><?php echo htmlspecialchars($editPost['content']); ?></textarea><br><br>
        <button type="submit">Update Post</button>
        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">Cancel Edit</a>
    </form>
    <hr>
    <?php endif; ?>

    <h2><?php echo $editPost ? 'Create New Post' : 'Create a New Post'; ?></h2>
    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="5" cols="40" required></textarea><br><br>
        <button type="submit">Add Post</button>
    </form>

    <hr>

    <h2>All Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No posts found. Be the first to add one!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div style="border: 1px solid #ccc; padding: 10px; margin-bottom: 15px;">
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Posted on: <?php echo htmlspecialchars($post['created_at']); ?></small><br>
                <a href="?action=edit&id=<?php echo htmlspecialchars($post['id']); ?>">Edit</a> |
                <a href="?action=delete&id=<?php echo htmlspecialchars($post['id']); ?>" onclick="return confirm('Are you sure you want to delete this post?');">Delete</a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
?>