<?php
// /classes/BlogPost.php
class BlogPost {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function create($title, $content) {
        try {
            $title = htmlspecialchars(trim($title), ENT_QUOTES, 'UTF-8');
            $content = htmlspecialchars(trim($content), ENT_QUOTES, 'UTF-8');
            
            if (empty($title) || empty($content)) {
                return json_encode(['success' => false, 'message' => 'Title and content are required']);
            }
            
            $stmt = $this->db->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$title, $content]);
            
            if ($result) {
                return json_encode(['success' => true, 'message' => 'Blog post created successfully']);
            } else {
                return json_encode(['success' => false, 'message' => 'Failed to create blog post']);
            }
        } catch (Exception $e) {
            return json_encode(['success' => false, 'message' => 'Database error occurred']);
        }
    }
    
    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return json_encode(['success' => true, 'message' => 'Posts retrieved successfully', 'data' => $posts]);
        } catch (Exception $e) {
            return json_encode(['success' => false, 'message' => 'Failed to retrieve posts']);
        }
    }
    
    public function getById($id) {
        try {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if (!$id) {
                return json_encode(['success' => false, 'message' => 'Invalid post ID']);
            }
            
            $stmt = $this->db->prepare("SELECT id, title, content, created_at FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post) {
                return json_encode(['success' => true, 'message' => 'Post retrieved successfully', 'data' => $post]);
            } else {
                return json_encode(['success' => false, 'message' => 'Post not found']);
            }
        } catch (Exception $e) {
            return json_encode(['success' => false, 'message' => 'Failed to retrieve post']);
        }
    }
    
    public function update($id, $title, $content) {
        try {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if (!$id) {
                return json_encode(['success' => false, 'message' => 'Invalid post ID']);
            }
            
            $title = htmlspecialchars(trim($title), ENT_QUOTES, 'UTF-8');
            $content = htmlspecialchars(trim($content), ENT_QUOTES, 'UTF-8');
            
            if (empty($title) || empty($content)) {
                return json_encode(['success' => false, 'message' => 'Title and content are required']);
            }
            
            $stmt = $this->db->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
            $result = $stmt->execute([$title, $content, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                return json_encode(['success' => true, 'message' => 'Blog post updated successfully']);
            } else {
                return json_encode(['success' => false, 'message' => 'Post not found or no changes made']);
            }
        } catch (Exception $e) {
            return json_encode(['success' => false, 'message' => 'Failed to update post']);
        }
    }
    
    public function delete($id) {
        try {
            $id = filter_var($id, FILTER_VALIDATE_INT);
            if (!$id) {
                return json_encode(['success' => false, 'message' => 'Invalid post ID']);
            }
            
            $stmt = $this->db->prepare("DELETE FROM blog_posts WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                return json_encode(['success' => true, 'message' => 'Blog post deleted successfully']);
            } else {
                return json_encode(['success' => false, 'message' => 'Post not found']);
            }
        } catch (Exception $e) {
            return json_encode(['success' => false, 'message' => 'Failed to delete post']);
        }
    }
}
?>


<?php
// /classes/Database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'your_username';
    private $password = 'your_password';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4", 
                                $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed");
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function prepare($query) {
        return $this->pdo->prepare($query);
    }
}
?>


<?php
// /handlers/blog_post_handler.php
header('Content-Type: application/json');

require_once '../classes/Database.php';
require_once '../classes/BlogPost.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $database = new Database();
    $blogPost = new BlogPost($database->getConnection());
    
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    
    switch ($action) {
        case 'create':
            $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
            $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
            echo $blogPost->create($title, $content);
            break;
            
        case 'update':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
            $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
            echo $blogPost->update($id, $title, $content);
            break;
            
        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            echo $blogPost->delete($id);
            break;
            
        case 'get':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if ($id) {
                echo $blogPost->getById($id);
            } else {
                echo $blogPost->getAll();
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>


<?php
// /public/blog_form.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Management</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form action="../handlers/blog_post_handler.php" method="POST" id="createForm">
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
            <input type="submit" value="Create Post">
        </div>
    </form>

    <h2>Update Blog Post</h2>
    <form action="../handlers/blog_post_handler.php" method="POST" id="updateForm">
        <input type="hidden" name="action" value="update">
        <div>
            <label for="update_id">Post ID:</label>
            <input type="number" id="update_id" name="id" required min="1">
        </div>
        <div>
            <label for="update_title">Title:</label>
            <input type="text" id="update_title" name="title" required maxlength="255">
        </div>
        <div>
            <label for="update_content">Content:</label>
            <textarea id="update_content" name="content" required rows="10" cols="50"></textarea>
        </div>
        <div>
            <input type="submit" value="Update Post">
        </div>
    </form>

    <h2>Delete Blog Post</h2>
    <form action="../handlers/blog_post_handler.php" method="POST" id="deleteForm">
        <input type="hidden" name="action" value="delete">
        <div>
            <label for="delete_id">Post ID:</label>
            <input type="number" id="delete_id" name="id" required min="1">
        </div>
        <div>
            <input type="submit" value="Delete Post" onclick="return confirm('Are you sure you want to delete this post?')">
        </div>
    </form>

    <h2>Get All Posts</h2>
    <form action="../handlers/blog_post_handler.php" method="POST" id="getAllForm">
        <input type="hidden" name="action" value="get">
        <div>
            <input type="submit" value="Get All Posts">
        </div>
    </form>

    <h2>Get Single Post</h2>
    <form action="../handlers/blog_post_handler.php" method="POST" id="getSingleForm">
        <input type="hidden" name="action" value="get">
        <div>
            <label for="get_id">Post ID:</label>
            <input type="number" id="get_id" name="id" required min="1">
        </div>
        <div>
            <input type="submit" value="Get Post">
        </div>
    </form>
</body>
</html>


<?php
// /public/blog_display.php
require_once '../classes/Database.php';
require_once '../classes/BlogPost.php';

try {
    $database = new Database();
    $blogPost = new BlogPost($database->getConnection());
    $result = json_decode($blogPost->getAll(), true);
    
    if ($result['success']) {
        $posts = $result['data'];
    } else {
        $posts = [];
    }
} catch (Exception $e) {
    $posts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Posts</title>
</head>
<body>
    <h1>Blog Posts</h1>
    
    <?php if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <article>
                <h2><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p><small>Posted on: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></small></p>
                <div><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></div>
                <p><small>Post ID: <?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?></small></p>
                <hr>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <p><a href="blog_form.php">Manage Blog Posts</a></p>
</body>
</html>
?>