<?php
// handlers/blog_post_handler.php

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

try {
    $dsn = 'mysql:host=localhost;dbname=db_users;charset=utf8mb4';
    $username = 'root'; 
    $password = '';     

    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'create':
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';

                if (empty($title) || empty($content)) {
                    $response['message'] = 'Title and content cannot be empty.';
                    break;
                }

                $stmt = $pdo->prepare('INSERT INTO blog_posts (title, content) VALUES (:title, :content)');
                $stmt->execute([':title' => $title, ':content' => $content]);
                $response['success'] = true;
                $response['message'] = 'Blog post created successfully.';
                $response['id'] = $pdo->lastInsertId();
                break;

            case 'update':
                $id = $_POST['id'] ?? null;
                $title = $_POST['title'] ?? '';
                $content = $_POST['content'] ?? '';

                if (empty($id) || empty($title) || empty($content)) {
                    $response['message'] = 'ID, title, and content cannot be empty for update.';
                    break;
                }

                $stmt = $pdo->prepare('UPDATE blog_posts SET title = :title, content = :content WHERE id = :id');
                $stmt->execute([':title' => $title, ':content' => $content, ':id' => $id]);

                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Blog post updated successfully.';
                } else {
                    $response['message'] = 'Blog post not found or no changes made.';
                }
                break;

            case 'delete':
                $id = $_POST['id'] ?? null;

                if (empty($id)) {
                    $response['message'] = 'ID cannot be empty for delete.';
                    break;
                }

                $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = :id');
                $stmt->execute([':id' => $id]);

                if ($stmt->rowCount() > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Blog post deleted successfully.';
                } else {
                    $response['message'] = 'Blog post not found.';
                }
                break;

            default:
                $response['message'] = 'Invalid POST action specified.';
                break;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'read':
                $id = $_GET['id'] ?? null;

                if ($id !== null) {
                    $stmt = $pdo->prepare('SELECT id, title, content, created_at FROM blog_posts WHERE id = :id');
                    $stmt->execute([':id' => $id]);
                    $post = $stmt->fetch();
                    if ($post) {
                        $response['success'] = true;
                        $response['message'] = 'Blog post retrieved successfully.';
                        $response['data'] = $post;
                    } else {
                        $response['message'] = 'Blog post not found.';
                    }
                } else {
                    $stmt = $pdo->query('SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC');
                    $posts = $stmt->fetchAll();
                    $response['success'] = true;
                    $response['message'] = 'All blog posts retrieved successfully.';
                    $response['data'] = $posts;
                }
                break;

            default:
                $response['message'] = 'Invalid GET action specified or no action provided.';
                break;
        }
    } else {
        $response['message'] = 'Unsupported request method.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Application error: ' . $e->getMessage();
}

echo json_encode($response);
?>

<?php
// public/index.php

ob_start();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['action'] = 'read';
require_once __DIR__ . '/../handlers/blog_post_handler.php';
$read_response_json = ob_get_clean();

$posts = [];
$read_error = null;

$decoded_response = json_decode($read_response_json, true);
if ($decoded_response && $decoded_response['success']) {
    $posts = $decoded_response['data'] ?? [];
} else {
    $read_error = $decoded_response['message'] ?? 'Failed to retrieve blog posts: Unknown error from handler.';
}

unset($_SERVER['REQUEST_METHOD']);
unset($_GET['action']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Manager</title>
</head>
<body>
    <h1>Create New Blog Post</h1>
    <form action="/handlers/blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" cols="50" required></textarea><br><br>
        <button type="submit">Create Post</button>
    </form>

    <hr>

    <h1>Existing Blog Posts</h1>
    <?php if ($read_error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($read_error); ?></p>
    <?php else: ?>
        <?php if (empty($posts)): ?>
            <p>No blog posts found.</p>
        <?php else: ?>
            <?php foreach ($posts as $post): ?>
                <div>
                    <h2><?php echo htmlspecialchars($post['title']); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                    <small>Created on: <?php echo htmlspecialchars($post['created_at']); ?></small><br>

                    <h3>Update Post (ID: <?php echo htmlspecialchars($post['id']); ?>)</h3>
                    <form action="/handlers/blog_post_handler.php" method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                        <label for="update_title_<?php echo $post['id']; ?>">Title:</label><br>
                        <input type="text" id="update_title_<?php echo $post['id']; ?>" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required><br><br>
                        <label for="update_content_<?php echo $post['id']; ?>">Content:</label><br>
                        <textarea id="update_content_<?php echo $post['id']; ?>" name="content" rows="5" cols="40" required><?php echo htmlspecialchars($post['content']); ?></textarea><br><br>
                        <button type="submit">Update Post</button>
                    </form>

                    <h3>Delete Post (ID: <?php echo htmlspecialchars($post['id']); ?>)</h3>
                    <form action="/handlers/blog_post_handler.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this post?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                        <button type="submit" style="background-color: red; color: white;">Delete Post</button>
                    </form>
                    <hr>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
?>