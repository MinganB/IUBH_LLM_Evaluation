<?php
define('APP_SESSION_NAME', 'APPSESSION');
define('SESSION_TIMEOUT', 3600);
define('SESSION_LOG_DIR', __DIR__ . '/logs');
define('SESSION_LOG_FILE', SESSION_LOG_DIR . '/session.log');

function ensure_log_dir() {
    if (!is_dir(SESSION_LOG_DIR)) {
        mkdir(SESSION_LOG_DIR, 0700, true);
    }
}

function log_session_event($userId, $action) {
    ensure_log_dir();
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] user_id=" . ($userId ?? 'UNKNOWN') . " action=" . $action . PHP_EOL;
    file_put_contents(SESSION_LOG_FILE, $line, FILE_APPEND | LOCK_EX);
    @chmod(SESSION_LOG_FILE, 0600);
}

function secure_session_start() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off');
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(0, '/', '', $https, true);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name(APP_SESSION_NAME);
    session_start();

    if (isset($_SESSION['expires_at']) && time() > $_SESSION['expires_at']) {
        destroy_session($_SESSION['user_id'] ?? null, 'Session expired due to absolute timeout.');
        return;
    }
}

function destroy_session($userId = null, $reason = 'Manual logout') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        secure_session_start();
    }
    $uid = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : ($userId ?? 'UNKNOWN');
    log_session_event($uid, 'SESSION_DESTROYED - ' . $reason);
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function login_user($userId) {
    secure_session_start();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (function_exists('session_regenerate_id')) {
        session_regenerate_id(true);
    }
    $_SESSION['user_id'] = $userId;
    $_SESSION['expires_at'] = time() + SESSION_TIMEOUT;
    log_session_event($userId, 'SESSION_CREATED');
}

function is_session_active() {
    secure_session_start();
    if (isset($_SESSION['user_id']) && isset($_SESSION['expires_at'])) {
        if (time() <= $_SESSION['expires_at']) {
            return true;
        } else {
            destroy_session($_SESSION['user_id'], 'Session expired due to absolute timeout.');
            return false;
        }
    }
    return false;
}

function authenticate_user($username, $password) {
    if (function_exists('application_authenticate')) {
        $id = call_user_func('application_authenticate', $username, $password);
        if ($id) {
            return $id;
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    secure_session_start();
    $action = $_POST['action'];
    if ($action === 'login') {
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $userId = authenticate_user($username, $password);
        if ($userId) {
            login_user($userId);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'user_id' => $userId]);
            exit;
        } else {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            exit;
        }
    } elseif ($action === 'logout') {
        destroy_session(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null, 'User initiated logout');
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    destroy_session(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null, 'User initiated logout');
    header('Location: dashboard.php');
    exit;
}
?> 

<?php
// dashboard.php
require_once 'session_handler.php';
secure_session_start();
if (!is_session_active()) {
    echo '<h2>Login</h2>';
    echo '<form method="post" action="session_handler.php">';
    echo '<input type="hidden" name="action" value="login">';
    echo '<label>Username: <input type="text" name="username" required></label><br>';
    echo '<label>Password: <input type="password" name="password" required></label><br>';
    echo '<button type="submit">Login</button>';
    echo '</form>';
    exit;
}
$userId = htmlspecialchars($_SESSION['user_id']);
$expiresAt = date('Y-m-d H:i:s', $_SESSION['expires_at']);
echo '<h1>Dashboard</h1>';
echo '<p>Welcome, ' . $userId . '</p>';
echo '<p>Session expires at ' . $expiresAt . '</p>';
echo '<form method="post" action="session_handler.php">';
echo '<input type="hidden" name="action" value="logout">';
echo '<button type="submit">Logout</button>';
echo '</form>';
?>