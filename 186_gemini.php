<?php
// session_handler.php

const LOG_FILE = __DIR__ . '/session_log.txt';
const SESSION_ABSOLUTE_TIMEOUT = 1800; // 30 minutes in seconds

function log_event($message, $userId = null, $sessionId = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] ";
    if ($userId !== null) {
        $log_entry .= "[User:$userId] ";
    }
    if ($sessionId !== null) {
        $log_entry .= "[Session:$sessionId] ";
    }
    $log_entry .= "$message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

function set_secure_session_cookie_params() {
    $lifetime = 0;
    $path = '/';
    $domain = '';
    $secure = true;
    $httponly = true;
    $samesite = 'Lax';

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);
    } else {
        session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
        header('Set-Cookie: PHPSESSID=' . session_id() . '; Path=' . $path . '; Domain=' . $domain . '; Secure; HttpOnly; SameSite=' . $samesite, false);
    }
}

set_secure_session_cookie_params();
session_start();
session_regenerate_id(true);

$_SESSION['user_id'] = 123;
$_SESSION['CREATED'] = time();
$_SESSION['LAST_ACTIVITY'] = time();

log_event("Session created (login successful)", $_SESSION['user_id'], session_id());

header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Login successful']);
exit;

<?php
// dashboard.php

const LOG_FILE = __DIR__ . '/session_log.txt';
const SESSION_ABSOLUTE_TIMEOUT = 1800; // 30 minutes in seconds
const SESSION_INACTIVITY_TIMEOUT = 300; // 5 minutes inactivity

function log_event($message, $userId = null, $sessionId = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] ";
    if ($userId !== null) {
        $log_entry .= "[User:$userId] ";
    }
    if ($sessionId !== null) {
        $log_entry .= "[Session:$sessionId] ";
    }
    $log_entry .= "$message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

function set_secure_session_cookie_params() {
    $lifetime = 0;
    $path = '/';
    $domain = '';
    $secure = true;
    $httponly = true;
    $samesite = 'Lax';

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => $samesite
        ]);
    } else {
        session_set_cookie_params($lifetime, $path, $domain, $secure, $httponly);
        header('Set-Cookie: PHPSESSID=' . session_id() . '; Path=' . $path . '; Domain=' . $domain . '; Secure; HttpOnly; SameSite=' . $samesite, false);
    }
}

function destroy_session_securely() {
    $sessionId = session_id();
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A';
    log_event("Session destroyed", $userId, $sessionId);

    $_SESSION = array();

    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );

    session_destroy();
}

set_secure_session_cookie_params();
session_start();

if (isset($_GET['logout'])) {
    destroy_session_securely();
    header('Location: session_handler.php');
    exit;
}

if (isset($_SESSION['CREATED']) && (time() - $_SESSION['CREATED'] > SESSION_ABSOLUTE_TIMEOUT)) {
    log_event("Session absolute timeout", isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A', session_id());
    destroy_session_securely();
    header('Location: session_handler.php');
    exit;
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_INACTIVITY_TIMEOUT)) {
    log_event("Session inactivity timeout", isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A', session_id());
    destroy_session_securely();
    header('Location: session_handler.php');
    exit;
}

$_SESSION['LAST_ACTIVITY'] = time();

if (!isset($_SESSION['user_id'])) {
    log_event("Unauthorized access attempt (no user_id)", 'N/A', session_id());
    destroy_session_securely();
    header('Location: session_handler.php');
    exit;
}

$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome to the Dashboard!</h1>
    <p>You are logged in as User ID: <?php echo htmlspecialchars($userId); ?>.</p>
    <p>This is your secure content.</p>
    <p><a href="dashboard.php?logout=1">Logout</a></p>
</body>
</html>
?>