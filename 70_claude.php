<?php
// /classes/BlogPost.php
class BlogPost {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function create($title, $content) {
        try {
            $stmt = $this->db->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$title, $content]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Blog post created successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to create blog post'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'message' => 'Posts retrieved successfully', 'data' => $posts];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT id, title, content, created_at FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post) {
                return ['success' => true, 'message' => 'Post retrieved successfully', 'data' => $post];
            } else {
                return ['success' => false, 'message' => 'Post not found'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function update($id, $title, $content) {
        try {
            $stmt = $this->db->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
            $result = $stmt->execute([$title, $content, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Blog post updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update blog post or post not found'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM blog_posts WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Blog post deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete blog post or post not found'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>


<?php
// /classes/Database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
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

$database = new Database();
$blogPost = new BlogPost($database->getConnection());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            
            if (empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Title and content are required']);
                exit;
            }
            
            $result = $blogPost->create($title, $content);
            echo json_encode($result);
            break;
            
        case 'update':
            $id = $_POST['id'] ?? '';
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            
            if (empty($id) || empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'ID, title and content are required']);
                exit;
            }
            
            $result = $blogPost->update($id, $title, $content);
            echo json_encode($result);
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID is required']);
                exit;
            }
            
            $result = $blogPost->delete($id);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'getAll':
            $result = $blogPost->getAll();
            echo json_encode($result);
            break;
            
        case 'getById':
            $id = $_GET['id'] ?? '';
            
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'ID is required']);
                exit;
            }
            
            $result = $blogPost->getById($id);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>


<?php
// /public/create_post.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Create Blog Post</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form action="../handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" required>
        
        <label for="content">Content:</label>
        <textarea id="content" name="content" rows="10" required></textarea>
        
        <button type="submit">Create Post</button>
    </form>
</body>
</html>


<?php
// /public/edit_post.php
$postId = $_GET['id'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Blog Post</title>
</head>
<body>
    <h1>Edit Blog Post</h1>
    <form action="../handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($postId); ?>">
        
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" required>
        
        <label for="content">Content:</label>
        <textarea id="content" name="content" rows="10" required></textarea>
        
        <button type="submit">Update Post</button>
    </form>
    
    <script>
        if (<?php echo json_encode($postId); ?>) {
            fetch('../handlers/blog_post_handler.php?action=getById&id=' + <?php echo json_encode($postId); ?>)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('title').value = data.data.title;
                        document.getElementById('content').value = data.data.content;
                    }
                });
        }
    </script>
</body>
</html>


<?php
// /public/view_posts.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Blog Posts</title>
</head>
<body>
    <h1>All Blog Posts</h1>
    <div id="posts-container"></div>
    
    <script>
        fetch('../handlers/blog_post_handler.php?action=getAll')
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('posts-container');
                if (data.success && data.data) {
                    data.data.forEach(post => {
                        const postDiv = document.createElement('div');
                        postDiv.innerHTML = `
                            <h2>${post.title}</h2>
                            <p>${post.content}</p>
                            <small>Created: ${post.created_at}</small>
                            <div>
                                <a href="edit_post.php?id=${post.id}">Edit</a>
                                <button onclick="deletePost(${post.id})">Delete</button>
                            </div>
                            <hr>
                        `;
                        container.appendChild(postDiv);
                    });
                } else {
                    container.innerHTML = '<p>No posts found</p>';
                }
            });
        
        function deletePost(id) {
            if (confirm('Are you sure you want to delete this post?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                
                fetch('../handlers/blog_post_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        }
    </script>
</body>
</html>
?>