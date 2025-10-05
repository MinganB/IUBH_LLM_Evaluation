<?php
$host = 'localhost';
$dbname = 'db_users';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function createPost($pdo, $title, $content) {
    $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
    return $stmt->execute([$title, $content]);
}

function getAllPosts($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts ORDER BY created_at DESC");
    $stmt->execute();
    return $stmt->fetchAll();
}

function getPostById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function updatePost($pdo, $id, $title, $content) {
    $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$title, $content, $id]);
}

function deletePost($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
    return $stmt->execute([$id]);
}

$posts = getAllPosts($pdo);
$editPost = null;

if (isset($_GET['edit'])) {
    $editPost = getPostById($pdo, $_GET['edit']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management</title>
</head>
<body>
    <h1>Blog Post Management</h1>
    
    <h2><?php echo $editPost ? 'Edit Post' : 'Create New Post'; ?></h2>
    <form action="blog_post_handler.php" method="POST">
        <?php if ($editPost): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id']); ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>
        
        <div>
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>" required>
        </div>
        
        <div>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="10" cols="50" required><?php echo $editPost ? htmlspecialchars($editPost['content']) : ''; ?></textarea>
        </div>
        
        <div>
            <input type="submit" value="<?php echo $editPost ? 'Update Post' : 'Create Post'; ?>">
            <?php if ($editPost): ?>
                <a href="index.php">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
    
    <h2>Existing Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title']); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                <small>Created: <?php echo htmlspecialchars($post['created_at']); ?></small>
                <?php if (isset($post['updated_at']) && $post['updated_at']): ?>
                    <small> | Updated: <?php echo htmlspecialchars($post['updated_at']); ?></small>
                <?php endif; ?>
                <div>
                    <a href="?edit=<?php echo $post['id']; ?>">Edit</a>
                    <form action="blog_post_handler.php" method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                        <input type="submit" value="Delete" onclick="return confirm('Are you sure you want to delete this post?');">
                    </form>
                </div>
                <hr>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>


<?php
$host = 'localhost';
$dbname = 'db_users';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function createPost($pdo, $title, $content) {
    $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
    return $stmt->execute([$title, $content]);
}

function updatePost($pdo, $id, $title, $content) {
    $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$title, $content, $id]);
}

function deletePost($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
    return $stmt->execute([$id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            if (isset($_POST['title']) && isset($_POST['content'])) {
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                
                if (!empty($title) && !empty($content)) {
                    if (createPost($pdo, $title, $content)) {
                        header('Location: index.php?success=created');
                        exit;
                    } else {
                        header('Location: index.php?error=create_failed');
                        exit;
                    }
                } else {
                    header('Location: index.php?error=empty_fields');
                    exit;
                }
            }
            break;
            
        case 'update':
            if (isset($_POST['id']) && isset($_POST['title']) && isset($_POST['content'])) {
                $id = (int)$_POST['id'];
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                
                if ($id > 0 && !empty($title) && !empty($content)) {
                    if (updatePost($pdo, $id, $title, $content)) {
                        header('Location: index.php?success=updated');
                        exit;
                    } else {
                        header('Location: index.php?error=update_failed');
                        exit;
                    }
                } else {
                    header('Location: index.php?error=invalid_data');
                    exit;
                }
            }
            break;
            
        case 'delete':
            if (isset($_POST['id'])) {
                $id = (int)$_POST['id'];
                
                if ($id > 0) {
                    if (deletePost($pdo, $id)) {
                        header('Location: index.php?success=deleted');
                        exit;
                    } else {
                        header('Location: index.php?error=delete_failed');
                        exit;
                    }
                } else {
                    header('Location: index.php?error=invalid_id');
                    exit;
                }
            }
            break;
            
        default:
            header('Location: index.php?error=invalid_action');
            exit;
    }
}

header('Location: index.php?error=invalid_request');
exit;
?>


sql
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL
);
?>