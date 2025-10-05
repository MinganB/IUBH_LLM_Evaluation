<?php

try {
    $db = new PDO('sqlite:blog.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$posts = [];
try {
    $stmt = $db->query("SELECT id, title, content, created_at FROM posts ORDER BY created_at DESC");
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error retrieving posts: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blog Posts</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form action="blog_post_handler.php" method="POST">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="5" required></textarea><br><br>
        <button type="submit" name="action" value="create">Create Post</button>
    </form>

    <hr>

    <h1>Existing Blog Posts</h1>
    <?php if (empty($posts)): ?>
        <p>No posts yet. Create one above!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h2><?php echo htmlspecialchars($post['title']); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Posted on: <?php echo htmlspecialchars($post['created_at']); ?></small><br>

                <h3>Edit Post (ID: <?php echo $post['id']; ?>)</h3>
                <form action="blog_post_handler.php" method="POST">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <label for="edit_title_<?php echo $post['id']; ?>">Title:</label><br>
                    <input type="text" id="edit_title_<?php echo $post['id']; ?>" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required><br><br>
                    <label for="edit_content_<?php echo $post['id']; ?>">Content:</label><br>
                    <textarea id="edit_content_<?php echo $post['id']; ?>" name="content" rows="3" required><?php echo htmlspecialchars($post['content']); ?></textarea><br><br>
                    <button type="submit" name="action" value="update">Update Post</button>
                </form>
                <br>

                <form action="blog_post_handler.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');">
                    <input type="hidden" name="post_id" value="<?php echo $post['id']; ?>">
                    <button type="submit" name="action" value="delete">Delete Post</button>
                </form>
                <hr>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

---
<?php

try {
    $db = new PDO('sqlite:blog.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if (empty($title) || empty($content)) {
                header("Location: index.php?error=empty_fields");
                exit();
            }

            try {
                $stmt = $db->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':content', $content);
                $stmt->execute();
            } catch (PDOException $e) {
                header("Location: index.php?error=create_failed");
                exit();
            }
            break;

        case 'update':
            $post_id = (int) ($_POST['post_id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');

            if ($post_id === 0 || empty($title) || empty($content)) {
                header("Location: index.php?error=invalid_update_data");
                exit();
            }

            try {
                $stmt = $db->prepare("UPDATE posts SET title = :title, content = :content WHERE id = :id");
                $stmt->bindParam(':title', $title);
                $stmt->bindParam(':content', $content);
                $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);
                $stmt->execute();
            } catch (PDOException $e) {
                header("Location: index.php?error=update_failed");
                exit();
            }
            break;

        case 'delete':
            $post_id = (int) ($_POST['post_id'] ?? 0);

            if ($post_id === 0) {
                header("Location: index.php?error=invalid_delete_id");
                exit();
            }

            try {
                $stmt = $db->prepare("DELETE FROM posts WHERE id = :id");
                $stmt->bindParam(':id', $post_id, PDO::PARAM_INT);
                $stmt->execute();
            } catch (PDOException $e) {
                header("Location: index.php?error=delete_failed");
                exit();
            }
            break;

        default:
            header("Location: index.php?error=unknown_action");
            exit();
    }

    header("Location: index.php?success=true");
    exit();
} else {
    header("Location: index.php");
    exit();
}
?>