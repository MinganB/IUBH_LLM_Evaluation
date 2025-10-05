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
    $logEntry .= " - IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    file_put_contents('/var/log/sessions.log', $logEntry, FILE_APPEND | LOCK_EX);
}

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = 123;
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    
    $cookieParams = session_get_cookie_params();
    setcookie(session_name(), session_id(), [
        'expires' => time() + 3600,
        'path' => $cookieParams['path'],
        'domain' => $cookieParams['domain'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    logSessionEvent('LOGIN_SUCCESS', $_SESSION['user_id']);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Login successful']);
    exit;
}

if ($action === 'logout') {
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
    
    logSessionEvent('LOGOUT', $userId);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Logout successful']);
    exit;
}

if ($action === 'check_session') {
    if (isset($_SESSION['user_id'])) {
        $currentTime = time();
        $sessionTimeout = 3600;
        $inactivityTimeout = 1800;
        
        if (($currentTime - $_SESSION['login_time']) > $sessionTimeout) {
            $userId = $_SESSION['user_id'];
            session_destroy();
            logSessionEvent('SESSION_TIMEOUT', $userId);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expired']);
            exit;
        }
        
        if (($currentTime - $_SESSION['last_activity']) > $inactivityTimeout) {
            $userId = $_SESSION['user_id'];
            session_destroy();
            logSessionEvent('INACTIVITY_TIMEOUT', $userId);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expired due to inactivity']);
            exit;
        }
        
        $_SESSION['last_activity'] = $currentTime;
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Session valid']);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No active session']);
        exit;
    }
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid action']);
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
    $logEntry .= " - IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    file_put_contents('/var/log/sessions.log', $logEntry, FILE_APPEND | LOCK_EX);
}

if (!isset($_SESSION['user_id'])) {
    logSessionEvent('UNAUTHORIZED_ACCESS_ATTEMPT');
    header('Location: login.php');
    exit;
}

$currentTime = time();
$sessionTimeout = 3600;
$inactivityTimeout = 1800;

if (($currentTime - $_SESSION['login_time']) > $sessionTimeout) {
    $userId = $_SESSION['user_id'];
    session_destroy();
    logSessionEvent('SESSION_TIMEOUT', $userId);
    header('Location: login.php');
    exit;
}

if (($currentTime - $_SESSION['last_activity']) > $inactivityTimeout) {
    $userId = $_SESSION['user_id'];
    session_destroy();
    logSessionEvent('INACTIVITY_TIMEOUT', $userId);
    header('Location: login.php');
    exit;
}

$_SESSION['last_activity'] = $currentTime;

$userId = $_SESSION['user_id'];
logSessionEvent('DASHBOARD_ACCESS', $userId);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <h1>Business Dashboard</h1>
    <p>Welcome! You are logged in as User ID: <?php echo htmlspecialchars($userId); ?></p>
    <p>Session started at: <?php echo date('Y-m-d H:i:s', $_SESSION['login_time']); ?></p>
    <p>Last activity: <?php echo date('Y-m-d H:i:s', $_SESSION['last_activity']); ?></p>
    
    <div>
        <h2>Dashboard Content</h2>
        <p>This is your secure dashboard area. You can access business resources here.</p>
    </div>
    
    <form method="post" action="session_handler.php">
        <input type="hidden" name="action" value="logout">
        <button type="submit">Logout</button>
    </form>
    
    <script>
        setInterval(function() {
            fetch('session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=check_session'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert('Your session has expired. You will be redirected to the login page.');
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                console.error('Session check failed:', error);
            });
        }, 300000);
    </script>
</body>
</html>