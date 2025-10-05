<?php
class SessionManager {
    private $sessionName = 'business_dashboard_session';
    private $cookieName = 'dashboard_auth';
    private $sessionLifetime = 3600;
    private $cookieLifetime = 86400;
    private $cookiePath = '/';
    private $cookieDomain = '';
    private $cookieSecure = true;
    private $cookieHttpOnly = true;
    private $sessionRegenerateInterval = 300;
    
    public function __construct() {
        $this->initializeSession();
    }
    
    private function initializeSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', $this->cookieSecure ? 1 : 0);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            session_name($this->sessionName);
            session_start();
        }
    }
    
    public function createSession($userId, $userEmail, $userRole = 'user') {
        $this->regenerateSessionId();
        
        $_SESSION['user_id'] = (int)$userId;
        $_SESSION['user_email'] = filter_var($userEmail, FILTER_SANITIZE_EMAIL);
        $_SESSION['user_role'] = htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8');
        $_SESSION['session_created'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['last_regenerate'] = time();
        
        $cookieValue = $this->generateSecureCookieValue($userId);
        setcookie(
            $this->cookieName,
            $cookieValue,
            time() + $this->cookieLifetime,
            $this->cookiePath,
            $this->cookieDomain,
            $this->cookieSecure,
            $this->cookieHttpOnly
        );
        
        return true;
    }
    
    public function validateSession() {
        if (!$this->isSessionActive()) {
            return false;
        }
        
        if (!$this->validateSessionIntegrity()) {
            $this->destroySession();
            return false;
        }
        
        if ($this->isSessionExpired()) {
            $this->destroySession();
            return false;
        }
        
        if (!$this->validateCookie()) {
            $this->destroySession();
            return false;
        }
        
        $this->updateLastActivity();
        $this->maybeRegenerateSession();
        
        return true;
    }
    
    private function isSessionActive() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['session_created']) && 
               isset($_SESSION['last_activity']);
    }
    
    private function validateSessionIntegrity() {
        $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $currentIp) {
            return false;
        }
        
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentUserAgent) {
            return false;
        }
        
        return true;
    }
    
    private function isSessionExpired() {
        $sessionAge = time() - $_SESSION['session_created'];
        $inactiveTime = time() - $_SESSION['last_activity'];
        
        return ($sessionAge > $this->sessionLifetime) || ($inactiveTime > 1800);
    }
    
    private function validateCookie() {
        if (!isset($_COOKIE[$this->cookieName])) {
            return false;
        }
        
        $cookieValue = $_COOKIE[$this->cookieName];
        $expectedValue = $this->generateSecureCookieValue($_SESSION['user_id']);
        
        return hash_equals($expectedValue, $cookieValue);
    }
    
    private function generateSecureCookieValue($userId) {
        $secret = $this->getApplicationSecret();
        return hash_hmac('sha256', $userId . $_SESSION['session_created'], $secret);
    }
    
    private function getApplicationSecret() {
        return hash('sha256', 'dashboard_secret_key_change_in_production' . $_SERVER['SERVER_NAME']);
    }
    
    private function updateLastActivity() {
        $_SESSION['last_activity'] = time();
    }
    
    private function maybeRegenerateSession() {
        if (!isset($_SESSION['last_regenerate'])) {
            $_SESSION['last_regenerate'] = time();
            return;
        }
        
        if (time() - $_SESSION['last_regenerate'] > $this->sessionRegenerateInterval) {
            $this->regenerateSessionId();
            $_SESSION['last_regenerate'] = time();
        }
    }
    
    private function regenerateSessionId() {
        session_regenerate_id(true);
    }
    
    public function destroySession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = array();
            
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
        }
        
        if (isset($_COOKIE[$this->cookieName])) {
            setcookie(
                $this->cookieName,
                '',
                time() - 3600,
                $this->cookiePath,
                $this->cookieDomain,
                $this->cookieSecure,
                $this->cookieHttpOnly
            );
        }
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getUserEmail() {
        return $_SESSION['user_email'] ?? null;
    }
    
    public function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    public function getCsrfToken() {
        return $_SESSION['csrf_token'] ?? null;
    }
    
    public function validateCsrfToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>


