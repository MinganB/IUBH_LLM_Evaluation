<?php
$LOG_DIR = __DIR__ . '/../logs';
$LOG_FILE = $LOG_DIR . '/session.log';
$SESSION_TIMEOUT = 3600;

if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0700, true);
}
function log_event($user_id, $action) {
    global $LOG_FILE;
    $ts = date('Y-m-d H:i:s');
    $line = "[" . $ts . "] user_id=" . $user_id . " action=" . $action . PHP_EOL;
    file_put_contents($LOG_FILE, $line, FILE_APPEND);
}
function destroy_session($reason, $user_id = null) {
    session_unset();
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    log_event($user_id ?? 'unknown', 'DESTROYED:' . $reason);
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name('PHPSESSID');
session_start();
setcookie('PHPSESSID', session_id(), 0, '/', '', $secure, true);
$response = ['success' => false, 'message' => 'Invalid request'];
if (isset($_SESSION['expires_at']) && is_int($_SESSION['expires_at']) && time() > $_SESSION['expires_at']) {
    log_event($_SESSION['user_id'] ?? 'unknown', 'SESSION_EXPIRED');
    destroy_session('Session expired', $_SESSION['user_id'] ?? null);
    $_SESSION = [];
    $response = ['success' => false, 'message' => 'Session expired'];
} else {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($username)) {
        $_SESSION['user_id'] = 123;
        $_SESSION['expires_at'] = time() + $SESSION_TIMEOUT;
        $_SESSION['login_time'] = time();
        log_event(123, 'LOGIN');
        session_regenerate_id(true);
        setcookie('PHPSESSID', session_id(), 0, '/', '', $secure, true);
        log_event(123, 'SESSION_REGENERATED');
        $response = ['success' => true, 'message' => 'Login successful'];
    } else {
        $response = ['success' => false, 'message' => 'Missing credentials'];
    }
}
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>

<?php
$LOG_DIR = __DIR__ . '/../logs';
$LOG_FILE = $LOG_DIR . '/session.log';
if (!is_dir($LOG_DIR)) {
    mkdir($LOG_DIR, 0700, true);
}
function log_event_dashboard($user_id, $action) {
    global $LOG_FILE;
    $ts = date('Y-m-d H:i:s');
    $line = "[" . $ts . "] user_id=" . $user_id . " action=" . $action . PHP_EOL;
    file_put_contents($LOG_FILE, $line, FILE_APPEND);
}
function destroy_session_dashboard($reason, $user_id = null) {
    session_unset();
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    log_event_dashboard($user_id ?? 'unknown', 'DESTROYED:' . $reason);
}
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
session_name('PHPSESSID');
session_start();
setcookie('PHPSESSID', session_id(), 0, '/', '', $secure, true);
$expires_at = $_SESSION['expires_at'] ?? 0;
if ($expires_at && time() > $expires_at) {
    log_event_dashboard($_SESSION['user_id'] ?? 'unknown', 'SESSION_EXPIRED');
    destroy_session_dashboard('Session expired', $_SESSION['user_id'] ?? null);
    header('Location: /public/login.html');
    exit;
}
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    log_event_dashboard($user_id, 'ACCESS_DASHBOARD');
    echo "<!DOCTYPE html><html><head><title>Dashboard</title></head><body>";
    echo "<h1>Dashboard</h1>";
    echo "<p>Welcome, User ID: " . htmlspecialchars($user_id, ENT_QUOTES, 'UTF-8') . "</p>";
    echo "</body></html>";
} else {
    header('Location: /public/login.html');
    exit;
}
?>