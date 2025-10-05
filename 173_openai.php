<?php
declare(strict_types=1);

$pdo = null;
try {
    $pdo = new PDO('sqlite:' . __DIR__ . '/users.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

function ensureUsersTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

function ensureDefaultUser(PDO $pdo): void {
    $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM users");
    $row = $stmt->fetch();
    if ((int)$row['cnt'] === 0) {
        $username = 'admin';
        $password = 'Admin@1234';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt2 = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (:u, :h)");
        $stmt2->execute([':u' => $username, ':h' => $hash]);
    }
}

ensureUsersTable($pdo);
ensureDefaultUser($pdo);

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    if (isset($_COOKIE['AUTH_TOKEN'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        setcookie('AUTH_TOKEN', '', time() - 3600, '/', '', $https, true);
    }
    header('Location: session_handler.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $username = trim($username);
    $password = trim($password);

    if ($username === '' || $password === '') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Missing credentials']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        if (version_compare(PHP_VERSION, '7.3.0', '>='))
        {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'] ?? '',
                'secure' => $https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, '/', $_SERVER['HTTP_HOST'] ?? '', $https, true);
        }
        session_start();
        if (function_exists('session_regenerate_id')) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $token = bin2hex(random_bytes(32));
        $_SESSION['auth_token'] = $token;
        setcookie('AUTH_TOKEN', $token, time() + 24 * 60 * 60, '/', $_SERVER['HTTP_HOST'] ?? '', $https, true);

        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok']);
        } else {
            header('Location: dashboard.php');
        }
        exit;
    } else {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Login</title>
    </head>
    <body>
    <h1>Login</h1>
    <form method="POST" action="session_handler.php" autocomplete="on">
        <label>
            Username:
            <input type="text" name="username" required>
        </label><br>
        <label>
            Password:
            <input type="password" name="password" required>
        </label><br>
        <button type="submit">Login</button>
    </form>
    </body>
    </html>
    <?php
    exit;
}
?> 
<?php
declare(strict_types=1);

session_start();
$token = $_COOKIE['AUTH_TOKEN'] ?? '';
$valid = isset($_SESSION['user_id'], $_SESSION['auth_token'], $_SESSION['username']) && hash_equals($_SESSION['auth_token'], $token);
if (!$valid) {
    header('Location: session_handler.php');
    exit;
}
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
</head>
<body>
  <h1>Dashboard</h1>
  <p>Welcome, <?php echo $username; ?>.</p>
  <p><a href="session_handler.php?action=logout">Logout</a></p>
</body>
</html>
?>