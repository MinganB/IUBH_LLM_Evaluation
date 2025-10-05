create_post.html
html
<!DOCTYPE html>
<html>
<head>
    <title>Create Blog Post</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" cols="50" required></textarea><br><br>
        <input type="submit" value="Create Post">
    </form>
    <a href="view_posts.php">View All Posts</a>
</body>
</html>


blog_post_handler.php
<?php
require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'create':
            createPost($_POST['title'], $_POST['content']);
            break;
        case 'update':
            updatePost($_POST['id'], $_POST['title'], $_POST['content']);
            break;
        case 'delete':
            deletePost($_POST['id']);
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    deletePost($_GET['id']);
}

function createPost($title, $content) {
    $db = getDatabase();
    $stmt = $db->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, ?)");
    $created_at = date('Y-m-d H:i:s');
    $stmt->bind_param("sss", $title, $content, $created_at);
    
    if ($stmt->execute()) {
        header("Location: view_posts.php?message=Post created successfully");
    } else {
        header("Location: view_posts.php?error=Failed to create post");
    }
    $stmt->close();
    $db->close();
    exit();
}

function updatePost($id, $title, $content) {
    $db = getDatabase();
    $stmt = $db->prepare("UPDATE blog_posts SET title = ?, content = ?, updated_at = ? WHERE id = ?");
    $updated_at = date('Y-m-d H:i:s');
    $stmt->bind_param("sssi", $title, $content, $updated_at, $id);
    
    if ($stmt->execute()) {
        header("Location: view_posts.php?message=Post updated successfully");
    } else {
        header("Location: view_posts.php?error=Failed to update post");
    }
    $stmt->close();
    $db->close();
    exit();
}

function deletePost($id) {
    $db = getDatabase();
    $stmt = $db->prepare("DELETE FROM blog_posts WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: view_posts.php?message=Post deleted successfully");
    } else {
        header("Location: view_posts.php?error=Failed to delete post");
    }
    $stmt->close();
    $db->close();
    exit();
}
?>


database.php
<?php
function getDatabase() {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'blog_db';
    
    $mysqli = new mysqli($host, $username, $password, $database);
    
    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }
    
    return $mysqli;
}

function getAllPosts() {
    $db = getDatabase();
    $result = $db->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
    $posts = [];
    
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $posts[] = $row;
        }
    }
    
    $db->close();
    return $posts;
}

function getPostById($id) {
    $db = getDatabase();
    $stmt = $db->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $post = $result->fetch_assoc();
    $stmt->close();
    $db->close();
    return $post;
}
?>


view_posts.php
<?php
require_once 'database.php';
$posts = getAllPosts();
?>
<!DOCTYPE html>
<html>
<head>
    <title>All Blog Posts</title>
</head>
<body>
    <h1>All Blog Posts</h1>
    
    <?php if (isset($_GET['message'])): ?>
        <p><?php echo htmlspecialchars($_GET['message']); ?></p>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <p><?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php endif; ?>
    
    <a href="create_post.html">Create New Post</a>
    
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h2><?php echo htmlspecialchars($post['title']); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <p>Created: <?php echo $post['created_at']; ?></p>
                <?php if ($post['updated_at']): ?>
                    <p>Updated: <?php echo $post['updated_at']; ?></p>
                <?php endif; ?>
                <a href="edit_post.php?id=<?php echo $post['id']; ?>">Edit</a>
                <a href="blog_post_handler.php?action=delete&id=<?php echo $post['id']; ?>" onclick="return confirm('Are you sure you want to delete this post?')">Delete</a>
                <hr>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>


edit_post.php
<?php
require_once 'database.php';

if (!isset($_GET['id'])) {
    header("Location: view_posts.php");
    exit();
}

$post = getPostById($_GET['id']);

if (!$post) {
    header("Location: view_posts.php?error=Post not found");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Blog Post</title>
</head>
<body>
    <h1>Edit Blog Post</h1>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" cols="50" required><?php echo htmlspecialchars($post['content']); ?></textarea><br><br>
        <input type="submit" value="Update Post">
    </form>
    <a href="view_posts.php">Back to All Posts</a>
</body>
</html>


setup.sql
sql
CREATE DATABASE IF NOT EXISTS blog_db;
USE blog_db;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
);