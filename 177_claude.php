session_handler.php
<?php
class SessionHandler {
    private $session_timeout = 3600;
    private $log_file = 'session_log.txt';
    
    public function __construct() {
        $this->configureSession();
    }
    
    private function configureSession() {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_name('BUSINESS_SESSION');
    }
    
    public function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function login($user_id, $username) {
        $this->startSession();
        
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        $this->logEvent('LOGIN', $user_id);
        
        return true;
    }
    
    public function validateSession() {
        $this->startSession();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        if (time() - $_SESSION['login_time'] > $this->session_timeout) {
            $this->logout('TIMEOUT');
            return false;
        }
        
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
            $this->logout('INACTIVITY');
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function logout($reason = 'USER_LOGOUT') {
        $this->startSession();
        
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'UNKNOWN';
        
        session_unset();
        session_destroy();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/', '', true, true);
        }
        
        $this->logEvent('LOGOUT_' . $reason, $user_id);
    }
    
    public function getCurrentUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    public function getCurrentUsername() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }
    
    private function logEvent($event, $user_id) {
        $timestamp = date('Y-m-d H:i:s');
        $session_id = session_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN';
        
        $log_entry = sprintf(
            "[%s] EVENT: %s | USER_ID: %s | SESSION_ID: %s | IP: %s | USER_AGENT: %s\n",
            $timestamp,
            $event,
            $user_id,
            $session_id,
            $ip_address,
            $user_agent
        );
        
        file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
}

$sessionHandler = new SessionHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'login':
            if (isset($_POST['username']) && isset($_POST['password'])) {
                $username = $_POST['username'];
                $password = $_POST['password'];
                
                if ($username === 'admin' && $password === 'password123') {
                    $user_id = 1;
                    $sessionHandler->login($user_id, $username);
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid credentials';
                }
            }
            break;
            
        case 'logout':
            $sessionHandler->logout('USER_LOGOUT');
            header('Location: login.php');
            exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Business Login</title>
</head>
<body>
    <h2>Business Dashboard Login</h2>
    
    <?php if (isset($error)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <form method="POST" action="">
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


dashboard.php
<?php
require_once 'session_handler.php';

$sessionHandler = new SessionHandler();

if (!$sessionHandler->validateSession()) {
    header('Location: session_handler.php');
    exit;
}

$user_id = $sessionHandler->getCurrentUserId();
$username = $sessionHandler->getCurrentUsername();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Business Dashboard</title>
</head>
<body>
    <header>
        <h1>Business Dashboard</h1>
        <div>
            Welcome, <?php echo htmlspecialchars($username); ?> (ID: <?php echo htmlspecialchars($user_id); ?>)
            <form method="POST" action="session_handler.php" style="display: inline;">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Logout</button>
            </form>
        </div>
    </header>
    
    <main>
        <h2>Dashboard Content</h2>
        
        <section>
            <h3>Business Overview</h3>
            <p>Revenue this month: $45,230</p>
            <p>Total customers: 1,247</p>
            <p>Orders pending: 23</p>
        </section>
        
        <section>
            <h3>Quick Actions</h3>
            <button>View Reports</button>
            <button>Manage Inventory</button>
            <button>Customer Service</button>
            <button>Financial Summary</button>
        </section>
        
        <section>
            <h3>Recent Activity</h3>
            <ul>
                <li>Order #1234 completed</li>
                <li>New customer registration: John Doe</li>
                <li>Inventory alert: Low stock on Product A</li>
                <li>Payment received: $1,500</li>
            </ul>
        </section>
        
        <section>
            <h3>System Information</h3>
            <p>Last login: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p>Session expires in: <?php echo '60 minutes'; ?></p>
        </section>
    </main>
</body>
</html>