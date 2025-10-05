<?php
define('SESSION_NAME', 'MYAPPSESSID');
define('MAX_SESSION_LIFETIME', 1800);
define('INACTIVITY_TIMEOUT', 600);
define('LOG_DIR', __DIR__ . '/logs/');
define('SESSION_CREATE_LOG', LOG_DIR . 'session_creation.log');
define('SESSION_TERMINATE_LOG', LOG_DIR . 'session_termination.log');

function log_session_event($log_file, $user_id, $event_type) {
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $log_message = sprintf("[%s] User ID: %s - Event: %s - Session ID: %s\n",
        $timestamp,
        htmlspecialchars($user_id),
        htmlspecialchars($event_type),
        session_id()
    );
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

function destroy_session_securely($user_id, $reason) {
    if (isset($_SESSION['user_id'])) {
        log_session_event(SESSION_TERMINATE_LOG, $_SESSION['user_id'], 'Terminated: ' . $reason);
    } else {
        log_session_event(SESSION_TERMINATE_LOG, $user_id ? $user_id : 'UNKNOWN', 'Terminated: ' . $reason . ' (User ID not in session)');
    }

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

function configure_session() {
    ini_set('session.use_trans_sid', 0);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_name(SESSION_NAME);
}

configure_session();
session_start();

$message = '';

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    destroy_session_securely(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A', 'User initiated logout');
    header('Location: session_handler.php?status=loggedout');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === 'admin' && $password === 'password') {
        session_regenerate_id(true);

        $_SESSION['user_id'] = 'admin_user_123';
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        log_session_event(SESSION_CREATE_LOG, $_SESSION['user_id'], 'New session created upon successful login');

        header('Location: dashboard.php');
        exit;
    } else {
        $message = 'Invalid username or password.';
    }
} elseif (isset($_GET['status'])) {
    if ($_GET['status'] == 'loggedout') {
        $message = 'You have been successfully logged out.';
    } elseif ($_GET['status'] == 'timeout') {
        $message = 'Your session has expired due to inactivity or absolute timeout. Please log in again.';
    }
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h2>Login to Dashboard</h2>
    <?php if ($message): ?>
        <p style="color: red;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form action="session_handler.php" method="post">
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div>
            <button type="submit">Login</button>
        </div>
    </form>
</body>
</html>
<?php
// FILE: dashboard.php
?>
<?php
define('SESSION_NAME', 'MYAPPSESSID');
define('MAX_SESSION_LIFETIME', 1800);
define('INACTIVITY_TIMEOUT', 600);
define('LOG_DIR', __DIR__ . '/logs/');
define('SESSION_CREATE_LOG', LOG_DIR . 'session_creation.log');
define('SESSION_TERMINATE_LOG', LOG_DIR . 'session_termination.log');

function log_session_event($log_file, $user_id, $event_type) {
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $log_message = sprintf("[%s] User ID: %s - Event: %s - Session ID: %s\n",
        $timestamp,
        htmlspecialchars($user_id),
        htmlspecialchars($event_type),
        session_id()
    );
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

function destroy_session_securely($user_id, $reason) {
    if (isset($_SESSION['user_id'])) {
        log_session_event(SESSION_TERMINATE_LOG, $_SESSION['user_id'], 'Terminated: ' . $reason);
    } else {
        log_session_event(SESSION_TERMINATE_LOG, $user_id ? $user_id : 'UNKNOWN', 'Terminated: ' . $reason . ' (User ID not in session)');
    }

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

function configure_session() {
    ini_set('session.use_trans_sid', 0);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_name(SESSION_NAME);
}

configure_session();
session_start();

if (!isset($_SESSION['user_id'])) {
    destroy_session_securely('N/A', 'Attempt to access dashboard without active session');
    header('Location: session_handler.php?status=timeout');
    exit;
}

$current_time = time();

if (isset($_SESSION['login_time']) && ($current_time - $_SESSION['login_time']) > MAX_SESSION_LIFETIME) {
    destroy_session_securely($_SESSION['user_id'], 'Absolute session timeout');
    header('Location: session_handler.php?status=timeout');
    exit;
}

if (isset($_SESSION['last_activity']) && ($current_time - $_SESSION['last_activity']) > INACTIVITY_TIMEOUT) {
    destroy_session_securely($_SESSION['user_id'], 'Inactivity session timeout');
    header('Location: session_handler.php?status=timeout');
    exit;
}

$_SESSION['last_activity'] = $current_time;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome to Your Business Dashboard, <?php echo htmlspecialchars($_SESSION['user_id']); ?>!</h2>
    <p>This is your protected content.</p>
    <p>Last activity: <?php echo date('Y-m-d H:i:s', $_SESSION['last_activity']); ?></p>
    <p>Login time: <?php echo date('Y-m-d H:i:s', $_SESSION['login_time']); ?></p>
    <p><a href="session_handler.php?action=logout">Logout</a></p>
    <div>
        <h3>Dashboard Content</h3>
        <p>Here you can display sensitive business data and controls.</p>
        <ul>
            <li>Sales Report</li>
            <li>Inventory Management</li>
            <li>Customer Data</li>
        </ul>
    </div>
</body>
</html>
?>