<?php
class UserAuthentication {
    private $dbHost = 'localhost';
    private $dbName = 'business_dashboard';
    private $dbUser = 'db_user';
    private $dbPass = 'db_password';
    private $pdo;
    
    public function __construct() {
        $this->initializeDatabase();
    }
    
    private function initializeDatabase() {
        try {
            $dsn = "mysql:host={$this->dbHost};dbname={$this->dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $this->pdo = new PDO($dsn, $this->dbUser, $this->dbPass, $options);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed');
        }
    }
    
    public function authenticateUser($email, $password) {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return false;
        }
        
        $stmt = $this->pdo->prepare('SELECT id, email, password_hash, role, is_active FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        $this->updateLastLogin($user['id']);
        
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ];
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }
    
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
?>


<?php
require_once 'SessionManager.php';
require_once 'UserAuthentication.php';

$sessionManager = new SessionManager();
$auth = new UserAuthentication();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!$sessionManager->validateCsrfToken($csrfToken)) {
        $error = 'Invalid request';
    } else {
        $user = $auth->authenticateUser($email, $password);
        
        if ($user) {
            $sessionManager->createSession($user['id'], $user['email'], $user['role']);
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    }
}

$csrfToken = bin2hex(random_bytes(32));
session_start();
$_SESSION['csrf_token'] = $csrfToken;
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
        <h2>Login to Dashboard</h2>
        
        <?php if (isset($error)): ?>
            <div style="color: red; margin-bottom: 15px;">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required maxlength="255">
            </div>
            
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required maxlength="255">
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
</body>
</html>


<?php
require_once 'SessionManager.php';

$sessionManager = new SessionManager();

if (!$sessionManager->validateSession()) {
    header('Location: login.php');
    exit;
}

$userId = $sessionManager->getUserId();
$userEmail = $sessionManager->getUserEmail();
$userRole = $sessionManager->getUserRole();
$csrfToken = $sessionManager->getCsrfToken();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Business Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <header>
        <h1>Business Dashboard</h1>
        <div>
            Welcome, <?php echo htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8'); ?>
            (<?php echo htmlspecialchars($userRole, ENT_QUOTES, 'UTF-8'); ?>)
            <a href="logout.php">Logout</a>
        </div>
    </header>
    
    <main>
        <section>
            <h2>Dashboard Overview</h2>
            <p>You have successfully logged in to the business dashboard.</p>
            
            <div>
                <h3>Quick Stats</h3>
                <div>
                    <div>
                        <h4>Sales Today</h4>
                        <p>$2,450</p>
                    </div>
                    <div>
                        <h4>Orders</h4>
                        <p>23</p>
                    </div>
                    <div>
                        <h4>Customers</h4>
                        <p>156</p>
                    </div>
                </div>
            </div>
            
            <div>
                <h3>Recent Activity</h3>
                <ul>
                    <li>New order #1234 - $125.00</li>
                    <li>Customer registration - John Doe</li>
                    <li>Product update - Widget A</li>
                </ul>
            </div>
        </section>
        
        <section>
            <h2>Management Tools</h2>
            <nav>
                <ul>
                    <li><a href="orders.php">Orders</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="customers.php">Customers</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <?php if ($userRole === 'admin'): ?>
                        <li><a href="users.php">User Management</a></li>
                        <li><a href="settings.php">Settings</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </section>
    </main>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form) {
                if (!form.querySelector('input[name="csrf_token"]')) {
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = 'csrf_token';
                    csrfInput.value = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
                    form.appendChild(csrfInput);
                }
            });
        });
    </script>
</body>
</html>


<?php
require_once 'SessionManager.php';

$sessionManager = new SessionManager();
$sessionManager->destroySession();

header('Location: login.php');
exit;
?>


<?php
require_once 'SessionManager.php';

function requireLogin() {
    $sessionManager = new SessionManager();
    
    if (!$sessionManager->validateSession()) {
        header('Location: login.php');
        exit;
    }
    
    return $sessionManager;
}

function requireRole($requiredRole) {
    $sessionManager = requireLogin();
    $userRole = $sessionManager->getUserRole();
    
    if ($userRole !== $requiredRole && $userRole !== 'admin') {
        header('HTTP/1.1 403 Forbidden');
        exit('Access denied');
    }
    
    return $sessionManager;
}

function getCurrentUser() {
    $
?>