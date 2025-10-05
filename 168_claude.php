<?php
class SessionManager {
    private $logFile;
    private $sessionTimeout;
    
    public function __construct($logFile = '/var/log/secure/session.log', $sessionTimeout = 3600) {
        $this->logFile = $logFile;
        $this->sessionTimeout = $sessionTimeout;
        
        if (session_status() == PHP_SESSION_NONE) {
            $this->configureSession();
            session_start();
        }
    }
    
    private function configureSession() {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    
    public function createSession($userId) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $this->logEvent('SESSION_CREATED', $userId);
        return session_id();
    }
    
    public function validateSession() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $currentTime = time();
        
        if (!isset($_SESSION['login_time']) || 
            ($currentTime - $_SESSION['login_time']) > $this->sessionTimeout) {
            $this->destroySession('TIMEOUT');
            return false;
        }
        
        if (!isset($_SESSION['last_activity']) || 
            ($currentTime - $_SESSION['last_activity']) > 900) {
            $this->destroySession('INACTIVITY');
            return false;
        }
        
        $_SESSION['last_activity'] = $currentTime;
        return true;
    }
    
    public function destroySession($reason = 'LOGOUT') {
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'UNKNOWN';
        
        session_unset();
        session_destroy();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        $this->logEvent('SESSION_DESTROYED_' . $reason, $userId);
    }
    
    public function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    private function logEvent($event, $userId) {
        $timestamp = date('Y-m-d H:i:s');
        $sessionId = session_id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        
        $logEntry = sprintf(
            "[%s] %s - User: %s, Session: %s, IP: %s, UserAgent: %s\n",
            $timestamp,
            $event,
            $userId,
            $sessionId,
            $ipAddress,
            $userAgent
        );
        
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        chmod($this->logFile, 0640);
    }
}
?>


<?php
class UserAuth {
    private $sessionManager;
    private $users;
    
    public function __construct() {
        $this->sessionManager = new SessionManager();
        $this->users = [
            'admin' => password_hash('admin123', PASSWORD_DEFAULT),
            'user1' => password_hash('password123', PASSWORD_DEFAULT),
            'manager' => password_hash('manager456', PASSWORD_DEFAULT)
        ];
    }
    
    public function login($username, $password) {
        if (isset($this->users[$username]) && 
            password_verify($password, $this->users[$username])) {
            $this->sessionManager->createSession($username);
            return true;
        }
        return false;
    }
    
    public function logout() {
        $this->sessionManager->destroySession('LOGOUT');
    }
    
    public function isLoggedIn() {
        return $this->sessionManager->validateSession();
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return $this->sessionManager->getUserId();
        }
        return null;
    }
}
?>


<?php
require_once 'SessionManager.php';
require_once 'UserAuth.php';

$auth = new UserAuth();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($auth->login($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Business Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div>
        <h1>Business Dashboard Login</h1>
        <?php if ($error): ?>
            <div style="color: red; margin-bottom: 15px;">
                <?php echo htmlspecialchars($error); ?>
            </div>
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
        
        <div>
            <h3>Demo Credentials:</h3>
            <p>Username: admin, Password: admin123</p>
            <p>Username: user1, Password: password123</p>
            <p>Username: manager, Password: manager456</p>
        </div>
    </div>
</body>
</html>


<?php
require_once 'SessionManager.php';
require_once 'UserAuth.php';

$auth = new UserAuth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (isset($_POST['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}

$currentUser = $auth->getCurrentUser();
$loginTime = isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'Unknown';
$lastActivity = isset($_SESSION['last_activity']) ? date('Y-m-d H:i:s', $_SESSION['last_activity']) : 'Unknown';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Business Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300">
</head>
<body>
    <div>
        <header>
            <h1>Business Dashboard</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($currentUser); ?>!</span>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="logout">Logout</button>
                </form>
            </div>
        </header>
        
        <main>
            <section>
                <h2>Session Information</h2>
                <table border="1">
                    <tr>
                        <td>User ID</td>
                        <td><?php echo htmlspecialchars($currentUser); ?></td>
                    </tr>
                    <tr>
                        <td>Login Time</td>
                        <td><?php echo htmlspecialchars($loginTime); ?></td>
                    </tr>
                    <tr>
                        <td>Last Activity</td>
                        <td><?php echo htmlspecialchars($lastActivity); ?></td>
                    </tr>
                    <tr>
                        <td>Session ID</td>
                        <td><?php echo htmlspecialchars(session_id()); ?></td>
                    </tr>
                </table>
            </section>
            
            <section>
                <h2>Dashboard Content</h2>
                <div>
                    <h3>Sales Overview</h3>
                    <p>Monthly Revenue: $45,230</p>
                    <p>Total Orders: 156</p>
                    <p>Customer Growth: +12%</p>
                </div>
                
                <div>
                    <h3>Recent Activities</h3>
                    <ul>
                        <li>New order received from Customer #1234</li>
                        <li>Inventory updated for Product SKU-789</li>
                        <li>Monthly report generated</li>
                        <li>Customer service ticket resolved</li>
                    </ul>
                </div>
                
                <div>
                    <h3>System Status</h3>
                    <p>Server Status: Online</p>
                    <p>Database Status: Connected</p>
                    <p>Last Backup: <?php echo date('Y-m-d H:i:s', time() - 3600); ?></p>
                </div>
            </section>
        </main>
    </div>
    
    <script>
        setTimeout(function() {
            if (confirm('Your session is about to expire. Do you want to continue?')) {
                location.reload();
            } else {
                location.href = 'login.php';
            }
        }, 870000);
    </script>
</body>
</html>


<?php
require_once 'SessionManager.php';
require_once 'UserAuth.php';

header('Content-Type: application/json');

$auth = new UserAuth();

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$response = [
    'status' => 'active',
    'user' => $auth->getCurrentUser(),
    'timestamp' => time()
];

echo json_encode($response);
?>