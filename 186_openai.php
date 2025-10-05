<?php
$LOG_FILE = __DIR__ . '/session.log';
const SESSION_LIFETIME = 3600;

session_name('PHPSESSID');
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(0, '/', '', $secure, true);
session_start();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'unknown';
    logEvent($uid, 'logout', $LOG_FILE);
    destroySession();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Logout successful.']);
    exit;
}

$created = isset($_SESSION['created_at']) ? $_SESSION['created_at'] : null;
if ($created === null) {
    $_SESSION['created_at'] = time();
} else {
    if (time() - $created > SESSION_LIFETIME) {
        $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'unknown';
        logEvent($uid, 'session_expired', $LOG_FILE);
        destroySession();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please login again.']);
        exit;
    }
}

$_SESSION['user_id'] = 123;
$_SESSION['created_at'] = time();
session_regenerate_id(true);
logEvent($_SESSION['user_id'], 'login', $LOG_FILE);

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful. Session established.']);
exit;

function logEvent($userId, $event, $logFile) {
    $ts = date('c');
    $line = sprintf("[%s] user_id=%s action=%s", $ts, $userId, $event);
    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
function destroySession() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
    session_destroy();
}
?>

<?php
$LOG_FILE = __DIR__ . '/session.log';
const SESSION_LIFETIME = 3600;

session_name('PHPSESSID');
$secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(0, '/', '', $secure, true);
session_start();

function logEvent($userId, $event, $logFile) {
    $ts = date('c');
    $line = sprintf("[%s] user_id=%s action=%s", $ts, $userId, $event);
    file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
function destroySession() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
    session_destroy();
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'unknown';
    logEvent($uid, 'logout', $LOG_FILE);
    destroySession();
    header('Location: login.php');
    exit;
}

$expired = false;
if (isset($_SESSION['created_at'])) {
    if (time() - $_SESSION['created_at'] > SESSION_LIFETIME) {
        $expired = true;
    }
} else {
    $_SESSION['created_at'] = time();
}

if (!isset($_SESSION['user_id']) || $expired) {
    $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'unknown';
    logEvent($uid, $expired ? 'session_expired' : 'unauthorized_access', $LOG_FILE);
    destroySession();
    header('Location: login.php');
    exit;
}

logEvent($_SESSION['user_id'], 'dashboard_access', $LOG_FILE);
echo "Welcome, User " . htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8') . ". This is your dashboard.";
?>