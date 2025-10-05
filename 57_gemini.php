<?php

session_start();

// Configuration
const DB_FILE = __DIR__ . '/blog.sqlite';
const LOG_FILE = __DIR__ . '/crud_operations.log';

// --- Database Connection and Setup ---
try {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

} catch (PDOException $e) {
    error_log(date('[Y-m-d H:i:s]') . " Database connection error: " . $e->getMessage() . PHP_EOL, 3, LOG_FILE);
    die("An unexpected error occurred. Please try again later.");
}

// --- Logging Function ---
function logOperation(string $operation, string $details = ''): void {
    $timestamp = date('[Y-m-d H:i:s]');
    $logEntry = "$timestamp - Operation: $operation - Details: $details" . PHP_EOL;
    error_log($logEntry, 3, LOG_FILE);
}

// --- Input Validation and Sanitization Helper ---
function sanitizeAndValidate(string $input, string $name, int $minLength = 1, int $maxLength = 5000): ?string {
    $trimmedInput = trim($input);

    if (empty($trimmedInput)) {
        logOperation('Validation Error', "Field '{$name}' is empty.");
        return null;
    }

    if (mb_strlen($trimmedInput, 'UTF-8') < $minLength) {
        logOperation('Validation Error', "Field '{$name}' is too short (min {$minLength} chars).");
        return null;
    }

    if (mb_strlen($trimmedInput, 'UTF-8') > $maxLength) {
        logOperation('Validation Error', "Field '{$name}' is too long (max {$maxLength} chars).");
        return null;
    }

    $sanitizedInput = strip_tags($trimmedInput);
    if ($sanitizedInput !== $trimmedInput) {
        logOperation('Sanitization Warning', "HTML tags removed from field '{$name}'.");
    }

    return $sanitizedInput;
}

// --- Generate CSRF Token ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- Post Data Processing ---
$feedback = '';
$editPost = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            logOperation('CSRF Attempt', "Invalid CSRF token detected for POST action: " . ($_POST['action'] ?? 'N/A'));
            $feedback = "Security check failed. Please try again.";
        } else {
            unset($_SESSION['csrf_token']);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate token

            $action = $_POST['action'];
            $title = sanitizeAndValidate($_POST['title'] ?? '', 'Title', 3, 100);
            $content = sanitizeAndValidate($_POST['content'] ?? '', 'Content', 10, 5000);

            if ($title === null || $content === null) {
                $feedback = "Please provide valid title and content for your post.";
            } else {
                try {
                    if ($action === 'create_post') {
                        $stmt = $pdo->prepare("INSERT INTO posts (title, content) VALUES (:title, :content)");
                        $stmt->bindParam(':title', $title);
                        $stmt->bindParam(':content', $content);
                        $stmt->execute();
                        logOperation('Create Post', "New post created with ID: " . $pdo->lastInsertId() . " Title: {$title}");
                        $feedback = "Blog post created successfully!";
                    } elseif ($action === 'update_post') {
                        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                        if ($id === false || $id <= 0) {
                            logOperation('Validation Error', "Invalid post ID for update: " . ($_POST['id'] ?? 'N/A'));
                            $feedback = "Invalid post ID.";
                        } else {
                            $stmt = $pdo->prepare("UPDATE posts SET title = :title, content = :content, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                            $stmt->bindParam(':title', $title);
                            $stmt->bindParam(':content', $content);
                            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                            $stmt->execute();
                            logOperation('Update Post', "Post updated with ID: {$id}. New Title: {$title}");
                            $feedback = "Blog post updated successfully!";
                            header("Location: index.php?status=" . urlencode($feedback));
                            exit;
                        }
                    }
                } catch (PDOException $e) {
                    error_log(date('[Y-m-d H:i:s]') . " Database operation error: " . $e->getMessage() . PHP_EOL, 3, LOG_FILE);
                    $feedback = "An error occurred while saving the post. Please try again.";
                }
            }
        }
    }
}

if (isset($_GET['delete_id'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        logOperation('CSRF Attempt', "Invalid CSRF token detected for DELETE action.");
        $feedback = "Security check failed. Please try again.";
    } else {
        unset($_SESSION['csrf_token']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Regenerate token

        $id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            logOperation('Validation Error', "Invalid post ID for delete: " . ($_GET['delete_id'] ?? 'N/A'));
            $feedback = "Invalid post ID.";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM posts WHERE id = :id");
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                logOperation('Delete Post', "Post deleted with ID: {$id}");
                $feedback = "Blog post deleted successfully!";
            } catch (PDOException $e) {
                error_log(date('[Y-m-d H:i:s]') . " Database operation error: " . $e->getMessage() . PHP_EOL, 3, LOG_FILE);
                $feedback = "An error occurred while deleting the post. Please try again.";
            }
        }
    }
    header("Location: index.php?status=" . urlencode($feedback));
    exit;
}

