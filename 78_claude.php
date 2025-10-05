<?php
// blog_form.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blog Post Management</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required maxlength="255">
        </div>
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" required maxlength="10000" rows="10" cols="50"></textarea>
        </div>
        <div>
            <button type="submit">Create Post</button>
        </div>
    </form>

    <h2>Existing Blog Posts</h2>
    <div id="posts-list">
        <?php
        include 'blog_post_handler.php';
        $posts = getAllPosts();
        foreach ($posts as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                <p><small>Created: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></small></p>
                
                <form action="blog_post_handler.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="text" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
                    <textarea name="content" required maxlength="10000" rows="3" cols="30"><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    <button type="submit">Update</button>
                </form>
                
                <form action="blog_post_handler.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this post?')">Delete</button>
                </form>
            </div>
            <hr>
        <?php endforeach; ?>
    </div>
</body>
</html>


<?php
// blog_post_handler.php

function getDatabaseConnection() {
    $host = 'localhost';
    $dbname = 'db_users';
    $username = 'your_username';
    $password = 'your_password';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        return $pdo;
    } catch (PDOException $e) {
        logOperation('Database connection failed');
        die('Database connection failed');
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateInput($title, $content) {
    if (empty($title) || empty($content)) {
        return false;
    }
    if (strlen($title) > 255 || strlen($content) > 10000) {
        return false;
    }
    return true;
}

function logOperation($operation) {
    $logFile = '/var/log/blog_operations.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $operation\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function createPost($title, $content) {
    try {
        $pdo = getDatabaseConnection();
        $title = sanitizeInput($title);
        $content = sanitizeInput($content);
        
        if (!validateInput($title, $content)) {
            logOperation('Create post failed - invalid input');
            return false;
        }
        
        $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
        $result = $stmt->execute([$title, $content]);
        
        if ($result) {
            logOperation("Post created - Title: $title");
        }
        
        return $result;
    } catch (PDOException $e) {
        logOperation('Create post failed - database error');
        return false;
    }
}

function getAllPosts() {
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
        $stmt->execute();
        $posts = $stmt->fetchAll();
        
        logOperation('Posts retrieved');
        return $posts;
    } catch (PDOException $e) {
        logOperation('Get posts failed - database error');
        return [];
    }
}

function updatePost($id, $title, $content) {
    try {
        $pdo = getDatabaseConnection();
        $id = (int)$id;
        $title = sanitizeInput($title);
        $content = sanitizeInput($content);
        
        if ($id <= 0 || !validateInput($title, $content)) {
            logOperation('Update post failed - invalid input');
            return false;
        }
        
        $stmt = $pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
        $result = $stmt->execute([$title, $content, $id]);
        
        if ($result && $stmt->rowCount() > 0) {
            logOperation("Post updated - ID: $id, Title: $title");
            return true;
        }
        
        logOperation("Update post failed - ID: $id not found");
        return false;
    } catch (PDOException $e) {
        logOperation('Update post failed - database error');
        return false;
    }
}

function deletePost($id) {
    try {
        $pdo = getDatabaseConnection();
        $id = (int)$id;
        
        if ($id <= 0) {
            logOperation('Delete post failed - invalid ID');
            return false;
        }
        
        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result && $stmt->rowCount() > 0) {
            logOperation("Post deleted - ID: $id");
            return true;
        }
        
        logOperation("Delete post failed - ID: $id not found");
        return false;
    } catch (PDOException $e) {
        logOperation('Delete post failed - database error');
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            if (createPost($title, $content)) {
                header('Location: blog_form.php');
                exit;
            } else {
                echo 'Failed to create post';
            }
            break;
            
        case 'update':
            $id = $_POST['id'] ?? '';
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            if (updatePost($id, $title, $content)) {
                header('Location: blog_form.php');
                exit;
            } else {
                echo 'Failed to update post';
            }
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? '';
            if (deletePost($id)) {
                header('Location: blog_form.php');
                exit;
            } else {
                echo 'Failed to delete post';
            }
            break;
            
        default:
            echo 'Invalid action';
    }
}
?>


sql
-- database_schema.sql
CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
?>