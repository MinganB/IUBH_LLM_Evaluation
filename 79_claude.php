<?php
class BlogPost {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO('mysql:host=localhost;dbname=db_users', 'username', 'password');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed');
        }
    }
    
    public function create($title, $content) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$title, $content]);
            return ['success' => true, 'message' => 'Blog post created successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to create blog post'];
        }
    }
    
    public function read() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return ['success' => true, 'message' => 'Posts retrieved successfully', 'data' => $posts];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to retrieve blog posts'];
        }
    }
    
    public function update($id, $title, $content) {
        try {
            $stmt = $this->pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
            $result = $stmt->execute([$title, $content, $id]);
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Blog post updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Blog post not found or no changes made'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to update blog post'];
        }
    }
    
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
            $result = $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Blog post deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Blog post not found'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to delete blog post'];
        }
    }
}
?>


<?php
require_once '../classes/BlogPost.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $blogPost = new BlogPost();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                if (empty($_POST['title']) || empty($_POST['content'])) {
                    echo json_encode(['success' => false, 'message' => 'Title and content are required']);
                    exit;
                }
                $result = $blogPost->create($_POST['title'], $_POST['content']);
                echo json_encode($result);
                break;
                
            case 'read':
                $result = $blogPost->read();
                echo json_encode($result);
                break;
                
            case 'update':
                if (empty($_POST['id']) || empty($_POST['title']) || empty($_POST['content'])) {
                    echo json_encode(['success' => false, 'message' => 'ID, title and content are required']);
                    exit;
                }
                $result = $blogPost->update($_POST['id'], $_POST['title'], $_POST['content']);
                echo json_encode($result);
                break;
                
            case 'delete':
                if (empty($_POST['id'])) {
                    echo json_encode(['success' => false, 'message' => 'ID is required']);
                    exit;
                }
                $result = $blogPost->delete($_POST['id']);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No action specified']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management</title>
</head>
<body>
    <h1>Blog Post Management</h1>
    
    <h2>Create New Post</h2>
    <form id="createForm" method="POST">
        <input type="hidden" name="action" value="create">
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required>
        </div>
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" rows="10" cols="50" required></textarea>
        </div>
        <button type="submit">Create Post</button>
    </form>
    
    <h2>Update Post</h2>
    <form id="updateForm" method="POST">
        <input type="hidden" name="action" value="update">
        <div>
            <label for="update_id">Post ID:</label>
            <input type="number" id="update_id" name="id" required>
        </div>
        <div>
            <label for="update_title">Title:</label>
            <input type="text" id="update_title" name="title" required>
        </div>
        <div>
            <label for="update_content">Content:</label>
            <textarea id="update_content" name="content" rows="10" cols="50" required></textarea>
        </div>
        <button type="submit">Update Post</button>
    </form>
    
    <h2>Delete Post</h2>
    <form id="deleteForm" method="POST">
        <input type="hidden" name="action" value="delete">
        <div>
            <label for="delete_id">Post ID:</label>
            <input type="number" id="delete_id" name="id" required>
        </div>
        <button type="submit">Delete Post</button>
    </form>
    
    <h2>All Posts</h2>
    <button id="loadPosts">Load All Posts</button>
    <div id="postsContainer"></div>
    
    <script>
        document.getElementById('createForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('../handlers/blog_post_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    this.reset();
                }
            });
        });
        
        document.getElementById('updateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('../handlers/blog_post_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    this.reset();
                }
            });
        });
        
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('../handlers/blog_post_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    this.reset();
                }
            });
        });
        
        document.getElementById('loadPosts').addEventListener('click', function() {
            const formData = new FormData();
            formData.append('action', 'read');
            fetch('../handlers/blog_post_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById('postsContainer');
                if (data.success && data.data) {
                    container.innerHTML = '';
                    data.data.forEach(post => {
                        const postDiv = document.createElement('div');
                        postDiv.innerHTML = `
                            <h3>ID: ${post.id} - ${post.title}</h3>
                            <p>${post.content}</p>
                            <small>Created: ${post.created_at}</small>
                            <hr>
                        `;
                        container.appendChild(postDiv);
                    });
                } else {
                    container.innerHTML = '<p>' + data.message + '</p>';
                }
            });
        });
    </script>
</body>
</html>
?>