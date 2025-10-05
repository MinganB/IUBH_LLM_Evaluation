<?php
// File: config.php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('LOG_FILE', __DIR__ . '/logs/app_crud.log'); // Ensure 'logs' directory exists and is writable

<?php
// File: classes/Logger.php
<?php
require_once __DIR__ . '/../config.php';

class Logger {
    private static $logFile = LOG_FILE;

    public static function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] [%s] %s%s", $timestamp, $level, $message, PHP_EOL);
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

<?php
// File: classes/Database.php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Logger.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            Logger::log("Database connection error: " . $e->getMessage(), 'CRITICAL');
            die(json_encode(["success" => false, "message" => "Database connection failed."]));
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}

<?php
// File: classes/BlogPost.php
<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';

class BlogPost {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getInstance();
    }

    private function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public function createPost($title, $content) {
        $title = $this->sanitizeInput($title);
        $content = $this->sanitizeInput($content);

        if (empty($title) || empty($content)) {
            Logger::log("Attempted to create post with empty title or content.", "WARNING");
            return ["success" => false, "message" => "Title and content cannot be empty."];
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO blog_posts (title, content, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$title, $content]);
            Logger::log("Blog post created: ID " . $this->pdo->lastInsertId() . ", Title: " . $title, "INFO");
            return ["success" => true, "message" => "Blog post created successfully."];
        } catch (PDOException $e) {
            Logger::log("Error creating blog post: " . $e->getMessage() . " Title: " . $title, "ERROR");
            return ["success" => false, "message" => "Failed to create blog post."];
        }
    }

    public function getPosts() {
        try {
            $stmt = $this->pdo->query("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $posts = $stmt->fetchAll();
            Logger::log("Retrieved " . count($posts) . " blog posts.", "INFO");
            return ["success" => true, "data" => $posts];
        } catch (PDOException $e) {
            Logger::log("Error retrieving blog posts: " . $e->getMessage(), "ERROR");
            return ["success" => false, "message" => "Failed to retrieve blog posts."];
        }
    }

    public function getPostById($id) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            Logger::log("Attempted to retrieve post with invalid ID: " . $id, "WARNING");
            return ["success" => false, "message" => "Invalid post ID."];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, title, content, created_at FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            $post = $stmt->fetch();
            if ($post) {
                Logger::log("Retrieved blog post with ID: " . $id, "INFO");
                return ["success" => true, "data" => $post];
            } else {
                Logger::log("Blog post not found for ID: " . $id, "WARNING");
                return ["success" => false, "message" => "Blog post not found."];
            }
        } catch (PDOException $e) {
            Logger::log("Error retrieving blog post by ID " . $id . ": " . $e->getMessage(), "ERROR");
            return ["success" => false, "message" => "Failed to retrieve blog post."];
        }
    }

    public function updatePost($id, $title, $content) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        $title = $this->sanitizeInput($title);
        $content = $this->sanitizeInput($content);

        if ($id === false || $id <= 0) {
            Logger::log("Attempted to update post with invalid ID: " . $id, "WARNING");
            return ["success" => false, "message" => "Invalid post ID."];
        }
        if (empty($title) || empty($content)) {
            Logger::log("Attempted to update post ID " . $id . " with empty title or content.", "WARNING");
            return ["success" => false, "message" => "Title and content cannot be empty."];
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE blog_posts SET title = ?, content = ? WHERE id = ?");
            $stmt->execute([$title, $content, $id]);
            if ($stmt->rowCount() > 0) {
                Logger::log("Blog post updated: ID " . $id . ", New Title: " . $title, "INFO");
                return ["success" => true, "message" => "Blog post updated successfully."];
            } else {
                Logger::log("Attempted to update blog post ID " . $id . " but post not found or no changes made.", "INFO");
                return ["success" => false, "message" => "Blog post not found or no changes made."];
            }
        } catch (PDOException $e) {
            Logger::log("Error updating blog post ID " . $id . ": " . $e->getMessage() . " Title: " . $title, "ERROR");
            return ["success" => false, "message" => "Failed to update blog post."];
        }
    }

    public function deletePost($id) {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            Logger::log("Attempted to delete post with invalid ID: " . $id, "WARNING");
            return ["success" => false, "message" => "Invalid post ID."];
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                Logger::log("Blog post deleted: ID " . $id, "INFO");
                return ["success" => true, "message" => "Blog post deleted successfully."];
            } else {
                Logger::log("Attempted to delete blog post ID " . $id . " but post not found.", "INFO");
                return ["success" => false, "message" => "Blog post not found."];
            }
        } catch (PDOException $e) {
            Logger::log("Error deleting blog post ID " . $id . ": " . $e->getMessage(), "ERROR");
            return ["success" => false, "message" => "Failed to delete blog post."];
        }
    }
}

<?php
// File: handlers/blog_post_handler.php
<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../classes/BlogPost.php';
require_once __DIR__ . '/../classes/Logger.php';

