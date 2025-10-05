<?php
// /public/create_post.php
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html>';
echo '<html lang="en">';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<title>Create Blog Post</title>';
echo '</head>';
echo '<body>';
echo '<h1>Create New Blog Post</h1>';
echo '<form action="/handlers/blog_post_handler.php" method="post">';
echo '<label for="title">Title:</label><br>';
echo '<input type="text" id="title" name="title" required><br><br>';
echo '<label for="content">Content:</label><br>';
echo '<textarea id="content" name="content" rows="10" cols="50" required></textarea><br><br>';
echo '<input type="hidden" name="action" value="create">';
echo '<button type="submit">Submit Post</button>';
echo '</form>';
echo '</body>';
echo '</html>';
?>
<?php
// /classes/Logger.php
class Logger {
    private static ?string $logFile = null;

    public static function setLogFile(string $path): void {
        self::$logFile = $path;
    }

    public static function log(string $message): void {
        if (self::$logFile === null) {
            self::$logFile = __DIR__ . '/../logs/app.log';
            $logDir = dirname(self::$logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[" . $timestamp . "] " . $message . PHP_EOL;
        error_log($logMessage, 3, self::$logFile);
    }
}
?>
<?php
// /handlers/blog_post_handler.php
require_once __DIR__ . '/../classes/Logger.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', '');
define('LOG_FILE_PATH', __DIR__ . '/../logs/blog_crud.log');

$logDir = dirname(LOG_FILE_PATH);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

Logger::setLogFile(LOG_FILE_PATH);

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);

    if (!$action) {
        throw new Exception('No action specified.');
    }

    switch ($action) {
        case 'create':
            $title = filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW);
            $content = filter_input(INPUT_POST, 'content', FILTER_UNSAFE_RAW);

            if (!$title || !$content) {
                throw new Exception('Title and content are required.');
            }

            $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

            $stmt = $pdo->prepare("INSERT INTO blog_posts (title, content) VALUES (:title, :content)");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->execute();

            $response['success'] = true;
            $response['message'] = 'Blog post created successfully with ID: ' . $pdo->lastInsertId();
            Logger::log('CREATE: New post created with ID ' . $pdo->lastInsertId());
            break;

        case 'read':
            $stmt = $pdo->prepare("SELECT id, title, content, created_at FROM blog_posts ORDER BY created_at DESC");
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['message'] = 'Blog posts retrieved successfully.';
            $response['posts'] = $posts;
            Logger::log('READ: All blog posts retrieved.');
            break;

        case 'update':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            $title = filter_input(INPUT_POST, 'title', FILTER_UNSAFE_RAW);
            $content = filter_input(INPUT_POST, 'content', FILTER_UNSAFE_RAW);

            if (!$id || !$title || !$content) {
                throw new Exception('ID, title, and content are required for update.');
            }

            $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');

            $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, content = :content WHERE id = :id");
            $stmt->bindParam(':title', $title);
            $stmt->bindParam(':content', $content);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'Blog post updated successfully.';
                Logger::log('UPDATE: Post ID ' . $id . ' updated.');
            } else {
                $response['message'] = 'No post found with ID ' . $id . ' or no changes made.';
                Logger::log('UPDATE: No post found with ID ' . $id . ' or no changes made.');
            }
            break;

        case 'delete':
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

            if (!$id) {
                throw new Exception('ID is required for deletion.');
            }

            $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'Blog post deleted successfully.';
                Logger::log('DELETE: Post ID ' . $id . ' deleted.');
            } else {
                $response['message'] = 'No post found with ID ' . $id . '.';
                Logger::log('DELETE: No post found with ID ' . $id . '.');
            }
            break;

        default:
            throw new Exception('Invalid action specified.');
    }
} catch (PDOException $e) {
    Logger::log('DATABASE ERROR: ' . $e->getMessage());
    $response['message'] = 'A database error occurred. Please try again later.';
} catch (Exception $e) {
    Logger::log('APPLICATION ERROR: ' . $e->getMessage());
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit();
?>