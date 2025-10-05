<?php
// session_config.php

define('LOG_FILE', __DIR__ . '/session_events.log');
define('SESSION_ABSOLUTE_TIMEOUT', 3600);
define('SESSION_INACTIVITY_TIMEOUT', 1800);

function configure_session_security() {
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.use_trans_sid', 0);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    session_name('MYAPPSESSID');
}

function log_session_event($user_id, $event_type, $session_id) {
    $timestamp = date('Y-m-d H:i:s');
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
    $log_entry = sprintf("[%s] IP: %s, User ID: %s, Session ID: %s, Event: %s\n",
        $timestamp,
        $client_ip,
        $user_id !== null ? $user_id : 'N/A',
        $session_id !== null ? $session_id : 'N/A',
        $event_type
    );
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
}

function destroy_current_session_completely($user_id_for_logging = null, $event_type_for_logging = 'unknown_destruction') {
    $session_id_to_log = session_id();
    
    log_session_event($user_id_for_logging, $event_type_for_logging, $session_id_to_log);

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params["path"],
            'domain' => $params["domain"],
            'secure' => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => $params["samesite"] ?? 'Lax'
        ]);
    }

    session_destroy();
}
?>
<?php
// session_handler.php

require_once 'session_config.php';

configure_session_security();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === 'admin' && $password === 'password') {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = 123;
        $_SESSION['logged_in'] = true;
        $_SESSION['last_activity'] = time();
        $_SESSION['absolute_timeout'] = time() + SESSION_ABSOLUTE_TIMEOUT;

        log_session_event($_SESSION['user_id'], 'login_success', session_id());

        header('Location: dashboard.php');
        exit();
    } else {
        log_session_event(null, 'login_failed', null);
        header('Location: login_form.php?error=invalid_credentials');
        exit();
    }
} else {
    header('Location: login_form.php');
    exit();
}
?>
<?php
// dashboard.php

require_once 'session_config.php';

configure_session_security();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $user_id_for_logging = $_SESSION['user_id'] ?? null;
    destroy_current_session_completely($user_id_for_logging, 'logout_event');
    
    header('Location: login_form.php?status=loggedout');
    exit();
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['user_id'])) {
    if (session_id() !== '') {
        destroy_current_session_completely(null, 'unauthorized_access');
    }
    header('Location: login_form.php?error=unauthorized');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_session_id = session_id();

if (isset($_SESSION['absolute_timeout']) && time() > $_SESSION['absolute_timeout']) {
    destroy_current_session_completely($current_user_id, 'absolute_timeout');
    header('Location: login_form.php?timeout=absolute');
    exit();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_INACTIVITY_TIMEOUT)) {
    destroy_current_session_completely($current_user_id, 'inactivity_timeout');
    header('Location: login_form.php?timeout=inactivity');
    exit();
}

$_SESSION['last_activity'] = time();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome to the Dashboard, User <?php echo htmlspecialchars($current_user_id); ?>!</h2>
    <p>This is your secure content.</p>
    <p>Your current session ID is: <?php echo htmlspecialchars($current_session_id); ?></p>
    <p>Last activity: <?php echo date('Y-m-d H:i:s', $_SESSION['last_activity']); ?></p>
    <p>Session will absolutely expire at: <?php echo date('Y-m-d H:i:s', $_SESSION['absolute_timeout']); ?></p>
    <br>
    <form action="dashboard.php" method="GET">
        <input type="hidden" name="action" value="logout">
        <input type="submit" value="Logout">
    </form>
</body>
</html>
?>