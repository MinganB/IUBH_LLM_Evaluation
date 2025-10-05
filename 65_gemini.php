<?php

function getPosts() {
    $filePath = __DIR__ . '/data/posts.json';
    if (!file_exists($filePath)) {
        return [];
    }
    $postsJson = file_get_contents($filePath);
    return json_decode($postsJson, true) ?: [];
}

$posts = getPosts();

$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Posts</title>
</head>
<body>
    <h1>Blog Posts</h1>

    <?php if ($message): ?>
        <p style="color: green;"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <h2>Create New Post</h2>
    <form action="blog_post_handler.php" method="POST">
        <input type="hidden" name="action" value="create">
        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" required><br><br>
        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="5" required></textarea><br><br>
        <button type="submit">Create Post</button>
    </form>

    <hr>

    <h2>Existing Posts</h2>
    <?php if (empty($posts)): ?>
        <p>No posts yet. Create one above!</p>
    <?php else: ?>
        <?php foreach ($posts as $post): ?>
            <div style="border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;">
                <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                <small>Posted on: <?php echo htmlspecialchars($post['created_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></small>
                <br>
                <button onclick="document.getElementById('edit-form-<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>').style.display = 'block';">Edit</button>
                <form action="blog_post_handler.php" method="POST" style="display: inline-block;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this post?');">Delete</button>
                </form>

                <div id="edit-form-<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>" style="display: none; margin-top: 15px; border-top: 1px dashed #eee; padding-top: 15px;">
                    <h4>Edit Post</h4>
                    <form action="blog_post_handler.php" method="POST">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <label for="edit_title_<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">Title:</label><br>
                        <input type="text" id="edit_title_<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>" name="title" value="<?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?>" required><br><br>
                        <label for="edit_content_<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">Content:</label><br>
                        <textarea id="edit_content_<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>" name="content" rows="5" required><?php echo htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8'); ?></textarea><br><br>
                        <button type="submit">Update Post</button>
                        <button type="button" onclick="document.getElementById('edit-form-<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>').style.display = 'none';">Cancel</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
<?php

header('Content-Type: text/html; charset=utf-8');

function getPosts() {
    $filePath = __DIR__ . '/data/posts.json';
    if (!file_exists($filePath)) {
        return [];
    }
    $postsJson = file_get_contents($filePath);
    return json_decode($postsJson, true) ?: [];
}

function savePosts(array $posts) {
    $filePath = __DIR__ . '/data/posts.json';
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($filePath, json_encode($posts, JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?message=' . urlencode('Invalid request method.'));
    exit;
}

$action = $_POST['action'] ?? '';
$message = '';

switch ($action) {
    case 'create':
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (empty($title) || empty($content)) {
            $message = 'Title and content cannot be empty.';
        } else {
            $posts = getPosts();
            $newPost = [
                'id' => uniqid(),
                'title' => $title,
                'content' => $content,
                'created_at' => date('Y-m-d H:i:s')
            ];
            $posts[] = $newPost;
            savePosts($posts);
            $message = 'Post created successfully.';
        }
        break;

    case 'update':
        $id = $_POST['id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (empty($id) || empty($title) || empty($content)) {
            $message = 'ID, title, and content cannot be empty for update.';
        } else {
            $posts = getPosts();
            $found = false;
            foreach ($posts as &$post) {
                if ($post['id'] === $id) {
                    $post['title'] = $title;
                    $post['content'] = $content;
                    $found = true;
                    break;
                }
            }
            if ($found) {
                savePosts($posts);
                $message = 'Post updated successfully.';
            } else {
                $message = 'Post not found.';
            }
        }
        break;

    case 'delete':
        $id = $_POST['id'] ?? '';

        if (empty($id)) {
            $message = 'ID cannot be empty for delete.';
        } else {
            $posts = getPosts();
            $initialCount = count($posts);
            $posts = array_filter($posts, function($post) use ($id) {
                return $post['id'] !== $id;
            });

            if (count($posts) < $initialCount) {
                savePosts($posts);
                $message = 'Post deleted successfully.';
            } else {
                $message = 'Post not found.';
            }
        }
        break;

    default:
        $message = 'Unknown action.';
        break;
}

header('Location: index.php?message=' . urlencode($message));
exit;
?>