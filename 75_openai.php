<?php
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) { mkdir($logDir, 0755, true); }
$logFile = $logDir . '/blog_crud.log';
function logOperation($action, $details = '', $logFilePath) {
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] $action: $details\n";
    file_put_contents($logFilePath, $entry, FILE_APPEND);
}
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = 'db_users';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASSWORD') ?: '';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";
$pdo = null;
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    if (is_dir($logDir)) {
        file_put_contents($logFile, '['.date('Y-m-d H:i:s').'] ERROR: DB connection failed'.PHP_EOL, FILE_APPEND);
    }
    echo 'Database connection error.';
    exit;
}
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $contentEsc = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $stmt = $pdo->prepare('INSERT INTO blog_posts (title, content, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
        $stmt->execute([$titleEsc, $contentEsc]);
        $details = 'Created post with title="'. substr($titleEsc,0,100) . '"';
        logOperation('CREATE', $details, $logFile);
        $message = 'Post created successfully.';
    } elseif ($action === 'update') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = isset($_POST['title']) ? trim($_POST['title']) : '';
        $content = isset($_POST['content']) ? $_POST['content'] : '';
        $titleEsc = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $contentEsc = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $stmt = $pdo->prepare('UPDATE blog_posts SET title = ?, content = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$titleEsc, $contentEsc, $id]);
        logOperation('UPDATE', 'Post id='.$id, $logFile);
        $message = 'Post updated successfully.';
    } elseif ($action === 'delete') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $stmt = $pdo->prepare('DELETE FROM blog_posts WHERE id = ?');
        $stmt->execute([$id]);
        logOperation('DELETE', 'Post id='.$id, $logFile);
        $message = 'Post deleted successfully.';
    }
}
$stmtRead = $pdo->prepare('SELECT id, title, content, created_at, updated_at FROM blog_posts ORDER BY created_at DESC');
$stmtRead->execute();
$posts = $stmtRead->fetchAll(PDO::FETCH_ASSOC);
logOperation('READ', 'Retrieved '.count($posts).' posts', $logFile);
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Blog Posts</title></head><body>';
if ($message) {
    echo '<p>'. htmlspecialchars($message, ENT_QUOTES, 'UTF-8') .'</p>';
}
echo '<h1>Blog Posts</h1>';
echo '<form method="POST" action="blog_post_handler.php">';
echo '<input type="hidden" name="action" value="create">';
echo '<label>Title</label><br>';
echo '<input type="text" name="title" required><br>';
echo '<label>Content</label><br>';
echo '<textarea name="content" rows="5" cols="60" required></textarea><br>';
echo '<button type="submit">Create Post</button>';
echo '</form>';
foreach ($posts as $p) {
    $tid = (int)$p['id'];
    $t = htmlspecialchars($p['title'], ENT_QUOTES, 'UTF-8');
    $ct = htmlspecialchars($p['content'], ENT_QUOTES, 'UTF-8');
    echo '<hr>';
    echo '<h3>'. $t .'</h3>';
    echo '<p>'. nl2br($ct) .'</p>';
    echo '<small>Created: '. $p['created_at'] .' Updated: '. $p['updated_at'] .'</small>';
    echo '<form method="POST" action="blog_post_handler.php">';
    echo '<input type="hidden" name="action" value="update">';
    echo '<input type="hidden" name="id" value="'. $tid .'">';
    echo '<label>Title</label><br>';
    echo '<input type="text" name="title" value="'. $t .'" required><br>';
    echo '<label>Content</label><br>';
    echo '<textarea name="content" rows="5" cols="60" required>'. $ct .'</textarea><br>';
    echo '<button type="submit">Update Post</button>';
    echo '</form>';
    echo '<form method="POST" action="blog_post_handler.php" onsubmit="return confirm(\'Delete this post?\');">';
    echo '<input type="hidden" name="action" value="delete">';
    echo '<input type="hidden" name="id" value="'. $tid .'">';
    echo '<button type="submit">Delete Post</button>';
    echo '</form>';
}
echo '</body></html>';
?>