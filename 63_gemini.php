<?php
// config.php
// This file contains sensitive information and should be secured.
// In a real production environment, environment variables or a more secure configuration
// management system should be used instead of hardcoding values.

// Database Credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user'); // Replace with your actual database user
define('DB_PASS', 'your_db_password'); // Replace with your actual database password

// Log File Path
// Ensure this path is outside the web-accessible directory in a production environment.
// For this example, it's placed in a 'logs' subdirectory within the module's root.
define('LOG_FILE_PATH', __DIR__ . '/logs/app.log');

// Set PHP error reporting for production
ini_set('display_errors', 'Off');
error_reporting(0);

// Create logs directory if it doesn't exist
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}
?>
<?php
// classes/Logger.php

class Logger
{
    public static function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message" . PHP_EOL;

        // Attempt to write to the log file
        // Use FILE_APPEND to add to the end of the file
        // Use LOCK_EX to prevent anyone else from writing to the file at the same time
        if (file_put_contents(LOG_FILE_PATH, $logMessage, FILE_APPEND | LOCK_EX) === false) {
            // Fallback for logging if file write fails (e.g., permissions)
            // In a production environment, this might trigger an alert or write to syslog
            error_log("Failed to write to log file: " . LOG_FILE_PATH . " - Original message: " . $message);
        }
    }
}
?>
<?php
// classes/Database.php

class Database
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
        // Private constructor to prevent direct instantiation
    }

    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                Logger::log("Database Connection Error: " . $e->getMessage());
                // In a production environment, avoid exposing detailed error messages.
                // Return a generic message or re-throw a custom exception.
                throw new Exception("Could not connect to the database. Please try again later.");
            }
        }
        return self::$pdo;
    }
}
?>
<?php
// classes/BlogPost.php

