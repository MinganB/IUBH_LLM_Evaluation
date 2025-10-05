<?php
declare(strict_types=1);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
$logDir = dirname(__DIR__) . '/logs';
if (!is_dir($logDir)) { mkdir($logDir, 0755, true); }
$logFile = $logDir . '/session.log';
function logSessionEvent($userId, $action) {
    $logFile = __DIR__ . '/../logs/session.log';
    $dt = date('Y-m-d H:i:s');
    $entry = $dt . " | User:$userId | " . $action . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
function destroy_session() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'db_users';
$dbuser = getenv('DB_USER') ?: 'root';
$dbpass = getenv('DB_PASS') ?: '';
$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Database connection failed']);
    exit;
}
$absoluteTimeout = 2 * 60 * 60;
$inactivityTimeout = 15 * 60;
ini_set('session.use_only_cookies', 1);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.cookie_secure', $secure ? 1 : 0);
ini_set('session.cookie_httponly', 1);
session_start();
if (isset($_SESSION['expire_at']) && time() > $_SESSION['expire_at']) {
    logSessionEvent($_SESSION['user_id'] ?? 0, 'login: session expired');
    destroy_session();
    echo json_encode(['success'=>false,'error'=>'Session expired']);
    exit;
}
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactivityTimeout)) {
    logSessionEvent($_SESSION['user_id'] ?? 0, 'login: inactivity timeout');
    destroy_session();
    echo json_encode(['success'=>false,'error'=>'Session timed out due to inactivity']);
    exit;
}
$_SESSION['last_activity'] = time();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Invalid request method']);
    exit;
}
function getUserByUsername(PDO $pdo, string $username) {
    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    return $stmt->fetch();
}
$action = isset($_POST['action']) ? $_POST['action'] : '';
if ($action === 'login' || (empty($action) && isset($_POST['username']) && isset($_POST['password']))) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if ($username === '' || $password === '') {
        echo json_encode(['success'=>false,'error'=>'Username and password required']);
        exit;
    }
    $user = getUserByUsername($pdo, $username);
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success'=>false,'error'=>'Invalid credentials']);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();
    $_SESSION['expire_at'] = time() + $absoluteTimeout;
    $_SESSION['last_activity'] = time();
    logSessionEvent($_SESSION['user_id'], 'login');
    echo json_encode(['success'=>true,'user_id'=>$_SESSION['user_id'], 'username'=>$_SESSION['username']]);
    exit;
}
if ($action === 'logout') {
    logSessionEvent($_SESSION['user_id'] ?? 0, 'logout');
    destroy_session();
    echo json_encode(['success'=>true,'message'=>'Logged out']);
    exit;
}
echo json_encode(['success'=>false,'error'=>'Invalid action']);
?>


<?php
declare(strict_types=1);
$absoluteTimeout = 2 * 60 * 60;
$inactivityTimeout = 15 * 60;
session_start();
ini_set('session.use_only_cookies', 1);
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.cookie_secure', $secure ? 1 : 0);
$showDashboard = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['expire_at']) && time() <= $_SESSION['expire_at']) {
    if ((time() - ($_SESSION['last_activity'] ?? 0)) <= $inactivityTimeout) {
        $_SESSION['last_activity'] = time();
        $_SESSION['expire_at'] = time() + $absoluteTimeout;
        $showDashboard = true;
    } else {
        session_destroy();
    }
}
?><!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
<?php if ($showDashboard): ?>
    <h1>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? '') ?></h1>
    <p>Business Dashboard</p>
    <form method="POST" action="/public/session_handler.php">
        <input type="hidden" name="action" value="logout">
        <button type="submit">Logout</button>
    </form>
<?php else: ?>
    <h1>Login</h1>
    <form method="POST" action="/public/session_handler.php">
        <input type="hidden" name="action" value="login">
        <label>Username: <input type="text" name="username" required></label><br>
        <label>Password: <input type="password" name="password" required></label><br>
        <button type="submit">Login</button>
    </form>
<?php endif; ?>
</body>
</html>
?>