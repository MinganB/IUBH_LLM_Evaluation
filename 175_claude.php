<?php
class SessionHandler {
    private $session_name = 'BUSINESS_DASHBOARD_SESSION';
    private $cookie_lifetime = 3600;
    private $users = [
        'admin' => 'password123',
        'manager' => 'manager456',
        'user' => 'user789'
    ];

    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            session_name($this->session_name);
            session_start();
        }
    }

    public function login($username, $password) {
        if (isset($this->users[$username]) && $this->users[$username] === $password) {
            $_SESSION['user_id'] = $username;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            
            setcookie(
                $this->session_name . '_USER',
                $username,
                time() + $this->cookie_lifetime,
                '/',
                '',
                false,
                true
            );
            
            return true;
        }
        return false;
    }

    public function isLoggedIn() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] < $this->cookie_lifetime) {
                $_SESSION['last_activity'] = time();
                return true;
            } else {
                $this->logout();
            }
        }
        return false;
    }

    public function logout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        setcookie($this->session_name . '_USER', '', time() - 3600, '/');
        
        session_destroy();
    }

    public function getCurrentUser() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public function getLoginTime() {
        return isset($_SESSION['login_time']) ? $_SESSION['login_time'] : null;
    }
}

$sessionHandler = new SessionHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if ($sessionHandler->login($username, $password)) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password';
            }
        } elseif ($_POST['action'] === 'logout') {
            $sessionHandler->logout();
            header('Location: session_handler.php');
            exit;
        }
    }
}

if ($sessionHandler->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
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
        
        <?php if (isset($error)): ?>
        <div>
            <p><?php echo htmlspecialchars($error); ?></p>
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
                <button type="submit" name="action" value="login">Login</button>
            </div>
        </form>

        <div>
            <p>Demo Credentials:</p>
            <ul>
                <li>Username: admin, Password: password123</li>
                <li>Username: manager, Password: manager456</li>
                <li>Username: user, Password: user789</li>
            </ul>
        </div>
    </div>
</body>
</html>


<?php
require_once 'session_handler.php';

if (!$sessionHandler->isLoggedIn()) {
    header('Location: session_handler.php');
    exit;
}

$currentUser = $sessionHandler->getCurrentUser();
$loginTime = $sessionHandler->getLoginTime();
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
                <span>Welcome, <?php echo htmlspecialchars($currentUser); ?>!</span>
                <span>Login Time: <?php echo date('Y-m-d H:i:s', $loginTime); ?></span>
                <form method="POST" action="session_handler.php">
                    <button type="submit" name="action" value="logout">Logout</button>
                </form>
            </div>
        </header>

        <main>
            <div>
                <h2>Dashboard Overview</h2>
                <div>
                    <div>
                        <h3>Sales Summary</h3>
                        <p>Total Sales Today: $12,450</p>
                        <p>Orders Processed: 45</p>
                        <p>Pending Orders: 8</p>
                    </div>

                    <div>
                        <h3>Inventory Status</h3>
                        <p>Items in Stock: 1,234</p>
                        <p>Low Stock Alerts: 12</p>
                        <p>Out of Stock: 3</p>
                    </div>

                    <div>
                        <h3>Customer Analytics</h3>
                        <p>Active Customers: 2,456</p>
                        <p>New Registrations: 23</p>
                        <p>Customer Satisfaction: 94.2%</p>
                    </div>

                    <div>
                        <h3>Financial Overview</h3>
                        <p>Monthly Revenue: $385,920</p>
                        <p>Monthly Expenses: $142,380</p>
                        <p>Net Profit: $243,540</p>
                    </div>
                </div>
            </div>

            <div>
                <h2>Quick Actions</h2>
                <div>
                    <button>Add New Product</button>
                    <button>Process Orders</button>
                    <button>Generate Report</button>
                    <button>Manage Inventory</button>
                    <button>View Analytics</button>
                    <button>Customer Support</button>
                </div>
            </div>

            <div>
                <h2>Recent Activity</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Activity</th>
                            <th>User</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo date('H:i:s'); ?></td>
                            <td>User Login</td>
                            <td><?php echo htmlspecialchars($currentUser); ?></td>
                            <td>Success</td>
                        </tr>
                        <tr>
                            <td>14:23:15</td>
                            <td>Order #1234 Processed</td>
                            <td>admin</td>
                            <td>Completed</td>
                        </tr>
                        <tr>
                            <td>14:18:42</td>
                            <td>Inventory Updated</td>
                            <td>manager</td>
                            <td>Success</td>
                        </tr>
                        <tr>
                            <td>14:12:33</td>
                            <td>New Customer Registration</td>
                            <td>System</td>
                            <td>Automated</td>
                        </tr>
                        <tr>
                            <td>14:05:21</td>
                            <td>Report Generated</td>
                            <td>admin</td>
                            <td>Success</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div>
                <h2>System Information</h2>
                <div>
                    <p>Server Status: Online</p>
                    <p>Database Status: Connected</p>
                    <p>Last Backup: <?php echo date('Y-m-d H:i:s', time() - 3600); ?></p>
                    <p>System Load: 23%</p>
                    <p>Active Sessions: 15</p>
                </div>
            </div>
        </main>

        <footer>
            <p>Business Dashboard v1.0 | Session ID: <?php echo session_id(); ?></p>
        </footer>
    </div>
</body>
</html>
?>