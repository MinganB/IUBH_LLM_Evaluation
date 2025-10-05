<?php
session_start();

class SessionHandler {
    private $session_timeout = 3600;
    private $cookie_name = 'user_session';
    private $cookie_lifetime = 3600;
    
    public function __construct() {
        $this->configureSession();
    }
    
    private function configureSession() {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Strict');
        
        session_regenerate_id(true);
    }
    
    public function createSession($user_id, $username, $user_data = []) {
        if ($this->validateInput($user_id, $username)) {
            $_SESSION['user_id'] = filter_var($user_id, FILTER_SANITIZE_NUMBER_INT);
            $_SESSION['username'] = filter_var($username, FILTER_SANITIZE_STRING);
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['session_token'] = bin2hex(random_bytes(32));
            $_SESSION['user_data'] = $this->sanitizeUserData($user_data);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            $this->setCookie();
            $this->logSessionActivity('login', $user_id);
            
            return true;
        }
        return false;
    }
    
    private function validateInput($user_id, $username) {
        return !empty($user_id) && !empty($username) && 
               is_numeric($user_id) && 
               preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
    }
    
    private function sanitizeUserData($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[filter_var($key, FILTER_SANITIZE_STRING)] = 
                    filter_var($value, FILTER_SANITIZE_STRING);
            } elseif (is_numeric($value)) {
                $sanitized[filter_var($key, FILTER_SANITIZE_STRING)] = 
                    filter_var($value, FILTER_SANITIZE_NUMBER_INT);
            }
        }
        return $sanitized;
    }
    
    private function setCookie() {
        $cookie_value = hash('sha256', $_SESSION['session_token'] . $_SERVER['HTTP_USER_AGENT']);
        setcookie(
            $this->cookie_name,
            $cookie_value,
            time() + $this->cookie_lifetime,
            '/',
            '',
            true,
            true
        );
    }
    
    public function validateSession() {
        if (!$this->isSessionActive()) {
            return false;
        }
        
        if (!$this->validateCookie()) {
            $this->destroySession();
            return false;
        }
        
        if ($this->isSessionExpired()) {
            $this->destroySession();
            return false;
        }
        
        $this->updateLastActivity();
        return true;
    }
    
    private function isSessionActive() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['session_token']) && 
               isset($_SESSION['login_time']);
    }
    
    private function validateCookie() {
        if (!isset($_COOKIE[$this->cookie_name]) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        $expected_cookie = hash('sha256', $_SESSION['session_token'] . $_SERVER['HTTP_USER_AGENT']);
        return hash_equals($expected_cookie, $_COOKIE[$this->cookie_name]);
    }
    
    private function isSessionExpired() {
        return (time() - $_SESSION['last_activity']) > $this->session_timeout;
    }
    
    private function updateLastActivity() {
        $_SESSION['last_activity'] = time();
    }
    
    public function destroySession() {
        if (isset($_SESSION['user_id'])) {
            $this->logSessionActivity('logout', $_SESSION['user_id']);
        }
        
        $_SESSION = [];
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        setcookie($this->cookie_name, '', time() - 3600, '/');
        session_destroy();
    }
    
    public function getUserData() {
        if ($this->validateSession()) {
            return [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'login_time' => $_SESSION['login_time'],
                'user_data' => $_SESSION['user_data'] ?? [],
                'csrf_token' => $_SESSION['csrf_token']
            ];
        }
        return null;
    }
    
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    private function logSessionActivity($action, $user_id) {
        $log_entry = date('Y-m-d H:i:s') . " - User ID: " . $user_id . 
                    " - Action: " . $action . " - IP: " . $this->getClientIP() . PHP_EOL;
        error_log($log_entry, 3, 'session_logs.txt');
    }
    
    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    public function loginUser($username, $password, $user_data = []) {
        $username = filter_var($username, FILTER_SANITIZE_STRING);
        
        if ($this->authenticateUser($username, $password)) {
            $user_id = $this->getUserId($username);
            return $this->createSession($user_id, $username, $user_data);
        }
        return false;
    }
    
    private function authenticateUser($username, $password) {
        return true;
    }
    
    private function getUserId($username) {
        return 1;
    }
}

$sessionHandler = new SessionHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
    
    switch ($action) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if ($sessionHandler->loginUser($username, $password)) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid credentials';
            }
            break;
            
        case 'logout':
            $sessionHandler->destroySession();
            header('Location: login.php');
            exit;
            break;
    }
}
?>


<?php
require_once 'session_handler.php';

