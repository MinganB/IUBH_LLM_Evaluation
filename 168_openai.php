<?php
$LOG_FILE = __DIR__ . '/secure_session.log';
date_default_timezone_set('UTC');
$secureFlag = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on')) || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_cookies', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $secureFlag ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
define('SESSION_LIFETIME', 3600);
define('INACTIVITY_TIMEOUT', 900);

function log_event($userId, $event) {
    global $LOG_FILE;
    $ts = date('Y-m-d H:i:s');
    $entry = "[$ts] UserID:$userId - $event" . PHP_EOL;
    if (file_put_contents($LOG_FILE, $entry, FILE_APPEND) !== false) {
        if (function_exists('chmod')) @chmod($LOG_FILE, 0600);
    }
}
function is_logged_in() {
    return isset($_SESSION['user_id']);
}
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    $now = time();
    if (isset($_SESSION['created_at'])) {
        if (($now - $_SESSION['created_at']) > SESSION_LIFETIME) {
            log_event($_SESSION['user_id'] ?? 0, 'SESSION_EXPIRED_ABSOLUTE');
            logout_user();
            header('Location: login.php');
            exit;
        }
    } else {
        $_SESSION['created_at'] = $now;
    }
    if (isset($_SESSION['last_activity'])) {
        if (($now - $_SESSION['last_activity']) > INACTIVITY_TIMEOUT) {
            log_event($_SESSION['user_id'] ?? 0, 'SESSION_EXPIRED_INACTIVITY');
            logout_user();
            header('Location: login.php');
            exit;
        }
    }
    $_SESSION['last_activity'] = $now;
}
function logout_user() {
    $uid = $_SESSION['user_id'] ?? 0;
    log_event($uid, 'LOGOUT');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    if (session_status() !== PHP_SESSION_NONE) {
        session_destroy();
    }
}
function authenticate($username, $password) {
    $users = [
        'admin' => ['id' => 1, 'password' => 'secret'],
        'user1' => ['id' => 2, 'password' => 'password123'],
    ];
    if (isset($users[$username]) && $password === $users[$username]['password']) {
        return $users[$username]['id'];
    }
    return false;
}
function login_user($username, $password) {
    $userId = authenticate($username, $password);
    if ($userId !== false) {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        } else {
            session_start();
        }
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $now = time();
        $_SESSION['created_at'] = $now;
        $_SESSION['last_activity'] = $now;
        log_event($userId, 'LOGIN_SUCCESS');
        return true;
    }
    log_event(0, 'LOGIN_FAILED');
    return false;
}
?><?php
require_once 'session_manager.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (login_user($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?><!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<?php if ($error) { echo '<p style="color:red;">'.$error.'</p>'; } ?>
<form method="post" action="login.php">
  <label>Username: <input type="text" name="username" required></label><br>
  <label>Password: <input type="password" name="password" required></label><br>
  <button type="submit">Login</button>
</form>
</body>
</html>
<?php
?><?php
require_once 'session_manager.php';
require_login();
$username = $_SESSION['username'] ?? '';
?><!DOCTYPE html>
<html>
<head><title>Dashboard</title></head>
<body>
<h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
<p>This is the secure dashboard content for the business.</p>
<a href="logout.php">Logout</a>
</body>
</html>
<?php
?><?php
require_once 'session_manager.php';
if (is_logged_in()) {
    logout_user();
}
header('Location: login.php');
exit;
?>