class BlogPost
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    private function sanitize(string $data): string
    {
        // Trim whitespace and escape HTML special characters
        return htmlspecialchars(trim($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function validateInput(string $title, string $content): array
    {
        $errors = [];
        if (empty($title)) {
            $errors[] = "Title cannot be empty.";
        }
        if (strlen($title) > 255) {
            $errors[] = "Title cannot exceed 255 characters.";
        }
        if (empty($content)) {
            $errors[] = "Content cannot be empty.";
        }
        return $errors;
    }

    public function createPost(string $title, string $content): array
    {
        $sanitizedTitle = $this->sanitize($title);
        $sanitizedContent = $this->sanitize($content);

        $errors = $this->validateInput($sanitizedTitle, $sanitizedContent);
        if (!empty($errors)) {
            Logger::log("Create Post Validation Failed: " . implode(", ", $errors));
            return ['success' => false, 'message' => implode(" ", $errors)];
        }

        $sql = "INSERT INTO blog_posts (title, content) VALUES (?, ?)";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sanitizedTitle, $sanitizedContent]);
            $postId = $this->db->lastInsertId();
            Logger::log("Blog post created successfully: ID $postId, Title: $sanitizedTitle");
            return ['success' => true, 'message' => "Blog post created successfully.", 'id' => $postId];
        } catch (PDOException $e) {
            Logger::log("Error creating blog post: " . $e->getMessage());
            return ['success' => false, 'message' => "An error occurred while creating the blog post."];
        }
    }

    public function getPost(int $id): array
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            Logger::log("Get Post: Invalid ID provided ($id).");
            return ['success' => false, 'message' => "Invalid post ID."];
        }

        $sql = "SELECT id, title, content, created_at FROM blog_posts WHERE id = ?";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $post = $stmt->fetch();

            if ($post) {
                Logger::log("Blog post retrieved successfully: ID $id");
                // Escape for display before returning
                $post['title'] = $this->sanitize($post['title']);
                $post['content'] = $this->sanitize($post['content']);
                return ['success' => true, 'message' => "Post retrieved.", 'post' => $post];
            } else {
                Logger::log("Blog post not found: ID $id");
                return ['success' => false, 'message' => "Blog post not found."];
            }
        } catch (PDOException $e) {
            Logger::log("Error retrieving blog post (ID $id): " . $e->getMessage());
            return ['success' => false, 'message' => "An error occurred while retrieving the blog post."];
        }
    }

    public function getAllPosts(): array
    {
        $sql = "SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC";
        try {
            $stmt = $this->db->query($sql);
            $posts = $stmt->fetchAll();

            // Escape for display before returning
            foreach ($posts as &$post) {
                $post['title'] = $this->sanitize($post['title']);
                $post['content'] = $this->sanitize($post['content']);
            }
            unset($post); // Unset the reference

            Logger::log("All blog posts retrieved successfully.");
            return ['success' => true, 'message' => "Posts retrieved.", 'posts' => $posts];
        } catch (PDOException $e) {
            Logger::log("Error retrieving all blog posts: " . $e->getMessage());
            return ['success' => false, 'message' => "An error occurred while retrieving blog posts."];
        }
    }

    public function updatePost(int $id, string $title, string $content): array
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            Logger::log("Update Post: Invalid ID provided ($id).");
            return ['success' => false, 'message' => "Invalid post ID."];
        }

        $sanitizedTitle = $this->sanitize($title);
        $sanitizedContent = $this->sanitize($content);

        $errors = $this->validateInput($sanitizedTitle, $sanitizedContent);
        if (!empty($errors)) {
            Logger::log("Update Post Validation Failed for ID $id: " . implode(", ", $errors));
            return ['success' => false, 'message' => implode(" ", $errors)];
        }

        $sql = "UPDATE blog_posts SET title = ?, content = ? WHERE id = ?";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sanitizedTitle, $sanitizedContent, $id]);

            if ($stmt->rowCount() > 0) {
                Logger::log("Blog post updated successfully: ID $id");
                return ['success' => true, 'message' => "Blog post updated successfully."];
            } else {
                Logger::log("Blog post not found or no changes made: ID $id");
                return ['success' => false, 'message' => "Blog post not found or no changes were made."];
            }
        } catch (PDOException $e) {
            Logger::log("Error updating blog post (ID $id): " . $e->getMessage());
            return ['success' => false, 'message' => "An error occurred while updating the blog post."];
        }
    }

    public function deletePost(int $id): array
    {
        if (!filter_var($id, FILTER_VALIDATE_INT)) {
            Logger::log("Delete Post: Invalid ID provided ($id).");
            return ['success' => false, 'message' => "Invalid post ID."];
        }

        $sql = "DELETE FROM blog_posts WHERE id = ?";
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            if ($stmt->rowCount() > 0) {
                Logger::log("Blog post deleted successfully: ID $id");
                return ['success' => true, 'message' => "Blog post deleted successfully."];
            } else {
                Logger::log("Blog post not found for deletion: ID $id");
                return ['success' => false, 'message' => "Blog post not found."];
            }
        } catch (PDOException $e) {
            Logger::log("Error deleting blog post (ID $id): " . $e->getMessage());
            return ['success' => false, 'message' => "An error occurred while deleting the blog post."];
        }
    }
}
?>
<?php
// handlers/create_post.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getConnection();
        $blogPost = new BlogPost($db);

        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';

        $response = $blogPost->createPost($title, $content);
        echo json_encode($response);
    } catch (Exception $e) {
        Logger::log("Handler Error (create_post.php): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => "An unexpected error occurred."]);
    }
} else {
    Logger::log("Invalid request method for create_post.php: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => "Invalid request method."]);
}
?>
<?php
// handlers/read_posts.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $db = Database::getConnection();
        $blogPost = new BlogPost($db);

        if (isset($_GET['id']) && $_GET['id'] !== '') {
            $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
            if ($id === false) {
                Logger::log("Read Post: Invalid ID provided for single post retrieval: " . ($_GET['id'] ?? 'N/A'));
                echo json_encode(['success' => false, 'message' => "Invalid post ID format."]);
            } else {
                $response = $blogPost->getPost($id);
                echo json_encode($response);
            }
        } else {
            $response = $blogPost->getAllPosts();
            echo json_encode($response);
        }
    } catch (Exception $e) {
        Logger::log("Handler Error (read_posts.php): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => "An unexpected error occurred."]);
    }
} else {
    Logger::log("Invalid request method for read_posts.php: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => "Invalid request method."]);
}
?>
<?php
// handlers/update_post.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getConnection();
        $blogPost = new BlogPost($db);

        $id = $_POST['id'] ?? null;
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';

        $id_int = filter_var($id, FILTER_VALIDATE_INT);

        if ($id_int === false) {
            Logger::log("Update Post: Invalid ID provided in POST data: " . ($id ?? 'N/A'));
            echo json_encode(['success' => false, 'message' => "Invalid post ID format."]);
        } else {
            $response = $blogPost->updatePost($id_int, $title, $content);
            echo json_encode($response);
        }
    } catch (Exception $e) {
        Logger::log("Handler Error (update_post.php): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => "An unexpected error occurred."]);
    }
} else {
    Logger::log("Invalid request method for update_post.php: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => "Invalid request method."]);
}
?>
<?php
// handlers/delete_post.php

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/BlogPost.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getConnection();
        $blogPost = new BlogPost($db);

        $id = $_POST['id'] ?? null;

        $id_int = filter_var($id, FILTER_VALIDATE_INT);

        if ($id_int === false) {
            Logger::log("Delete Post: Invalid ID provided in POST data: " . ($id ?? 'N/A'));
            echo json_encode(['success' => false, 'message' => "Invalid post ID format."]);
        } else {
            $response = $blogPost->deletePost($id_int);
            echo json_encode($response);
        }
    } catch (Exception $e) {
        Logger::log("Handler Error (delete_post.php): " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => "An unexpected error occurred."]);
    }
} else {
    Logger::log("Invalid request method for delete_post.php: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => "Invalid request method."]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blog Posts</title>
</head>
<body>
    <h1>Manage Blog Posts</h1>

    <form id="blogPostForm">
        <input type="hidden" id="postId" name="id">
        <div>
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" required maxlength="255">
        </div>
        <div>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="10" required></textarea>
        </div>
        <div>
            <button type="submit" id="submitButton">Create Post</button>
            <button type="button" id="cancelEditButton" style="display:none;">Cancel Edit</button>
        </div>
    </form>

    <div id="messageArea" style="margin-top: 10px; padding: 10px; border: 1px solid transparent; display: none;"></div>

    <h2>Existing Blog Posts</h2>
    <div id="postsList">
        Loading posts...
    </div>

    <script>
        const blogPostForm = document.getElementById('blogPostForm');
        const postIdInput = document.getElementById('postId');
        const titleInput = document.getElementById('title');
        const contentInput = document.getElementById('content');
        const submitButton = document.getElementById('submitButton');
        const cancelEditButton = document.getElementById('cancelEditButton');
        const messageArea = document.getElementById('messageArea');
        const postsList = document.getElementById('postsList');

        function showMessage(message, isSuccess) {
            messageArea.textContent = message;
            messageArea.style.borderColor = isSuccess ? 'green' : 'red';
            messageArea.style.backgroundColor = isSuccess ? '#e6ffe6' : '#ffe6e6';
            messageArea.style.color = isSuccess ? 'green' : 'red';
            messageArea.style.display = 'block';
            setTimeout(() => {
                messageArea.style.display = 'none';
            }, 5000);
        }

        async function fetchPosts() {
            postsList.innerHTML = 'Loading posts...';
            try {
                const response = await fetch('handlers/read_posts.php');
                const result = await response.json();

                if (result.success) {
                    postsList.innerHTML = '';
                    if (result.posts && result.posts.length > 0) {
                        result.posts.forEach(post => {
                            const postDiv = document.createElement('div');
                            postDiv.style.border = '1px solid #ccc';
                            postDiv.style.padding = '10px';
                            postDiv.style.marginBottom = '10px';

                            const postTitle = document.createElement('h3');
                            postTitle.textContent = post.title;
                            postDiv.appendChild(postTitle);

                            const postContent = document.createElement('p');
                            postContent.textContent = post.content;
                            postDiv.appendChild(postContent);

                            const postDate = document.createElement('small');
                            postDate.textContent = `Posted on: ${new Date(post.created_at).toLocaleString()}`;
                            postDiv.appendChild(postDate);

                            const editButton = document.createElement('button');
                            editButton.textContent = 'Edit';
                            editButton.onclick = () => editPost(post.id, post.title, post.content);
                            editButton.style.marginLeft = '10px';
                            postDiv.appendChild(editButton);

                            const deleteButton = document.createElement('button');
                            deleteButton.textContent = 'Delete';
                            deleteButton.onclick = () => deletePost(post.id);
                            deleteButton.style.marginLeft = '5px';
                            postDiv.appendChild(deleteButton);

                            postsList.appendChild(postDiv);
                        });
                    } else {
                        postsList.innerHTML = '<p>No blog posts found.</p>';
                    }
                } else {
                    postsList.innerHTML = `<p>Error loading posts: ${result.message}</p>`;
                    showMessage(result.message, false);
                }
            } catch (error) {
                console.error('Error fetching posts:', error);
                postsList.innerHTML = '<p>An error occurred while fetching posts.</p>';
                showMessage('An unexpected error occurred while fetching posts.', false);
            }
        }

        function editPost(id, title, content) {
            postIdInput.value = id;
            titleInput.value = title;
            contentInput.value = content;
            submitButton.textContent = 'Update Post';
            cancelEditButton.style.display = 'inline-block';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        async function deletePost(id) {
            if (!confirm('Are you sure you want to delete this post?')) {
                return;
            }

            const formData = new FormData();
            formData.append('id', id);

            try {
                const response = await fetch('handlers/delete_post.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                showMessage(result.message, result.success);
                if (result.success) {
                    fetchPosts();
                    resetForm();
                }
            } catch (error) {
                console.error('Error deleting post:', error);
                showMessage('An unexpected error occurred while deleting the post.', false);
            }
        }

        function resetForm() {
            blogPostForm.reset();
            postIdInput.value = '';
            submitButton.textContent = 'Create Post';
            cancelEditButton.style.display = 'none';
        }

        blogPostForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const formData = new FormData(blogPostForm);
            const postId = formData.get('id');
            const handlerUrl = postId ? 'handlers/update_post.php' : 'handlers/create_post.php';

            try {
                const response = await fetch(handlerUrl, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                showMessage(result.message, result.success);
                if (result.success) {
                    fetchPosts();
                    resetForm();
                }
            } catch (error) {
                console.error('Error submitting post:', error);
                showMessage('An unexpected error occurred while submitting the post.', false);
            }
        });

        cancelEditButton.addEventListener('click', resetForm);

        // Initial fetch of posts when the page loads
        fetchPosts();
    </script>
</body>
</html>
?>