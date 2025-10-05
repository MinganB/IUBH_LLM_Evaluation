<?php
define('ABSOLUTE_SESSION_LIFETIME', 3600);
define('SESSION_IDLE_TIMEOUT', 1800);
define('SESSION_LOG', __DIR__ . '/session.log');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$secure = $https;
session_set_cookie_params(0, '/', '', $secure, true);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function log_session_event($userId, $action){
    if (!defined('SESSION_LOG')) return;
    $file = SESSION_LOG;
    if (empty($userId)) $userId = 'unknown';
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] user_id=$userId action=$action\n";
    $dir = dirname($file);
    if (!is_dir($dir)) { mkdir($dir, 0700, true); }
    if (is_writable($dir)) {
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
function is_session_expired(){
    if (!isset($_SESSION['expires_at'])) return true;
    if (time() > $_SESSION['expires_at']) return true;
    return false;
}
function login_user($userId, $username){
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$userId;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
    $_SESSION['created_at'] = time();
    $_SESSION['expires_at'] = time() + ABSOLUTE_SESSION_LIFETIME;
    $_SESSION['last_active'] = time();
    log_session_event($_SESSION['user_id'], 'LOGIN');
}
function logout_user($redirectUrl = 'login.php'){
    if (isset($_SESSION['user_id'])) {
        log_session_event($_SESSION['user_id'], 'LOGOUT');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header("Location: $redirectUrl");
    exit;
}
function require_login(){
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active'] > SESSION_IDLE_TIMEOUT)) {
        logout_user('login.php');
    } else {
        $_SESSION['last_active'] = time();
    }
    if (is_session_expired()) {
        logout_user('login.php');
    }
}
?> 
<?php
require_once 'session_manager.php';
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'testdb';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$error = '';
$username = '';
$password = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username !== '' && $password !== '') {
        try {
            $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = :username');
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user['id'], $username);
                header('Location: protected.php');
                exit;
            } else {
                $error = 'Invalid credentials';
            }
        } catch (Exception $e) {
            $error = 'Authentication error';
        }
    } else {
        $error = 'Please enter username and password';
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<?php if ($error): ?>
<p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<form method="post" action="login.php">
    <label>Username</label><input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" />
    <label>Password</label><input type="password" name="password" />
    <button type="submit">Login</button>
</form>
</body>
</html>
<?php
?> 
<?php
require_once 'session_manager.php';
require_login();
?>
<!DOCTYPE html>
<html>
<head><title>Protected Content</title></head>
<body>
<h1>Protected Content</h1>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>.</p>
<p>Login time: <?php echo date('Y-m-d H:i:s', $_SESSION['login_time']); ?></p>
<p>Session expires at: <?php echo date('Y-m-d H:i:s', $_SESSION['expires_at']); ?></p>
<p><a href="logout.php">Logout</a></p>
</body>
</html>
<?php
?> 
<?php
require_once 'session_manager.php';
logout_user('login.php');
?>