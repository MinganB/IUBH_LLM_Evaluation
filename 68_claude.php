**create_post.html**
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
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required maxlength="255">
        </div>
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" required rows="10" cols="50"></textarea>
        </div>
        <div>
            <button type="submit">Create Post</button>
        </div>
    </form>
</body>
</html>


**blog_post_handler.php**
<?php
session_start();

class BlogPost {
    private $pdo;
    
    public function __construct($database_path = 'blog.db') {
        try {
            $this->pdo = new PDO("sqlite:$database_path");
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->createTable();
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    private function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS blog_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        $this->pdo->exec($sql);
    }
    
    public function create($title, $content) {
        $sql = "INSERT INTO blog_posts (title, content) VALUES (:title, :content)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':title' => $title,
            ':content' => $content
        ]);
    }
    
    public function read($id = null) {
        if ($id) {
            $sql = "SELECT * FROM blog_posts WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT * FROM blog_posts ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    
    public function update($id, $title, $content) {
        $sql = "UPDATE blog_posts SET title = :title, content = :content, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':title' => $title,
            ':content' => $content
        ]);
    }
    
    public function delete($id) {
        $sql = "DELETE FROM blog_posts WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function validateInput($data, $required_fields) {
    $errors = [];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = ucfirst($field) . " is required";
        }
    }
    return $errors;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$blogPost = new BlogPost();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $errors = validateInput($_POST, ['title', 'content']);
            if (empty($errors)) {
                $title = sanitizeInput($_POST['title']);
                $content = sanitizeInput($_POST['content']);
                
                if (strlen($title) > 255) {
                    $errors[] = "Title must be 255 characters or less";
                }
                
                if (empty($errors)) {
                    if ($blogPost->create($title, $content)) {
                        header("Location: view_posts.php?success=created");
                        exit;
                    } else {
                        $errors[] = "Failed to create post";
                    }
                }
            }
            break;
            
        case 'update':
            $errors = validateInput($_POST, ['id', 'title', 'content']);
            if (empty($errors)) {
                $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
                $title = sanitizeInput($_POST['title']);
                $content = sanitizeInput($_POST['content']);
                
                if (!$id) {
                    $errors[] = "Invalid post ID";
                } elseif (strlen($title) > 255) {
                    $errors[] = "Title must be 255 characters or less";
                }
                
                if (empty($errors)) {
                    if ($blogPost->update($id, $title, $content)) {
                        header("Location: view_posts.php?success=updated");
                        exit;
                    } else {
                        $errors[] = "Failed to update post";
                    }
                }
            }
            break;
            
        case 'delete':
            if (isset($_POST['id'])) {
                $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
                if ($id && $blogPost->delete($id)) {
                    header("Location: view_posts.php?success=deleted");
                    exit;
                } else {
                    $errors[] = "Failed to delete post";
                }
            }
            break;
    }
    
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header("Location: " . $_SERVER['HTTP_REFERER'] ?? 'view_posts.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'get') {
        header('Content-Type: application/json');
        $id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
        echo json_encode($blogPost->read($id));
        exit;
    }
}

header("Location: view_posts.php");
exit;
?>


**view_posts.php**
<?php
session_start();
require_once 'blog_post_handler.php';

$blogPost = new BlogPost();
$posts = $blogPost->read();
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blog Posts</title>
</head>
<body>
    <h1>Blog Posts</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="success">
            <p>Post <?php echo htmlspecialchars($success); ?> successfully!</p>
        </div>
    <?php endif; ?>
    
    <a href="create_post.html">Create New Post</a>
    
    <div class="posts">
        <?php if (empty($posts)): ?>
            <p>No posts found.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div class="post">
                    <h2><?php echo htmlspecialchars($post['title']); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                    <p><small>Created: <?php echo htmlspecialchars($post['created_at']); ?></small></p>
                    <?php if ($post['updated_at'] !== $post['created_at']): ?>
                        <p><small>Updated: <?php echo htmlspecialchars($post['updated_at']); ?></small></p>
                    <?php endif; ?>
                    
                    <div class="actions">
                        <a href="edit_post.php?id=<?php echo $post['id']; ?>">Edit</a>
                        
                        <form action="blog_post_handler.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this post?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </div>
                </div>
                <hr>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>


**edit_post.php**
<?php
session_start();
require_once 'blog_post_handler.php';

$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: view_posts.php");
    exit;
}

$blogPost = new BlogPost();
$post = $blogPost->read($id);

if (!$post) {
    header("Location: view_posts.php");
    exit;
}

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Blog Post</title>
</head>
<body>
    <h1>Edit Blog Post</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
        
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required maxlength="255">
        </div>
        
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" required rows="10" cols="50"><?php echo htmlspecialchars($post['content']); ?></textarea>
        </div>
        
        <div>
            <button type="submit">Update Post</button>
            <a href="view_posts.php">Cancel</a>
        </div>
    </form>
</body>
</html>


**single_post.php**
<?php
require_once 'blog_post_handler.php';

$id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
if (!$id) {
    header("Location: view_posts.php");
    exit;
}

$blogPost = new BlogPost();
$post = $blogPost->read($id);

if (!$post) {
    header("Location: view_posts.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($post['title']); ?></title>
</head>
<body>
    <article>
        <h1><?php echo htmlspecialchars($post['title']); ?></h1>
        <div class="content">
            <?php echo nl2br(htmlspecialchars($post['content'])); ?>
        </div>
        <div class="meta">
            <p><small>Published: <?php echo htmlspecialchars($post['created_at']); ?></small></p>
            <?php if ($post['updated_at'] !== $post['created_at']): ?>
                <p><small>Last updated: <?php echo htmlspecialchars($post['updated_at']); ?></small></p>
            <?php endif; ?>
        </div>
    </article>
    
    <div class="navigation">
        <a href="view_posts.php">← Back to all posts</a>
    </div>
</body>
</html>