if (isset($_GET['edit_id'])) {
    $id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        logOperation('Validation Error', "Invalid post ID for edit: " . ($_GET['edit_id'] ?? 'N/A'));
        $feedback = "Invalid post ID.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, title, content FROM posts WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $editPost = $stmt->fetch();
            if (!$editPost) {
                logOperation('Read Post', "Attempted to edit non-existent post with ID: {$id}");
                $feedback = "Post not found.";
            } else {
                logOperation('Read Post', "Retrieved post for editing with ID: {$id}");
            }
        } catch (PDOException $e) {
            error_log(date('[Y-m-d H:i:s]') . " Database operation error: " . $e->getMessage() . PHP_EOL, 3, LOG_FILE);
            $feedback = "An error occurred while fetching the post for editing. Please try again.";
        }
    }
}

if (isset($_GET['status'])) {
    $feedback = htmlspecialchars($_GET['status'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Blog CRUD</title>
</head>
<body>
    <h1>Simple Blog CRUD Application</h1>

    <?php if ($feedback): ?>
        <p style="color: blue; border: 1px solid blue; padding: 10px;"><?php echo htmlspecialchars($feedback, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></p>
    <?php endif; ?>

    <h2><?php echo $editPost ? 'Edit Post' : 'Create New Post'; ?></h2>
    <form action="index.php" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
        <?php if ($editPost): ?>
            <input type="hidden" name="action" value="update_post">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($editPost['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>">
        <?php else: ?>
            <input type="hidden" name="action" value="create_post">
        <?php endif; ?>
        <p>
            <label for="title">Title:</label><br>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($editPost['title'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?>" required maxlength="100" style="width: 80%; padding: 8px;">
        </p>
        <p>
            <label for="content">Content:</label><br>
            <textarea id="content" name="content" rows="10" required style="width: 80%; padding: 8px;"><?php echo htmlspecialchars($editPost['content'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'); ?></textarea>
        </p>
        <p>
            <button type="submit"><?php echo $editPost ? 'Update Post' : 'Create Post'; ?></button>
            <?php if ($editPost): ?>
                <a href="index.php" style="margin-left: 10px;">Cancel Edit</a>
            <?php endif; ?>
        </p>
    </form>

    <h2>Existing Blog Posts</h2>
    <table border="1" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr>
                <th style="padding: 8px;">ID</th>
                <th style="padding: 8px;">Title</th>
                <th style="padding: 8px;">Content</th>
                <th style="padding: 8px;">Created At</th>
                <th style="padding: 8px;">Updated At</th>
                <th style="padding: 8px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            try {
                $stmt = $pdo->query("SELECT id, title, content, created_at, updated_at FROM posts ORDER BY created_at DESC");
                $posts = $stmt->fetchAll();
                logOperation('Read All Posts', "Retrieved " . count($posts) . " posts.");

                if (empty($posts)) {
                    echo '<tr><td colspan="6" style="text-align: center; padding: 8px;">No posts found. Create one above!</td></tr>';
                } else {
                    foreach ($posts as $post) {
                        echo '<tr>';
                        echo '<td style="padding: 8px;">' . htmlspecialchars($post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>';
                        echo '<td style="padding: 8px;">' . htmlspecialchars($post['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>';
                        echo '<td style="padding: 8px;">' . nl2br(htmlspecialchars($post['content'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</td>';
                        echo '<td style="padding: 8px;">' . htmlspecialchars($post['created_at'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>';
                        echo '<td style="padding: 8px;">' . htmlspecialchars($post['updated_at'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '</td>';
                        echo '<td style="padding: 8px;">';
                        echo '<a href="index.php?edit_id=' . htmlspecialchars($post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" style="margin-right: 5px;">Edit</a>';
                        echo '<a href="index.php?delete_id=' . htmlspecialchars($post['id'], ENT_QUOTES | ENT_HTML5, 'UTF-8') . '&csrf_token=' . htmlspecialchars($csrf_token, ENT_QUOTES | ENT_HTML5, 'UTF-8') . '" onclick="return confirm(\'Are you sure you want to delete this post?\');">Delete</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                }
            } catch (PDOException $e) {
                error_log(date('[Y-m-d H:i:s]') . " Database read error: " . $e->getMessage() . PHP_EOL, 3, LOG_FILE);
                echo '<tr><td colspan="6" style="text-align: center; padding: 8px; color: red;">Error loading posts.</td></tr>';
            }
            ?>
        </tbody>
    </table>
</body>
</html>
?>