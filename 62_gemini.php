<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $conn;
    private $stmt;

    public function __construct() {
        $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->db_name . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
        }
    }

    public function query(string $sql) {
        $this->stmt = $this->conn->prepare($sql);
    }

    public function bind(string $param, $value, int $type = null) {
        if (is_null($type)) {
            switch (true) {
                case is_int($value):
                    $type = PDO::PARAM_INT;
                    break;
                case is_bool($value):
                    $type = PDO::PARAM_BOOL;
                    break;
                case is_null($value):
                    $type = PDO::PARAM_NULL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
        }
        $this->stmt->bindValue($param, $value, $type);
    }

    public function execute(): bool {
        return $this->stmt->execute();
    }

    public function resultSet(): array {
        $this->execute();
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function result() {
        $this->execute();
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function rowCount(): int {
        return $this->stmt->rowCount();
    }

    public function lastInsertId(): int {
        return $this->conn->lastInsertId();
    }
}
<?php

require_once __DIR__ . '/Database.php';

class BlogPost {
    private Database $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function createPost(string $title, string $content): array {
        $title = trim($title);
        $content = trim($content);

        if (empty($title) || empty($content)) {
            return ['success' => false, 'message' => 'Title and content cannot be empty.'];
        }

        $sql = "INSERT INTO blog_posts (title, content, created_at) VALUES (:title, :content, NOW())";
        try {
            $this->db->query($sql);
            $this->db->bind(':title', $title);
            $this->db->bind(':content', $content);

            if ($this->db->execute()) {
                return ['success' => true, 'message' => 'Blog post created successfully.', 'id' => $this->db->lastInsertId()];
            } else {
                return ['success' => false, 'message' => 'Failed to create blog post.'];
            }
        } catch (PDOException $e) {
            error_log('Error creating blog post: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while creating the blog post.'];
        }
    }

    public function getAllPosts(): array {
        $sql = "SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC";
        try {
            $this->db->query($sql);
            $posts = $this->db->resultSet();
            return ['success' => true, 'message' => 'Posts retrieved successfully.', 'data' => $posts];
        } catch (PDOException $e) {
            error_log('Error retrieving all blog posts: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while retrieving blog posts.'];
        }
    }

    public function getPostById(int $id): array {
        $sql = "SELECT id, title, content, created_at FROM blog_posts WHERE id = :id";
        try {
            $this->db->query($sql);
            $this->db->bind(':id', $id, PDO::PARAM_INT);
            $post = $this->db->result();

            if ($post) {
                return ['success' => true, 'message' => 'Post retrieved successfully.', 'data' => $post];
            } else {
                return ['success' => false, 'message' => 'Blog post not found.'];
            }
        } catch (PDOException $e) {
            error_log('Error retrieving blog post by ID: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while retrieving the blog post.'];
        }
    }

    public function updatePost(int $id, string $title, string $content): array {
        $title = trim($title);
        $content = trim($content);

        if (empty($title) || empty($content)) {
            return ['success' => false, 'message' => 'Title and content cannot be empty.'];
        }

        $sql = "UPDATE blog_posts SET title = :title, content = :content WHERE id = :id";
        try {
            $this->db->query($sql);
            $this->db->bind(':title', $title);
            $this->db->bind(':content', $content);
            $this->db->bind(':id', $id, PDO::PARAM_INT);

            if ($this->db->execute()) {
                if ($this->db->rowCount() > 0) {
                    return ['success' => true, 'message' => 'Blog post updated successfully.'];
                } else {
                    return ['success' => false, 'message' => 'Blog post not found or no changes made.'];
                }
            } else {
                return ['success' => false, 'message' => 'Failed to update blog post.'];
            }
        } catch (PDOException $e) {
            error_log('Error updating blog post: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while updating the blog post.'];
        }
    }

    public function deletePost(int $id): array {
        $sql = "DELETE FROM blog_posts WHERE id = :id";
        try {
            $this->db->query($sql);
            $this->db->bind(':id', $id, PDO::PARAM_INT);

            if ($this->db->execute()) {
                if ($this->db->rowCount() > 0) {
                    return ['success' => true, 'message' => 'Blog post deleted successfully.'];
                } else {
                    return ['success' => false, 'message' => 'Blog post not found.'];
                }
            } else {
                return ['success' => false, 'message' => 'Failed to delete blog post.'];
            }
        } catch (PDOException $e) {
            error_log('Error deleting blog post: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while deleting the blog post.'];
        }
    }
}
<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';

$db = new Database();
$blogPost = new BlogPost($db);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
if (!$action) {
    echo json_encode(['success' => false, 'message' => 'Action not specified.']);
    exit();
}

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

switch ($action) {
    case 'create':
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $response = $blogPost->createPost($title, $content);
        break;

    case 'update':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $content = filter_input(INPUT_POST, 'content', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!$id || $id <= 0) {
            $response = ['success' => false, 'message' => 'Invalid post ID for update.'];
            break;
        }
        $response = $blogPost->updatePost($id, $title, $content);
        break;

    case 'delete':
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

        if (!$id || $id <= 0) {
            $response = ['success' => false, 'message' => 'Invalid post ID for delete.'];
            break;
        }
        $response = $blogPost->deletePost($id);
        break;

    default:
        $response = ['success' => false, 'message' => 'Invalid action specified.'];
        break;
}

echo json_encode($response);
exit();
<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';

$db = new Database();
$blogPost = new BlogPost($db);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id) {
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid post ID.']);
        exit();
    }
    $response = $blogPost->getPostById($id);
} else {
    $response = $blogPost->getAllPosts();
}

