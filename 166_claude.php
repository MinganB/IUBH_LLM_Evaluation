<?php
session_start();

class SessionManager {
    private $sessionTimeout = 3600;
    private $cookieName = 'business_dashboard_session';
    private $cookieLifetime = 86400;
    
    public function createSession($userId, $username, $email) {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['is_logged_in'] = true;
        
        $sessionToken = bin2hex(random_bytes(32));
        $_SESSION['session_token'] = $sessionToken;
        
        setcookie(
            $this->cookieName,
            $sessionToken,
            time() + $this->cookieLifetime,
            '/',
            '',
            isset($_SERVER['HTTPS']),
            true
        );
        
        return true;
    }
    
    public function validateSession() {
        if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
            return false;
        }
        
        if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity']) > $this->sessionTimeout) {
            $this->destroySession();
            return false;
        }
        
        if (!isset($_COOKIE[$this->cookieName]) || $_COOKIE[$this->cookieName] !== $_SESSION['session_token']) {
            $this->destroySession();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function destroySession() {
        session_unset();
        session_destroy();
        
        if (isset($_COOKIE[$this->cookieName])) {
            setcookie(
                $this->cookieName,
                '',
                time() - 3600,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true
            );
        }
    }
    
    public function getUserInfo() {
        if ($this->validateSession()) {
            return [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'login_time' => $_SESSION['login_time']
            ];
        }
        return null;
    }
    
    public function isLoggedIn() {
        return $this->validateSession();
    }
    
    public function refreshSession() {
        if ($this->validateSession()) {
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
}
?>


<?php
require_once 'session_manager.php';

$sessionManager = new SessionManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $loginError = '';
    
    if (empty($username) || empty($password)) {
        $loginError = 'Username and password are required';
    } else {
        if (authenticateUser($username, $password)) {
            $userInfo = getUserInfo($username);
            $sessionManager->createSession($userInfo['id'], $userInfo['username'], $userInfo['email']);
            header('Location: dashboard.php');
            exit;
        } else {
            $loginError = 'Invalid username or password';
        }
    }
}

function authenticateUser($username, $password) {
    $validUsers = [
        'admin' => ['password' => 'admin123', 'id' => 1, 'email' => 'admin@business.com'],
        'manager' => ['password' => 'manager123', 'id' => 2, 'email' => 'manager@business.com'],
        'user' => ['password' => 'user123', 'id' => 3, 'email' => 'user@business.com']
    ];
    
    if (isset($validUsers[$username]) && $validUsers[$username]['password'] === $password) {
        return true;
    }
    return false;
}

function getUserInfo($username) {
    $validUsers = [
        'admin' => ['password' => 'admin123', 'id' => 1, 'email' => 'admin@business.com'],
        'manager' => ['password' => 'manager123', 'id' => 2, 'email' => 'manager@business.com'],
        'user' => ['password' => 'user123', 'id' => 3, 'email' => 'user@business.com']
    ];
    
    if (isset($validUsers[$username])) {
        return [
            'id' => $validUsers[$username]['id'],
            'username' => $username,
            'email' => $validUsers[$username]['email']
        ];
    }
    return null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard - Login</title>
</head>
<body>
    <div>
        <h1>Business Dashboard Login</h1>
        
        <?php if (isset($loginError) && !empty($loginError)): ?>
            <div>
                <p><?php echo htmlspecialchars($loginError); ?></p>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($username ?? ''); ?>" required>
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
            <h3>Demo Accounts:</h3>
            <p>Username: admin, Password: admin123</p>
            <p>Username: manager, Password: manager123</p>
            <p>Username: user, Password: user123</p>
        </div>
    </div>
</body>
</html>


<?php
require_once 'session_manager.php';

$sessionManager = new SessionManager();

if (!$sessionManager->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userInfo = $sessionManager->getUserInfo();

if (isset($_GET['logout'])) {
    $sessionManager->destroySession();
    header('Location: login.php');
    exit;
}

$sessionManager->refreshSession();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard</title>
</head>
<body>
    <div>
        <header>
            <h1>Business Dashboard</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($userInfo['username']); ?>!</span>
                <a href="?logout=1">Logout</a>
            </div>
        </header>
        
        <main>
            <div>
                <h2>User Information</h2>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($userInfo['username']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($userInfo['email']); ?></p>
                <p><strong>User ID:</strong> <?php echo htmlspecialchars($userInfo['user_id']); ?></p>
                <p><strong>Login Time:</strong> <?php echo date('Y-m-d H:i:s', $userInfo['login_time']); ?></p>
            </div>
            
            <div>
                <h2>Dashboard Content</h2>
                <div>
                    <h3>Sales Overview</h3>
                    <p>Total Sales Today: $15,240</p>
                    <p>Total Orders: 45</p>
                    <p>Pending Orders: 8</p>
                </div>
                
                <div>
                    <h3>Recent Activities</h3>
                    <ul>
                        <li>New order #1234 received - $245.50</li>
                        <li>Customer John Doe updated profile</li>
                        <li>Inventory alert: Low stock on Product ABC</li>
                        <li>Payment processed for order #1230</li>
                        <li>New customer registration: Jane Smith</li>
                    </ul>
                </div>
                
                <div>
                    <h3>Quick Actions</h3>
                    <p><a href="#add-product">Add New Product</a></p>
                    <p><a href="#view-orders">View All Orders</a></p>
                    <p><a href="#customer-management">Manage Customers</a></p>
                    <p><a href="#reports">Generate Reports</a></p>
                    <p><a href="#settings">System Settings</a></p>
                </div>
                
                <div>
                    <h3>System Status</h3>
                    <p><strong>Server Status:</strong> Online</p>
                    <p><strong>Database Status:</strong> Connected</p>
                    <p><strong>Last Backup:</strong> 2024-01-15 02:30:00</p>
                    <p><strong>Active Users:</strong> 12</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>


<?php
require_once 'session_manager.php';

$sessionManager = new SessionManager();
$sessionManager->destroySession();

header('Location: login.php');
exit;
?>