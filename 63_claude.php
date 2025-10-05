<?php
class BlogPost {
    private $db;
    private $logFile = '/var/log/blog_operations.log';
    
    public function __construct() {
        $this->db = new PDO('mysql:host=localhost;dbname=db_users', $_ENV['DB_USER'], $_ENV['DB_PASS']);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    private function logOperation($operation, $postId = null) {
        $timestamp = date('Y-m-d H:i:s');
        $message = "[$timestamp] Operation: $operation";
        if ($postId) {
            $message .= " | Post ID: $postId";
        }
        $message .= " | IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
        file_put_contents($this->logFile, $message, FILE_APPEND | LOCK_EX);
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    private function validateTitle($title) {
        return !empty($title) && strlen($title) <= 255;
    }
    
    private function validateContent($content) {
        return !empty($content) && strlen($content) <= 65535;
    }
    
    public function create($title, $content) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            
            if (!$this->validateTitle($title)) {
                return json_encode(['success' => false, 'message' => 'Invalid title']);
            }
            
            if (!$this->validateContent($content)) {
                return json_encode(['success' => false, 'message' => 'Invalid content']);
            }
            
            $stmt = $this->db->prepare('INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())');
            $result = $stmt->execute([$title, $content]);
            
            if ($result) {
                $postId = $this->db->lastInsertId();
                $this->logOperation('CREATE', $postId);
                return json_encode(['success' => true, 'message' => 'Post created successfully']);
            }
            
            return json_encode(['success' => false, 'message' => 'Failed to create post']);
        } catch (Exception $e) {
            $this->logOperation('CREATE_ERROR');
            return json_encode(['success' => false, 'message' => 'Operation failed']);
        }
    }
    
    public function read($id = null) {
        try {
            if ($id) {
                $stmt = $this->db->prepare('SELECT id, title, content, created_at FROM blog_posts WHERE id = ?');
                $stmt->execute([$id]);
                $post = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($post) {
                    $this->logOperation('READ', $id);
                    return json_encode(['success' => true, 'message' => 'Post retrieved', 'data' => $post]);
                }
                
                return json_encode(['success' => false, 'message' => 'Post not found']);
            } else {
                $stmt = $this->db->prepare('SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC');
                $stmt->execute();
                $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $this->logOperation('READ_ALL');
                return json_encode(['success' => true, 'message' => 'Posts retrieved', 'data' => $posts]);
            }
        } catch (Exception $e) {
            $this->logOperation('READ_ERROR');
            return json_encode(['success' => false, 'message' => 'Operation failed']);
        }
    }
    
    public function update($id, $title, $content) {
        try {
            $title = $this->sanitizeInput($title);
            $content = $this->sanitizeInput($content);
            
            if (!$this->validateTitle($title)) {
                return json_encode(['success' => false, 'message' => 'Invalid title']);
            }
            
            if (!$this->validateContent($content)) {
                return json_encode(['success' => false, 'message' => 'Invalid content']);
            }
            
            $stmt = $this->db->prepare('UPDATE blog_posts SET title = ?, content = ? WHERE id = ?');
            $result = $stmt->execute([$title, $content, $id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation('UPDATE', $id);
                return json_encode(['success' => true, 'message' => 'Post updated successfully']);
            }
            
            return json_encode(['success' => false, 'message' => 'Post not found or no changes made']);
        } catch (Exception $e) {
            $this->logOperation('UPDATE_ERROR');
            return json_encode(['success' => false, 'message' => 'Operation failed']);
        }
    }
    
    public function delete($id) {
        try {
            $stmt = $this->db->prepare('DELETE FROM blog_posts WHERE id = ?');
            $result = $stmt->execute([$id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logOperation('DELETE', $id);
                return json_encode(['success' => true, 'message' => 'Post deleted successfully']);
            }
            
            return json_encode(['success' => false, 'message' => 'Post not found']);
        } catch (Exception $e) {
            $this->logOperation('DELETE_ERROR');
            return json_encode(['success' => false, 'message' => 'Operation failed']);
        }
    }
}
?>


<?php
require_once '../classes/BlogPost.php';

header('Content-Type: application/json');

$blogPost = new BlogPost();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            echo $blogPost->create($title, $content);
            break;
            
        case 'update':
            $id = $_POST['id'] ?? '';
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            if (is_numeric($id)) {
                echo $blogPost->update($id, $title, $content);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            }
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? '';
            if (is_numeric($id)) {
                echo $blogPost->delete($id);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = $_GET['id'] ?? null;
    if ($id && is_numeric($id)) {
        echo $blogPost->read($id);
    } else {
        echo $blogPost->read();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
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
    <h1>Create New Blog Post</h1>
    <form id="createForm" action="../handlers/blog_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label>
        <input type="text" id="title" name="title" required maxlength="255">
        <label for="content">Content:</label>
        <textarea id="content" name="content" required rows="10" cols="50"></textarea>
        <button type="submit">Create Post</button>
    </form>

    <h1>Update Blog Post</h1>
    <form id="updateForm" action="../handlers/blog_handler.php" method="POST">
        <input type="hidden" name="action" value="update">
        <label for="update_id">Post ID:</label>
        <input type="number" id="update_id" name="id" required>
        <label for="update_title">Title:</label>
        <input type="text" id="update_title" name="title" required maxlength="255">
        <label for="update_content">Content:</label>
        <textarea id="update_content" name="content" required rows="10" cols="50"></textarea>
        <button type="submit">Update Post</button>
    </form>

    <h1>Delete Blog Post</h1>
    <form id="deleteForm" action="../handlers/blog_handler.php" method="POST">
        <input type="hidden" name="action" value="delete">
        <label for="delete_id">Post ID:</label>
        <input type="number" id="delete_id" name="id" required>
        <button type="submit">Delete Post</button>
    </form>

    <h1>View All Posts</h1>
    <button onclick="loadAllPosts()">Load All Posts</button>
    <div id="posts"></div>

    <h1>View Single Post</h1>
    <input type="number" id="single_post_id" placeholder="Enter Post ID">
    <button onclick="loadSinglePost()">Load Post</button>
    <div id="single_post"></div>

    <script>
        document.getElementById('createForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'Post created successfully!');
        });

        document.getElementById('updateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'Post updated successfully!');
        });

        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            submitForm(this, 'Post deleted successfully!');
        });

        function submitForm(form, successMessage) {
            fetch(form.action, {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(successMessage);
                    form.reset();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred');
            });
        }

        function loadAllPosts() {
            fetch('../handlers/blog_handler.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayPosts(data.data);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred');
            });
        }

        function loadSinglePost() {
            const id = document.getElementById('single_post_id').value;
            if (!id) return;

            fetch(`../handlers/blog_handler.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displaySinglePost(data.data);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                alert('An error occurred');
            });
        }

        function displayPosts(posts) {
            const postsDiv = document.getElementById('posts');
            postsDiv.innerHTML = '';
            posts.forEach(post => {
                const postDiv = document.createElement('div');
                postDiv.innerHTML = `
                    <h3>ID: ${post.id} - ${post.title}</h3>
                    <p>${post.content}</p>
                    <small>Created: ${post.created_at}</small>
                    <hr>
                `;
                postsDiv.appendChild(postDiv);
            });
        }

        function displaySinglePost(post) {
            const postDiv = document.getElementById('single_post');
            postDiv.innerHTML = `
                <h3>ID: ${post.id} - ${post.title}</h3>
                <p>${post.content}</p>
                <small>Created: ${post.created_at}</small>
            `;
        }
    </script>
</body>
</html>
?>