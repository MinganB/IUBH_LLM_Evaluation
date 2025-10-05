<?php
// session_handler.php

class SessionHandler {
    private $sessionTimeout = 3600; // 1 hour in seconds
    private $logFile = '/var/log/secure/session.log';
    
    public function __construct() {
        $this->configureSession();
    }
    
    private function configureSession() {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', $this->sessionTimeout);
        
        session_start();
    }
    
    public function createSession($userId) {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['created_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
        
        $this->logEvent('SESSION_CREATED', $userId);
        
        return session_id();
    }
    
    public function validateSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['created_time']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        $currentTime = time();
        
        if (($currentTime - $_SESSION['created_time']) > $this->sessionTimeout) {
            $this->destroySession('TIMEOUT');
            return false;
        }
        
        if (($currentTime - $_SESSION['last_activity']) > 1800) {
            $this->destroySession('INACTIVITY');
            return false;
        }
        
        $_SESSION['last_activity'] = $currentTime;
        return true;
    }
    
    public function destroySession($reason = 'LOGOUT') {
        $userId = $_SESSION['user_id'] ?? 'UNKNOWN';
        
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        
        $this->logEvent('SESSION_DESTROYED_' . $reason, $userId);
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    private function logEvent($event, $userId) {
        $timestamp = date('Y-m-d H:i:s');
        $sessionId = session_id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        
        $logEntry = sprintf(
            "[%s] %s - User ID: %s, Session ID: %s, IP: %s\n",
            $timestamp,
            $event,
            $userId,
            $sessionId,
            $ip
        );
        
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0750, true);
        }
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function login($username, $password) {
        $userId = $this->authenticateUser($username, $password);
        
        if ($userId) {
            $this->createSession($userId);
            return true;
        }
        
        return false;
    }
    
    private function authenticateUser($username, $password) {
        $users = [
            'admin' => password_hash('admin123', PASSWORD_DEFAULT),
            'user1' => password_hash('password123', PASSWORD_DEFAULT)
        ];
        
        if (isset($users[$username]) && password_verify($password, $users[$username])) {
            return $username;
        }
        
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionHandler = new SessionHandler();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                if (isset($_POST['username']) && isset($_POST['password'])) {
                    if ($sessionHandler->login($_POST['username'], $_POST['password'])) {
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = 'Invalid credentials';
                    }
                }
                break;
                
            case 'logout':
                $sessionHandler->destroySession('LOGOUT');
                header('Location: login.php');
                exit;
                break;
        }
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
    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="action" value="login">
        <div>
            <label>Username:</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>
</body>
</html>


<?php
// dashboard.php

require_once 'session_handler.php';

$sessionHandler = new SessionHandler();

if (!$sessionHandler->validateSession()) {
    header('Location: session_handler.php');
    exit;
}

$userId = $sessionHandler->getUserId();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome to Dashboard</h1>
    <p>Hello, <?php echo htmlspecialchars($userId); ?>!</p>
    <p>Session is active and valid.</p>
    <p>Last activity: <?php echo date('Y-m-d H:i:s', $_SESSION['last_activity']); ?></p>
    
    <h3>Dashboard Content</h3>
    <ul>
        <li>User Profile</li>
        <li>Settings</li>
        <li>Reports</li>
        <li>Analytics</li>
    </ul>
    
    <form method="POST" action="session_handler.php">
        <input type="hidden" name="action" value="logout">
        <button type="submit">Logout</button>
    </form>
    
    <script>
        setTimeout(function() {
            if (confirm('Your session will expire soon. Do you want to continue?')) {
                window.location.reload();
            } else {
                window.location.href = 'session_handler.php';
            }
        }, 1500000);
    </script>
</body>
</html>
?>