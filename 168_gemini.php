<?php

// config.php
define('SESSION_TIMEOUT_SECONDS', 1800);
define('SESSION_LOG_FILE', __DIR__ . '/session_events.log');
define('LOGIN_PAGE', 'login.php');
define('DASHBOARD_PAGE', 'dashboard.php');

?>
<?php

// session_helper.php
require_once __DIR__ . '/config.php';

function log_session_event($event_type, $user_id, $message = '') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [User: $user_id] [$event_type] $message\n";
    error_log($log_entry, 3, SESSION_LOG_FILE);
}

function start_secure_session() {
    $params = session_get_cookie_params();
    session_set_cookie_params([
        'lifetime' => $params['lifetime'],
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function destroy_session() {
    start_secure_session();

    $user_id_on_logout = $_SESSION['user_id'] ?? 'N/A';
    log_session_event('SESSION_DESTROY', $user_id_on_logout, 'Session terminated.');

    $_SESSION = array();

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
}

function check_session_timeout() {
    start_secure_session();

    if (!isset($_SESSION['login_time']) || !isset($_SESSION['user_id'])) {
        return;
    }

    $expire_time = $_SESSION['login_time'] + SESSION_TIMEOUT_SECONDS;

    if (time() > $expire_time) {
        log_session_event('SESSION_TIMEOUT', $_SESSION['user_id'], 'Session expired due to inactivity.');
        destroy_session();
        header('Location: ' . LOGIN_PAGE . '?timeout=1');
        exit();
    }
}

?>
<?php

// login.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session_helper.php';

start_secure_session();

$error_message = '';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . DASHBOARD_PAGE);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === 'admin' && $password === 'password123') {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $username;
        $_SESSION['login_time'] = time();

        log_session_event('LOGIN_SUCCESS', $username, 'User logged in successfully.');

        header('Location: ' . DASHBOARD_PAGE);
        exit();
    } else {
        $error_message = 'Invalid username or password.';
        log_session_event('LOGIN_FAILURE', $username, 'Attempted login with invalid credentials.');
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>
    <?php if ($error_message): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['timeout'])): ?>
        <p style="color: blue;">Your session has expired. Please log in again.</p>
    <?php endif; ?>
    <form method="POST" action="login.php">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
<?php

// dashboard.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session_helper.php';

start_secure_session();
check_session_timeout();

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . LOGIN_PAGE);
    exit();
}

$user_id = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome to the Dashboard, <?php echo htmlspecialchars($user_id); ?>!</h2>
    <p>This is your secure content.</p>
    <p>Your session will expire in approximately <?php echo max(0, (($_SESSION['login_time'] + SESSION_TIMEOUT_SECONDS) - time())); ?> seconds.</p>
    <p><a href="logout.php">Logout</a></p>
</body>
</html>
<?php

// logout.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session_helper.php';

destroy_session();

header('Location: ' . LOGIN_PAGE . '?loggedout=1');
exit();

?>