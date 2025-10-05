<?php
// public/index.php

echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Management</title>
</head>
<body>
    <h1>Blog Post Management</h1>

    <h2>Create New Post</h2>
    <form id="createPostForm" method="POST">
        <label for="createTitle">Title:</label><br>
        <input type="text" id="createTitle" name="title" required><br><br>
        <label for="createContent">Content:</label><br>
        <textarea id="createContent" name="content" rows="5" required></textarea><br><br>
        <button type="submit">Create Post</button>
    </form>
    <div id="createResult"></div>

    <h2>Update Post</h2>
    <form id="updatePostForm" method="POST">
        <label for="updateId">Post ID:</label><br>
        <input type="number" id="updateId" name="id" required><br><br>
        <label for="updateTitle">Title:</label><br>
        <input type="text" id="updateTitle" name="title" required><br><br>
        <label for="updateContent">Content:</label><br>
        <textarea id="updateContent" name="content" rows="5" required></textarea><br><br>
        <button type="submit">Update Post</button>
    </form>
    <div id="updateResult"></div>

    <h2>Delete Post</h2>
    <form id="deletePostForm" method="POST">
        <label for="deleteId">Post ID:</label><br>
        <input type="number" id="deleteId" name="id" required><br><br>
        <button type="submit">Delete Post</button>
    </form>
    <div id="deleteResult"></div>

    <h2>All Blog Posts</h2>
    <button id="refreshPosts">Refresh Posts</button>
    <div id="postsList"></div>

    <script>
    const handlerUrl = '../handlers/blog_post_handler.php';

    async function fetchPosts() {
        const postsListDiv = document.getElementById('postsList');
        postsListDiv.innerHTML = 'Loading posts...';
        try {
            const formData = new FormData();
            formData.append('action', 'read');
            const response = await fetch(handlerUrl, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                if (result.data && result.data.length > 0) {
                    let html = '<ul>';
                    result.data.forEach(post => {
                        html += `<li><strong>ID:</strong> \${post.id}<br><strong>Title:</strong> \${post.title}<br><strong>Content:</strong> \${post.content}<br><strong>Created At:</strong> \${post.created_at}</li><br>`;
                    });
                    html += '</ul>';
                    postsListDiv.innerHTML = html;
                } else {
                    postsListDiv.innerHTML = '<p>No blog posts found.</p>';
                }
            } else {
                postsListDiv.innerHTML = `<p style="color: red;">Error: \${result.message}</p>`;
            }
        } catch (error) {
            postsListDiv.innerHTML = `<p style="color: red;">Network error: \${error.message}</p>`;
        }
    }

    document.getElementById('createPostForm').addEventListener('submit', async function(event) {
        event.preventDefault();
        const resultDiv = document.getElementById('createResult');
        resultDiv.innerHTML = '';

        const formData = new FormData(this);
        formData.append('action', 'create');

        try {
            const response = await fetch(handlerUrl, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            resultDiv.innerHTML = `<p style="color: \${result.success ? 'green' : 'red'};">\${result.message}</p>`;
            if (result.success) {
                this.reset();
                fetchPosts();
            }
        } catch (error) {
            resultDiv.innerHTML = `<p style="color: red;">Network error: \${error.message}</p>`;
        }
    });

    document.getElementById('updatePostForm').addEventListener('submit', async function(event) {
        event.preventDefault();
        const resultDiv = document.getElementById('updateResult');
        resultDiv.innerHTML = '';

        const formData = new FormData(this);
        formData.append('action', 'update');

        try {
            const response = await fetch(handlerUrl, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            resultDiv.innerHTML = `<p style="color: \${result.success ? 'green' : 'red'};">\${result.message}</p>`;
            if (result.success) {
                this.reset();
                fetchPosts();
            }
        } catch (error) {
            resultDiv.innerHTML = `<p style="color: red;">Network error: \${error.message}</p>`;
        }
    });

    document.getElementById('deletePostForm').addEventListener('submit', async function(event) {
        event.preventDefault();
        const resultDiv = document.getElementById('deleteResult');
        resultDiv.innerHTML = '';

        const formData = new FormData(this);
        formData.append('action', 'delete');

        try {
            const response = await fetch(handlerUrl, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            resultDiv.innerHTML = `<p style="color: \${result.success ? 'green' : 'red'};">\${result.message}</p>`;
            if (result.success) {
                this.reset();
                fetchPosts();
            }
        } catch (error) {
            resultDiv.innerHTML = `<p style="color: red;">Network error: \${error.message}</p>`;
        }
    });

    document.getElementById('refreshPosts').addEventListener('click', fetchPosts);

    fetchPosts();

    </script>
</body>
</html>
HTML;
?>

<?php
// handlers/blog_post_handler.php

header('Content-Type: application/json');

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', '');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if (!isset($_POST['action'])) {
        $response['message'] = 'No action specified.';
        echo json_encode($response);
        exit;
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'create':
            if (empty($_POST['title']) || empty($_POST['content'])) {
                $response['message'] = 'Title and content are required for creating a post.';
                break;
            }
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);

            $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
            $stmt->execute([':title' => $title, ':content' => $content]);
            $response = ['success' => true, 'message' => 'Blog post created successfully.', 'id' => $pdo->lastInsertId()];
            break;

        case 'read':
            $stmt = $pdo->query("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $posts = $stmt->fetchAll();
            $response = ['success' => true, 'message' => 'Blog posts retrieved successfully.', 'data' => $posts];
            break;

        case 'update':
            if (empty($_POST['id']) || empty($_POST['title']) || empty($_POST['content'])) {
                $response['message'] = 'ID, title, and content are required for updating a post.';
                break;
            }
            $id = (int)$_POST['id'];
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);

            $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
            $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);

            if ($stmt->rowCount()) {
                $response = ['success' => true, 'message' => 'Blog post updated successfully.'];
            } else {
                $response['message'] = 'Blog post not found or no changes made.';
            }
            break;

        case 'delete':
            if (empty($_POST['id'])) {
                $response['message'] = 'ID is required for deleting a post.';
                break;
            }
            $id = (int)$_POST['id'];

            $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount()) {
                $response = ['success' => true, 'message' => 'Blog post deleted successfully.'];
            } else {
                $response['message'] = 'Blog post not found.';
            }
            break;

        default:
            $response['message'] = 'Invalid action specified.';
            break;
    }

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Application error: ' . $e->getMessage();
}

echo json_encode($response);
exit;
?>