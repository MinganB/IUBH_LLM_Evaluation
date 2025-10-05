**session_handler.php**

<?php
session_start();

ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.gc_maxlifetime', 3600);

function logSessionEvent($event, $userId = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$event}";
    if ($userId) {
        $logEntry .= " - User ID: {$userId}";
    }
    $logEntry .= " - Session ID: " . session_id() . PHP_EOL;
    file_put_contents('/var/log/sessions.log', $logEntry, FILE_APPEND | LOCK_EX);
}

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = 123;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    setcookie('PHPSESSID', session_id(), [
        'expires' => time() + 3600,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    logSessionEvent('Login successful', $_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful'
    ]);
} elseif ($action === 'logout') {
    $userId = $_SESSION['user_id'] ?? null;
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    logSessionEvent('Logout', $userId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Logout successful'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
}
?>


**dashboard.php**

<?php
session_start();

ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.gc_maxlifetime', 3600);

function logSessionEvent($event, $userId = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$event}";
    if ($userId) {
        $logEntry .= " - User ID: {$userId}";
    }
    $logEntry .= " - Session ID: " . session_id() . PHP_EOL;
    file_put_contents('/var/log/sessions.log', $logEntry, FILE_APPEND | LOCK_EX);
}

function destroySession($reason, $userId = null) {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    logSessionEvent($reason, $userId);
}

if (!isset($_SESSION['user_id'])) {
    logSessionEvent('Unauthorized access attempt');
    header('Location: login.php');
    exit();
}

$currentTime = time();
$sessionTimeout = 3600;
$inactivityTimeout = 1800;

if (isset($_SESSION['login_time']) && ($currentTime - $_SESSION['login_time']) > $sessionTimeout) {
    destroySession('Session timeout', $_SESSION['user_id']);
    header('Location: login.php');
    exit();
}

if (isset($_SESSION['last_activity']) && ($currentTime - $_SESSION['last_activity']) > $inactivityTimeout) {
    destroySession('Inactivity timeout', $_SESSION['user_id']);
    header('Location: login.php');
    exit();
}

$_SESSION['last_activity'] = $currentTime;

$userId = $_SESSION['user_id'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome to Dashboard</h1>
    <p>Hello User! Your User ID is: <?php echo htmlspecialchars($userId); ?></p>
    <p>Session started at: <?php echo date('Y-m-d H:i:s', $_SESSION['login_time']); ?></p>
    <p>Last activity: <?php echo date('Y-m-d H:i:s', $_SESSION['last_activity']); ?></p>
    
    <form method="post" action="session_handler.php">
        <input type="hidden" name="action" value="logout">
        <button type="submit">Logout</button>
    </form>
</body>
</html>