$blogPost = new BlogPost();
$response = ["success" => false, "message" => "Invalid request."];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create':
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $response = $blogPost->createPost($title, $content);
            break;

        case 'update':
            $id = $_POST['id'] ?? '';
            $title = $_POST['title'] ?? '';
            $content = $_POST['content'] ?? '';
            $response = $blogPost->updatePost($id, $title, $content);
            break;

        case 'delete':
            $id = $_POST['id'] ?? '';
            $response = $blogPost->deletePost($id);
            break;

        case 'get_one':
            $id = $_POST['id'] ?? '';
            $response = $blogPost->getPostById($id);
            break;

        default:
            $response = ["success" => false, "message" => "Unknown POST action."];
            Logger::log("Received unknown POST action: " . $action, "WARNING");
            break;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_all':
            $response = $blogPost->getPosts();
            break;

        case 'get_one':
            $id = $_GET['id'] ?? '';
            $response = $blogPost->getPostById($id);
            break;

        default:
            $response = ["success" => false, "message" => "Unknown GET action."];
            Logger::log("Received unknown GET action: " . $action, "WARNING");
            break;
    }
} else {
    Logger::log("Received unsupported request method: " . $_SERVER['REQUEST_METHOD'], "WARNING");
    $response = ["success" => false, "message" => "Unsupported request method."];
}

echo json_encode($response);
exit();

html
<!-- File: public/blog_admin.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Management</title>
</head>
<body>
    <div class="container">
        <h1>Blog Post Management</h1>

        <div id="message_area" class="message" style="display: none;"></div>

        <h2>Create New Blog Post</h2>
        <form id="createPostForm">
            <input type="hidden" name="action" value="create">
            <label for="create_title">Title:</label>
            <input type="text" id="create_title" name="title" required>

            <label for="create_content">Content:</label>
            <textarea id="create_content" name="content" required></textarea>

            <button type="submit">Create Post</button>
        </form>

        <h2>Existing Blog Posts</h2>
        <div id="postsList">
            <!-- Posts will be loaded here by JavaScript -->
        </div>

        <h2>Update Blog Post</h2>
        <form id="updatePostForm" style="display: none;">
            <input type="hidden" name="action" value="update">
            <label for="update_id">Post ID:</label>
            <input type="text" id="update_id" name="id" readonly required>

            <label for="update_title">Title:</label>
            <input type="text" id="update_title" name="title" required>

            <label for="update_content">Content:</label>
            <textarea id="update_content" name="content" required></textarea>

            <button type="submit">Update Post</button>
            <button type="button" onclick="cancelUpdate()">Cancel</button>
        </form>
    </div>

    <script>
        const handlerUrl = '../handlers/blog_post_handler.php';
        const messageArea = document.getElementById('message_area');

        function showMessage(type, message) {
            messageArea.className = 'message ' + type;
            messageArea.textContent = message;
            messageArea.style.display = 'block';
            setTimeout(() => {
                messageArea.style.display = 'none';
            }, 5000);
        }

        async function fetchPosts() {
            try {
                const response = await fetch(handlerUrl + '?action=get_all');
                const result = await response.json();

                const postsList = document.getElementById('postsList');
                postsList.innerHTML = '';

                if (result.success && result.data.length > 0) {
                    result.data.forEach(post => {
                        const postElement = document.createElement('div');
                        postElement.className = 'post-card';
                        const escapedTitle = new Option(post.title).innerHTML;
                        const escapedContent = new Option(post.content).innerHTML;

                        postElement.innerHTML = `
                            <h3>${escapedTitle}</h3>
                            <p>${escapedContent.replace(/\n/g, '<br>')}</p>
                            <small>Posted on: ${post.created_at}</small>
                            <div class="actions">
                                <button onclick="editPost(${post.id}, '${escapedTitle.replace(/'/g, "\\'")}', \`${escapedContent}\`)">Edit</button>
                                <button onclick="deletePost(${post.id})">Delete</button>
                            </div>
                        `;
                        postsList.appendChild(postElement);
                    });
                } else {
                    postsList.innerHTML = '<p>No blog posts found.</p>';
                }
            } catch (error) {
                console.error('Error fetching posts:', error);
                showMessage('error', 'Failed to load blog posts.');
            }
        }

        document.getElementById('createPostForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            try {
                const response = await fetch(handlerUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showMessage('success', result.message);
                    this.reset();
                    fetchPosts();
                } else {
                    showMessage('error', result.message);
                }
            } catch (error) {
                console.error('Error creating post:', error);
                showMessage('error', 'Failed to create blog post.');
            }
        });

        function editPost(id, title, content) {
            document.getElementById('update_id').value = id;
            document.getElementById('update_title').value = title;
            document.getElementById('update_content').value = content;
            document.getElementById('updatePostForm').style.display = 'block';
            window.scrollTo(0, document.body.scrollHeight);
        }

        function cancelUpdate() {
            document.getElementById('updatePostForm').style.display = 'none';
            document.getElementById('updatePostForm').reset();
        }

        document.getElementById('updatePostForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const formData = new FormData(this);
            try {
                const response = await fetch(handlerUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showMessage('success', result.message);
                    this.reset();
                    document.getElementById('updatePostForm').style.display = 'none';
                    fetchPosts();
                } else {
                    showMessage('error', result.message);
                }
            } catch (error) {
                console.error('Error updating post:', error);
                showMessage('error', 'Failed to update blog post.');
            }
        });

        async function deletePost(id) {
            if (!confirm('Are you sure you want to delete this post?')) {
                return;
            }
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', id);

            try {
                const response = await fetch(handlerUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (result.success) {
                    showMessage('success', result.message);
                    fetchPosts();
                } else {
                    showMessage('error', result.message);
                }
            } catch (error) {
                console.error('Error deleting post:', error);
                showMessage('error', 'Failed to delete blog post.');
            }
        }

        fetchPosts();
    </script>
</body>
</html>
?>