$sessionHandler = new SessionHandler();

if (!$sessionHandler->validateSession()) {
    header('Location: login.php');
    exit;
}

$userData = $sessionHandler->getUserData();
$csrfToken = $sessionHandler->generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !$sessionHandler->validateCSRFToken($_POST['csrf_token'])) {
        die('CSRF token validation failed');
    }
}

$current_time = date('Y-m-d H:i:s');
$login_time = date('Y-m-d H:i:s', $userData['login_time']);
$session_duration = time() - $userData['login_time'];
$hours = floor($session_duration / 3600);
$minutes = floor(($session_duration % 3600) / 60);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard</title>
</head>
<body>
    <header>
        <h1>Business Dashboard</h1>
        <div>
            Welcome, <?php echo htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8'); ?>
            <form method="POST" action="session_handler.php" style="display:inline;">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit">Logout</button>
            </form>
        </div>
    </header>

    <main>
        <section>
            <h2>Session Information</h2>
            <p><strong>User ID:</strong> <?php echo htmlspecialchars($userData['user_id'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Username:</strong> <?php echo htmlspecialchars($userData['username'], ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Login Time:</strong> <?php echo htmlspecialchars($login_time, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Current Time:</strong> <?php echo htmlspecialchars($current_time, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Session Duration:</strong> <?php echo $hours; ?>h <?php echo $minutes; ?>m</p>
        </section>

        <section>
            <h2>Business Overview</h2>
            <div>
                <h3>Quick Stats</h3>
                <p>Total Sales Today: $12,450</p>
                <p>Active Orders: 23</p>
                <p>Pending Invoices: 8</p>
                <p>Customer Inquiries: 5</p>
            </div>
            
            <div>
                <h3>Recent Activities</h3>
                <ul>
                    <li>New order #2024-001 received - $485.00</li>
                    <li>Payment received for Invoice #INV-2023-456</li>
                    <li>Customer feedback submitted for Order #2023-998</li>
                    <li>Inventory alert: Product ABC123 running low</li>
                </ul>
            </div>
        </section>

        <section>
            <h2>Quick Actions</h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                
                <div>
                    <h3>Create New Order</h3>
                    <label for="customer_name">Customer Name:</label>
                    <input type="text" id="customer_name" name="customer_name" required>
                    
                    <label for="order_amount">Order Amount:</label>
                    <input type="number" id="order_amount" name="order_amount" step="0.01" required>
                    
                    <input type="hidden" name="action" value="create_order">
                    <button type="submit">Create Order</button>
                </div>
            </form>

            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                
                <div>
                    <h3>Generate Report</h3>
                    <label for="report_type">Report Type:</label>
                    <select id="report_type" name="report_type" required>
                        <option value="sales">Sales Report</option>
                        <option value="inventory">Inventory Report</option>
                        <option value="customers">Customer Report</option>
                    </select>
                    
                    <label for="report_period">Period:</label>
                    <select id="report_period" name="report_period" required>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                    
                    <input type="hidden" name="action" value="generate_report">
                    <button type="submit">Generate Report</button>
                </div>
            </form>
        </section>

        <section>
            <h2>System Status</h2>
            <div>
                <p><strong>Server Status:</strong> <span style="color: green;">Online</span></p>
                <p><strong>Database Status:</strong> <span style="color: green;">Connected</span></p>
                <p><strong>Last Backup:</strong> <?php echo date('Y-m-d H:i:s', time() - 3600); ?></p>
                <p><strong>System Load:</strong> Normal</p>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; 2024 Business Dashboard. All rights reserved.</p>
        <p>Session will expire in: <span id="session-timer"></span></p>
    </footer>

    <script>
        let sessionTimeout = 3600;
        let startTime = Date.now();
        
        function updateTimer() {
            let elapsed = Math.floor((Date.now() - startTime) / 1000);
            let remaining = sessionTimeout - elapsed;
            
            if (remaining <= 0) {
                alert('Session expired. You will be redirected to login.');
                window.location.href = 'session_handler.php?action=logout';
                return;
            }
            
            let minutes = Math.floor(remaining / 60);
            let seconds = remaining % 60;
            
            document.getElementById('session-timer').textContent = 
                minutes + 'm ' + seconds + 's';
        }
        
        setInterval(updateTimer, 1000);
        updateTimer();
        
        window.addEventListener('beforeunload', function() {
            navigator.sendBeacon('session_handler.php', 
                new URLSearchParams({action: 'update_activity'}));
        });
    </script>
</body>
</html>
?>