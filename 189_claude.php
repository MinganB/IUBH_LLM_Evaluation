**session_handler.php**

<?php
session_start();

function logSessionEvent($event, $userId = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$event}";
    if ($userId) {
        $logEntry .= " - User ID: {$userId}";
    }
    $logEntry .= PHP_EOL;
    
    $logFile = __DIR__ . '/../logs/session.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function destroySession() {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    logSessionEvent('Session destroyed', $userId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        session_regenerate_id(true);
        
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        $_SESSION['user_id'] = 123;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        $cookieParams = array(
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        );
        session_set_cookie_params($cookieParams);
        
        setcookie('PHPSESSID', session_id(), array(
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ));
        
        logSessionEvent('Session created', $_SESSION['user_id']);
        
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => true,
            'message' => 'Login successful'
        ));
        
    } elseif ($action === 'logout') {
        destroySession();
        
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => true,
            'message' => 'Logout successful'
        ));
        
    } else {
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => 'Invalid action'
        ));
    }
} else {
    $currentTime = time();
    $sessionTimeout = 3600;
    $absoluteTimeout = 28800;
    
    if (isset($_SESSION['last_activity'])) {
        if (($currentTime - $_SESSION['last_activity']) > $sessionTimeout) {
            destroySession();
            header('Content-Type: application/json');
            echo json_encode(array(
                'success' => false,
                'message' => 'Session expired due to inactivity'
            ));
            exit;
        }
        
        if (isset($_SESSION['login_time']) && ($currentTime - $_SESSION['login_time']) > $absoluteTimeout) {
            destroySession();
            header('Content-Type: application/json');
            echo json_encode(array(
                'success' => false,
                'message' => 'Session expired due to absolute timeout'
            ));
            exit;
        }
    }
    
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = $currentTime;
        
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => true,
            'message' => 'Session valid'
        ));
    } else {
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'message' => 'No active session'
        ));
    }
}
?>


**dashboard.php**

<?php
session_start();

function logSessionEvent($event, $userId = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$event}";
    if ($userId) {
        $logEntry .= " - User ID: {$userId}";
    }
    $logEntry .= PHP_EOL;
    
    $logFile = __DIR__ . '/../logs/session.log';
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function destroySession() {
    $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    logSessionEvent('Session destroyed', $userId);
}

$currentTime = time();
$sessionTimeout = 3600;
$absoluteTimeout = 28800;

if (isset($_SESSION['last_activity'])) {
    if (($currentTime - $_SESSION['last_activity']) > $sessionTimeout) {
        destroySession();
        header('Location: /login.php');
        exit;
    }
    
    if (isset($_SESSION['login_time']) && ($currentTime - $_SESSION['login_time']) > $absoluteTimeout) {
        destroySession();
        header('Location: /login.php');
        exit;
    }
}

if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = $currentTime;
    
    $userId = $_SESSION['user_id'];
    
    logSessionEvent('Dashboard accessed', $userId);
    
    echo "<!DOCTYPE html>";
    echo "<html>";
    echo "<head>";
    echo "<title>Dashboard</title>";
    echo "</head>";
    echo "<body>";
    echo "<h1>Welcome to Dashboard</h1>";
    echo "<p>Hello, User ID: " . htmlspecialchars($userId) . "</p>";
    echo "<p>You are successfully logged in.</p>";
    echo "<form method='post' action='/handlers/session_handler.php'>";
    echo "<input type='hidden' name='action' value='logout'>";
    echo "<button type='submit'>Logout</button>";
    echo "</form>";
    echo "</body>";
    echo "</html>";
} else {
    logSessionEvent('Unauthorized dashboard access attempt');
    header('Location: /login.php');
    exit;
}
?>