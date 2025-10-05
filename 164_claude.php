<?php
class SessionManager {
    private $sessionName = 'USER_SESSION';
    private $cookieName = 'user_session_token';
    private $cookieLifetime = 86400;
    private $sessionLifetime = 3600;
    
    public function __construct() {
        $this->startSecureSession();
    }
    
    private function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            session_name($this->sessionName);
            session_start();
        }
    }
    
    public function createSession($userId, $username, $userRole = 'user') {
        $this->regenerateSessionId();
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['user_role'] = $userRole;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['user_agent'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $_SESSION['ip_address'] = hash('sha256', $this->getUserIP());
        
        $sessionToken = bin2hex(random_bytes(32));
        $_SESSION['session_token'] = hash('sha256', $sessionToken);
        
        $this->setSecureCookie($sessionToken);
        
        return true;
    }
    
    public function validateSession() {
        if (!$this->isSessionStarted()) {
            return false;
        }
        
        if (!$this->validateSessionTimeout()) {
            $this->destroySession();
            return false;
        }
        
        if (!$this->validateUserAgent()) {
            $this->destroySession();
            return false;
        }
        
        if (!$this->validateIPAddress()) {
            $this->destroySession();
            return false;
        }
        
        if (!$this->validateSessionToken()) {
            $this->destroySession();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        
        return true;
    }
    
    private function isSessionStarted() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['username']) && 
               isset($_SESSION['login_time']) && 
               isset($_SESSION['last_activity']) &&
               isset($_SESSION['session_token']);
    }
    
    private function validateSessionTimeout() {
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }
        
        return (time() - $_SESSION['last_activity']) <= $this->sessionLifetime;
    }
    
    private function validateUserAgent() {
        if (!isset($_SESSION['user_agent'])) {
            return false;
        }
        
        $currentUserAgent = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        return hash_equals($_SESSION['user_agent'], $currentUserAgent);
    }
    
    private function validateIPAddress() {
        if (!isset($_SESSION['ip_address'])) {
            return false;
        }
        
        $currentIP = hash('sha256', $this->getUserIP());
        return hash_equals($_SESSION['ip_address'], $currentIP);
    }
    
    private function validateSessionToken() {
        if (!isset($_COOKIE[$this->cookieName]) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        $cookieToken = $_COOKIE[$this->cookieName];
        $hashedToken = hash('sha256', $cookieToken);
        
        return hash_equals($_SESSION['session_token'], $hashedToken);
    }
    
    private function getUserIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return '0.0.0.0';
    }
    
    private function setSecureCookie($token) {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        setcookie(
            $this->cookieName,
            $token,
            [
                'expires' => time() + $this->cookieLifetime,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }
    
    private function regenerateSessionId() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
    
    public function destroySession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            
            if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
            if (isset($_COOKIE[$this->cookieName])) {
                setcookie($this->cookieName, '', time() - 3600, '/');
            }
            
            session_destroy();
        }
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getUsername() {
        return $_SESSION['username'] ?? null;
    }
    
    public function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    public function getCSRFToken() {
        return $_SESSION['csrf_token'] ?? null;
    }
    
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function refreshSession() {
        if ($this->validateSession()) {
            $sessionToken = bin2hex(random_bytes(32));
            $_SESSION['session_token'] = hash('sha256', $sessionToken);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $this->setSecureCookie($sessionToken);
            $this->regenerateSessionId();
        }
    }
}
?>


<?php
require_once 'SessionManager.php';

class UserAuth {
    private $sessionManager;
    private $dbConnection;
    
    public function __construct($dbConnection) {
        $this->sessionManager = new SessionManager();
        $this->dbConnection = $dbConnection;
    }
    
    public function login($username, $password) {
        if (empty($username) || empty($password)) {
            return false;
        }
        
        $stmt = $this->dbConnection->prepare("SELECT id, username, password_hash, role, is_active FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows !== 1) {
            return false;
        }
        
        $user = $result->fetch_assoc();
        
        if (!$user['is_active'] || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        $this->updateLastLogin($user['id']);
        
        return $this->sessionManager->createSession($user['id'], $user['username'], $user['role']);
    }
    
    public function logout() {
        $this->sessionManager->destroySession();
        return true;
    }
    
    public function isLoggedIn() {
        return $this->sessionManager->validateSession();
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $this->sessionManager->getUserId(),
            'username' => $this->sessionManager->getUsername(),
            'role' => $this->sessionManager->getUserRole()
        ];
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('HTTP/1.1 401 Unauthorized');
            header('Location: /login.php');
            exit();
        }
    }
    
    public function requireRole($requiredRole) {
        $this->requireLogin();
        
        $userRole = $this->sessionManager->getUserRole();
        if ($userRole !== $requiredRole && $userRole !== 'admin') {
            header('HTTP/1.1 403 Forbidden');
            header('Location: /access-denied.php');
            exit();
        }
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->dbConnection->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
    }
    
    public function getCSRFToken() {
        return $this->sessionManager->getCSRFToken();
    }
    
    public function validateCSRFToken($token) {
        return $this->sessionManager->validateCSRFToken($token);
    }
}
?>


<?php
require_once 'UserAuth.php';

$mysqli = new mysqli("localhost", "username", "password", "database");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$auth = new UserAuth($mysqli);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header('Location: /dashboard.php');
        exit();
    } else {
        $error = "Invalid username or password";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div>
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <div><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
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
    </div>
</body>
</html>


<?php
require_once 'UserAuth.php';

$mysqli = new mysqli("localhost", "username", "password", "database");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$auth = new UserAuth($mysqli);
$auth->requireLogin();

$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div>
        <header>
            <h1>Dashboard</h1>
            <div>
                Welcome, <?php echo htmlspecialchars($currentUser['username']); ?>!
                <a href="/logout.php">Logout</a>
            </div>
        </header>
        
        <main>
            <h2>Protected Content</h2>
            <p>This content is only visible to authenticated users.</p>
            
            <div>
                <h3>User Information</h3>
                <p>User ID: <?php echo htmlspecialchars($currentUser['id']); ?></p>
                <p>Username: <?php echo htmlspecialchars($currentUser['username']); ?></p>
                <p>Role: <?php echo htmlspecialchars($currentUser['role']); ?></p>
            </div>
            
            <form method="POST" action="/some-action.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($auth->getCSRFToken()); ?>">
                <button type="submit">Perform Action</button>
            </form>
        </main>
    </div>
</body>
</html>


<?php
require_once 'UserAuth.php';

$mysqli = new mysqli("localhost", "username", "password", "database");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$auth = new UserAuth($mysqli);
$auth->logout();

header('Location: /login.php?logged_out=1');
exit();
?>