<?php

define('POSTS_FILE', __DIR__ . '/posts.json');

function read_posts() {
    if (!file_exists(POSTS_FILE) || filesize(POSTS_FILE) === 0) {
        return [];
    }
    $json = file_get_contents(POSTS_FILE);
    $posts = json_decode($json, true);
    if (!is_array($posts)) {
        return [];
    }
    return $posts;
}

function write_posts(array $posts) {
    file_put_contents(POSTS_FILE, json_encode($posts, JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (!empty($title) && !empty($content)) {
            $posts = read_posts();
            $new_post = [
                'id' => uniqid(),
                'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
                'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
                'created_at' => date('Y-m-d H:i:s')
            ];
            $posts[] = $new_post;
            write_posts($posts);
        }
    } elseif ($action === 'update') {
        $id = $_POST['id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (!empty($id) && !empty($title) && !empty($content)) {
            $posts = read_posts();
            foreach ($posts as &$post) {
                if ($post['id'] === $id) {
                    $post['title'] = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
                    $post['content'] = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                    break;
                }
            }
            unset($post);
            write_posts($posts);
        }
    }
    header('Location: index.php');
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'delete') {
        $id = $_GET['id'] ?? '';

        if (!empty($id)) {
            $posts = read_posts();
            $posts = array_filter($posts, function($post) use ($id) {
                return $post['id'] !== $id;
            });
            write_posts($posts);
        }
    }
    if ($action !== 'edit') {
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Post Manager</title>
</head>
<body>

    <h1>Blog Post Manager</h1>

    <?php
    // This part of the code is executed in index.php to display the form.
    // blog_post_handler.php will be included in index.php.
    // If we're in blog_post_handler.php for a GET action that isn't 'edit',
    // a redirect happens above and this HTML won't be rendered.
    // If it's an 'edit' GET action, index.php will handle rendering this HTML.
    
    $editing_post = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $post_id = $_GET['id'];
        $posts = read_posts();
        foreach ($posts as $post) {
            if ($post['id'] === $post_id) {
                $editing_post = $post;
                break;
            }
        }
    }
    ?>

    <h2><?php echo $editing_post ? 'Edit Blog Post' : 'Create New Blog Post'; ?></h2>
    <form action="blog_post_handler.php" method="POST">
        <?php if ($editing_post): ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editing_post['id'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create">
        <?php endif; ?>

        <label for="title">Title:</label><br>
        <input type="text" id="title" name="title" value="<?php echo $editing_post ? htmlspecialchars($editing_post['title'], ENT_QUOTES, 'UTF-8') : ''; ?>" required><br><br>

        <label for="content">Content:</label><br>
        <textarea id="content" name="content" rows="10" cols="50" required><?php echo $editing_post ? htmlspecialchars($editing_post['content'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea><br><br>

        <button type="submit"><?php echo $editing_post ? 'Update Post' : 'Create Post'; ?></button>
        <?php if ($editing_post): ?>
            <a href="index.php">Cancel Edit</a>
        <?php endif; ?>
    </form>

    <h2>Existing Blog Posts</h2>
    <?php
    $posts = read_posts();
    if (empty($posts)): ?>
        <p>No blog posts found.</p>
    <?php else: ?>
        <?php foreach (array_reverse($posts) as $post): ?>
            <div>
                <h3><?php echo htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                <p><?php echo nl2br(htmlspecialchars($post['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                <small>Posted on: <?php echo htmlspecialchars($post['created_at'], ENT_QUOTES, 'UTF-8'); ?></small><br>
                <a href="?action=edit&id=<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>">Edit</a> |
                <a href="blog_post_handler.php?action=delete&id=<?php echo htmlspecialchars($post['id'], ENT_QUOTES, 'UTF-8'); ?>" onclick="return confirm('Are you sure you want to delete this post?');">Delete</a>
            </div>
            <hr>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
?>