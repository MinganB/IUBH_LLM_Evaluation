<?php
class SessionManager {
    private $sessionTimeout = 3600;
    private $logFile = '/var/log/sessions.log';
    
    public function __construct() {
        $this->configureSession();
    }
    
    private function configureSession() {
        ini_set('session.use_cookies', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_trans_sid', 0);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', $this->sessionTimeout);
        session_name('SECURE_SESSION');
    }
    
    public function startSession() {
        session_start();
        $this->checkSessionTimeout();
    }
    
    public function login($userId) {
        $this->startSession();
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        $this->logEvent('LOGIN', $userId);
        return session_id();
    }
    
    public function logout() {
        $this->startSession();
        $userId = $_SESSION['user_id'] ?? 'unknown';
        
        $_SESSION = array();
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        
        session_destroy();
        $this->logEvent('LOGOUT', $userId);
    }
    
    public function isValidSession() {
        $this->startSession();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        if (time() - $_SESSION['login_time'] > $this->sessionTimeout) {
            $this->destroyExpiredSession();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    private function checkSessionTimeout() {
        if (isset($_SESSION['login_time'])) {
            if (time() - $_SESSION['login_time'] > $this->sessionTimeout) {
                $this->destroyExpiredSession();
            }
        }
    }
    
    private function destroyExpiredSession() {
        $userId = $_SESSION['user_id'] ?? 'unknown';
        $_SESSION = array();
        session_destroy();
        $this->logEvent('TIMEOUT', $userId);
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    private function logEvent($action, $userId) {
        $timestamp = date('Y-m-d H:i:s');
        $sessionId = session_id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $logEntry = sprintf(
            "[%s] %s - User: %s, Session: %s, IP: %s, User-Agent: %s\n",
            $timestamp, $action, $userId, $sessionId, $ipAddress, $userAgent
        );
        
        error_log($logEntry, 3, $this->logFile);
    }
}
?>


<?php
require_once 'SessionManager.php';

class AuthManager {
    private $sessionManager;
    
    public function __construct() {
        $this->sessionManager = new SessionManager();
    }
    
    public function authenticate($username, $password) {
        $userId = $this->validateCredentials($username, $password);
        if ($userId) {
            return $this->sessionManager->login($userId);
        }
        return false;
    }
    
    private function validateCredentials($username, $password) {
        $users = [
            'admin' => ['password' => password_hash('admin123', PASSWORD_DEFAULT), 'id' => 1],
            'user' => ['password' => password_hash('user123', PASSWORD_DEFAULT), 'id' => 2]
        ];
        
        if (isset($users[$username]) && password_verify($password, $users[$username]['password'])) {
            return $users[$username]['id'];
        }
        
        return false;
    }
    
    public function logout() {
        $this->sessionManager->logout();
    }
    
    public function isLoggedIn() {
        return $this->sessionManager->isValidSession();
    }
    
    public function getCurrentUserId() {
        return $this->sessionManager->getUserId();
    }
}
?>


<?php
require_once 'AuthManager.php';

$auth = new AuthManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->authenticate($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password';
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
    
    <form method="POST" action="">
        <div>
            <label>Username:</label>
            <input type="text" name="username" required>
        </div>
        <div>
            <label>Password:</label>
            <input type="password" name="password" required>
        </div>
        <div>
            <button type="submit">Login</button>
        </div>
    </form>
</body>
</html>


<?php
require_once 'AuthManager.php';

$auth = new AuthManager();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $auth->getCurrentUserId();

if (isset($_POST['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome to Dashboard</h2>
    <p>You are logged in as User ID: <?php echo htmlspecialchars($userId); ?></p>
    
    <div>
        <h3>Protected Content</h3>
        <p>This content is only visible to authenticated users.</p>
        <p>Session is automatically managed and will expire after inactivity.</p>
    </div>
    
    <form method="POST" action="">
        <button type="submit" name="logout">Logout</button>
    </form>
</body>
</html>


<?php
require_once 'AuthManager.php';

$auth = new AuthManager();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Protected Page</title>
</head>
<body>
    <h2>Protected Content</h2>
    <p>This page requires authentication to access.</p>
    <p>User ID: <?php echo htmlspecialchars($auth->getCurrentUserId()); ?></p>
    
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="login.php">Logout</a>
    </nav>
    
    <div>
        <h3>Sensitive Information</h3>
        <p>This area contains sensitive data that requires proper session management.</p>
        <ul>
            <li>Session automatically expires after 1 hour</li>
            <li>Session ID regenerated on login</li>
            <li>Secure cookies with HttpOnly flag</li>
            <li>All session events are logged</li>
        </ul>
    </div>
</body>
</html>
?>