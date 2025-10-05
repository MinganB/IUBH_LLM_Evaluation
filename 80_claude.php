<?php
class BlogPost {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function create($title, $content) {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$title, $content]);
            
            if ($result) {
                return ['success' => true, 'message' => 'Blog post created successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to create blog post'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }
    
    public function readAll() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['success' => true, 'message' => 'Posts retrieved successfully', 'data' => $posts];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Failed to retrieve posts'];
        }
    }
    
    public function update($id, $title, $content) {
        try {
            $stmt = $this->pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
            $result = $stmt->execute([$title, $content, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
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
            
            if ($result && $stmt->rowCount() > 0) {
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

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_users', $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$blogPost = new BlogPost($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
    
    switch ($action) {
        case 'create':
            $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
            $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
            
            if (empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Title and content are required']);
                exit;
            }
            
            $result = $blogPost->create($title, $content);
            echo json_encode($result);
            break;
            
        case 'update':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
            $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_STRING);
            
            if (!$id || empty($title) || empty($content)) {
                echo json_encode(['success' => false, 'message' => 'ID, title, and content are required']);
                exit;
            }
            
            $result = $blogPost->update($id, $title, $content);
            echo json_encode($result);
            break;
            
        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'Valid ID is required']);
                exit;
            }
            
            $result = $blogPost->delete($id);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = filter_input(INPUT_GET, 'action', FILTER_SANITIZE_STRING);
    
    if ($action === 'read') {
        $result = $blogPost->readAll();
        echo json_encode($result);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
?>


html
<!DOCTYPE html>
<html>
<head>
    <title>Blog Post Management</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form id="createForm" method="POST" action="../handlers/blog_post_handler.php">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" required>
        <br><br>
        <label for="content">Content:</label>
        <textarea id="content" name="content" required rows="10" cols="50"></textarea>
        <br><br>
        <input type="submit" value="Create Post">
    </form>

    <h1>Update Blog Post</h1>
    <form id="updateForm" method="POST" action="../handlers/blog_post_handler.php">
        <input type="hidden" name="action" value="update">
        <label for="update_id">Post ID:</label>
        <input type="number" id="update_id" name="id" required>
        <br><br>
        <label for="update_title">Title:</label>
        <input type="text" id="update_title" name="title" required>
        <br><br>
        <label for="update_content">Content:</label>
        <textarea id="update_content" name="content" required rows="10" cols="50"></textarea>
        <br><br>
        <input type="submit" value="Update Post">
    </form>

    <h1>Delete Blog Post</h1>
    <form id="deleteForm" method="POST" action="../handlers/blog_post_handler.php">
        <input type="hidden" name="action" value="delete">
        <label for="delete_id">Post ID:</label>
        <input type="number" id="delete_id" name="id" required>
        <br><br>
        <input type="submit" value="Delete Post">
    </form>

    <h1>All Blog Posts</h1>
    <div id="posts"></div>
    <button onclick="loadPosts()">Load Posts</button>

    <script>
        function loadPosts() {
            fetch('../handlers/blog_post_handler.php?action=read')
                .then(response => response.json())
                .then(data => {
                    const postsDiv = document.getElementById('posts');
                    if (data.success) {
                        let html = '';
                        data.data.forEach(post => {
                            html += `<div><h3>${post.title}</h3><p>${post.content}</p><small>Created: ${post.created_at}</small><hr></div>`;
                        });
                        postsDiv.innerHTML = html;
                    } else {
                        postsDiv.innerHTML = '<p>Error: ' + data.message + '</p>';
                    }
                });
        }

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
    </script>
</body>
</html>
?>