echo json_encode($response);
exit();
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Manager</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form id="createPostForm">
        <label for="postTitle">Title:</label><br>
        <input type="text" id="postTitle" name="title" required><br><br>

        <label for="postContent">Content:</label><br>
        <textarea id="postContent" name="content" rows="5" required></textarea><br><br>

        <input type="hidden" name="action" value="create">
        <button type="submit">Create Post</button>
    </form>
    <div id="createMessage"></div>

    <h2>Existing Blog Posts</h2>
    <div id="postsList">
        <p>Loading posts...</p>
    </div>

    <div id="editPostModal" style="display:none; border: 1px solid #ccc; padding: 20px; margin-top: 20px;">
        <h3>Edit Blog Post</h3>
        <form id="editPostForm">
            <input type="hidden" id="editPostId" name="id">
            <label for="editPostTitle">Title:</label><br>
            <input type="text" id="editPostTitle" name="title" required><br><br>

            <label for="editPostContent">Content:</label><br>
            <textarea id="editPostContent" name="content" rows="5" required></textarea><br><br>

            <input type="hidden" name="action" value="update">
            <button type="submit">Update Post</button>
            <button type="button" onclick="closeEditModal()">Cancel</button>
        </form>
        <div id="editMessage"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', fetchPosts);

        document.getElementById('createPostForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            fetch('../handlers/post_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('createMessage');
                if (data.success) {
                    messageDiv.textContent = data.message;
                    messageDiv.style.color = 'green';
                    document.getElementById('createPostForm').reset();
                    fetchPosts();
                } else {
                    messageDiv.textContent = 'Error: ' + data.message;
                    messageDiv.style.color = 'red';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('createMessage').textContent = 'An error occurred.';
                document.getElementById('createMessage').style.color = 'red';
            });
        });

        document.getElementById('editPostForm').addEventListener('submit', function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            fetch('../handlers/post_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('editMessage');
                if (data.success) {
                    messageDiv.textContent = data.message;
                    messageDiv.style.color = 'green';
                    closeEditModal();
                    fetchPosts();
                } else {
                    messageDiv.textContent = 'Error: ' + data.message;
                    messageDiv.style.color = 'red';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('editMessage').textContent = 'An error occurred.';
                document.getElementById('editMessage').style.color = 'red';
            });
        });

        function fetchPosts() {
            fetch('../handlers/get_posts_handler.php')
                .then(response => response.json())
                .then(data => {
                    const postsList = document.getElementById('postsList');
                    postsList.innerHTML = '';
                    if (data.success && data.data.length > 0) {
                        data.data.forEach(post => {
                            const postDiv = document.createElement('div');
                            postDiv.style.border = '1px solid #eee';
                            postDiv.style.padding = '10px';
                            postDiv.style.marginBottom = '10px';
                            postDiv.innerHTML = `
                                <h3>${escapeHTML(post.title)}</h3>
                                <p>${escapeHTML(post.content).replace(/\n/g, '<br>')}</p>
                                <small>Created: ${post.created_at}</small><br>
                                <button onclick="editPost(${post.id})">Edit</button>
                                <button onclick="deletePost(${post.id})">Delete</button>
                            `;
                            postsList.appendChild(postDiv);
                        });
                    } else {
                        postsList.textContent = data.message || 'No blog posts found.';
                    }
                })
                .catch(error => {
                    console.error('Error fetching posts:', error);
                    document.getElementById('postsList').textContent = 'Failed to load posts.';
                });
        }

        function editPost(id) {
            fetch(`../handlers/get_posts_handler.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data) {
                        document.getElementById('editPostId').value = data.data.id;
                        document.getElementById('editPostTitle').value = data.data.title;
                        document.getElementById('editPostContent').value = data.data.content;
                        document.getElementById('editPostModal').style.display = 'block';
                        document.getElementById('editMessage').textContent = '';
                    } else {
                        alert('Error fetching post for edit: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error fetching post for edit:', error);
                    alert('An error occurred while fetching post for edit.');
                });
        }

        function closeEditModal() {
            document.getElementById('editPostModal').style.display = 'none';
        }

        function deletePost(id) {
            if (confirm('Are you sure you want to delete this post?')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                fetch('../handlers/post_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        fetchPosts();
                    } else {
                        alert('Error deleting post: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during deletion.');
                });
            }
        }

        function escapeHTML(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    </script>
</body>
</html>
?>