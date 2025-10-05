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
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
// /classes/BlogPost.php
require_once 'Database.php';

class BlogPost {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function create($title, $content) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$title, $content]);
            
            return [
                'success' => true,
                'message' => 'Blog post created successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create blog post: ' . $e->getMessage()
            ];
        }
    }
    
    public function getAll() {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->query("SELECT * FROM blog_posts ORDER BY created_at DESC");
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'message' => 'Posts retrieved successfully',
                'data' => $posts
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve posts: ' . $e->getMessage()
            ];
        }
    }
    
    public function getById($id) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($post) {
                return [
                    'success' => true,
                    'message' => 'Post retrieved successfully',
                    'data' => $post
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Post not found'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve post: ' . $e->getMessage()
            ];
        }
    }
    
    public function update($id, $title, $content) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
            $result = $stmt->execute([$title, $content, $id]);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Blog post updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No post found with that ID'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update blog post: ' . $e->getMessage()
            ];
        }
    }
    
    public function delete($id) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("DELETE FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Blog post deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No post found with that ID'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete blog post: ' . $e->getMessage()
            ];
        }
    }
}
?>


<?php
// /handlers/create_post.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

require_once '../classes/BlogPost.php';

$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

if (empty($title) || empty($content)) {
    echo json_encode([
        'success' => false,
        'message' => 'Title and content are required'
    ]);
    exit;
}

$blogPost = new BlogPost();
$result = $blogPost->create($title, $content);

echo json_encode($result);
?>


<?php
// /handlers/get_posts.php
header('Content-Type: application/json');

require_once '../classes/BlogPost.php';

$blogPost = new BlogPost();
$result = $blogPost->getAll();

echo json_encode($result);
?>


<?php
// /handlers/get_post.php
header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Valid post ID is required'
    ]);
    exit;
}

require_once '../classes/BlogPost.php';

$id = (int)$_GET['id'];
$blogPost = new BlogPost();
$result = $blogPost->getById($id);

echo json_encode($result);
?>


<?php
// /handlers/update_post.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

require_once '../classes/BlogPost.php';

$id = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');

if (empty($id) || empty($title) || empty($content)) {
    echo json_encode([
        'success' => false,
        'message' => 'ID, title and content are required'
    ]);
    exit;
}

$blogPost = new BlogPost();
$result = $blogPost->update($id, $title, $content);

echo json_encode($result);
?>


<?php
// /handlers/delete_post.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Valid post ID is required'
    ]);
    exit;
}

require_once '../classes/BlogPost.php';

$id = (int)$_POST['id'];
$blogPost = new BlogPost();
$result = $blogPost->delete($id);

echo json_encode($result);
?>


html
<!-- /public/create_post.html -->
<!DOCTYPE html>
<html>
<head>
    <title>Create Blog Post</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    
    <form id="createPostForm" method="POST" action="../handlers/create_post.php">
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
    
    <div id="message"></div>
    
    <script>
        document.getElementById('createPostForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../handlers/create_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('message');
                messageDiv.textContent = data.message;
                
                if (data.success) {
                    this.reset();
                }
            })
            .catch(error => {
                document.getElementById('message').textContent = 'An error occurred';
            });
        });
    </script>
</body>
</html>


html
<!-- /public/edit_post.html -->
<!DOCTYPE html>
<html>
<head>
    <title>Edit Blog Post</title>
</head>
<body>
    <h1>Edit Blog Post</h1>
    
    <form id="editPostForm" method="POST" action="../handlers/update_post.php">
        <input type="hidden" id="postId" name="id">
        
        <div>
            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required>
        </div>
        
        <div>
            <label for="content">Content:</label>
            <textarea id="content" name="content" rows="10" cols="50" required></textarea>
        </div>
        
        <button type="submit">Update Post</button>
    </form>
    
    <div id="message"></div>
    
    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const postId = urlParams.get('id');
        
        if (postId) {
            fetch(`../handlers/get_post.php?id=${postId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('postId').value = data.data.id;
                    document.getElementById('title').value = data.data.title;
                    document.getElementById('content').value = data.data.content;
                } else {
                    document.getElementById('message').textContent = data.message;
                }
            });
        }
        
        document.getElementById('editPostForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../handlers/update_post.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('message').textContent = data.message;
            })
            .catch(error => {
                document.getElementById('message').textContent = 'An error occurred';
            });
        });
    </script>
</body>
</html>


html
<!-- /public/view_posts.html -->
<!DOCTYPE html>
<html>
<head>
    <title>Blog Posts</title>
</head>
<body>
    <h1>Blog Posts</h1>
    
    <a href="create_post.html">Create New Post</a>
    
    <div id="posts"></div>
    
    <script>
        function loadPosts() {
            fetch('../handlers/get_posts.php')
            .then(response => response.json())
            .then(data => {
                const postsDiv = document.getElementById('posts');
                
                if (data.success) {
                    if (data.data.length === 0) {
                        postsDiv.innerHTML = '<p>No posts found</p>';
                    } else {
                        postsDiv.innerHTML = data.data.map(post => `
                            <div>
                                <h3>${post.title}</h3>
                                <p>${post.content}</p>
                                <small>Created: ${post.created_at}</small>
                                <div>
                                    <a href="edit_post.html?id=${post.id}">Edit</a>
                                    <button onclick="deletePost(${post.id})">Delete</button>
                                </div>
                            </div>
                        `).join('');
                    }
                } else {
                    postsDiv.innerHTML = `<p>Error: ${data.message}</p>`;
                }
            })
            .catch(error => {
                document.getElementById('posts').innerHTML = '<p>An error occurred loading posts</p>';
            });
        }
        
        function deletePost(id) {
            if (confirm('Are you sure you want to delete this post?')) {
                const formData = new FormData();
                formData.append('id', id);
                
                fetch('../handlers/delete_post.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        loadPosts();
                    }
                })
                .catch(error => {
                    alert('An error occurred');
                });
            }
        }
        
        loadPosts();
    </script>
</body>
</html>
?>