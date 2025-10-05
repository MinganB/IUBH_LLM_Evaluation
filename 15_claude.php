<?php
// config.php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900);
define('LOG_FILE', '/var/log/auth.log');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("System temporarily unavailable");
}
?>


<?php
// auth.php
<?php
require_once 'config.php';

class AuthManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public function logAttempt($username, $ip, $success, $message = '') {
        $logEntry = date('Y-m-d H:i:s') . " - IP: $ip - Username: $username - " . 
                   ($success ? 'SUCCESS' : 'FAILED') . " - $message" . PHP_EOL;
        file_put_contents(LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function isAccountLocked($username) {
        $stmt = $this->pdo->prepare("
            SELECT failed_attempts, last_failed_attempt 
            FROM users 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user) return false;
        
        if ($user['failed_attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $lockoutEnd = strtotime($user['last_failed_attempt']) + LOCKOUT_TIME;
            if (time() < $lockoutEnd) {
                return true;
            } else {
                $this->resetFailedAttempts($username);
            }
        }
        
        return false;
    }
    
    public function incrementFailedAttempts($username) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_attempts = failed_attempts + 1, 
                last_failed_attempt = NOW() 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
    }
    
    public function resetFailedAttempts($username) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET failed_attempts = 0, 
                last_failed_attempt = NULL 
            WHERE username = ?
        ");
        $stmt->execute([$username]);
    }
    
    public function authenticate($username, $password) {
        $username = $this->sanitizeInput($username);
        $password = $this->sanitizeInput($password);
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (empty($username) || empty($password)) {
            $this->logAttempt($username, $ip, false, 'Empty credentials');
            return false;
        }
        
        if ($this->isAccountLocked($username)) {
            $this->logAttempt($username, $ip, false, 'Account locked');
            return false;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT id, username, password_hash, email, is_active 
            FROM users 
            WHERE username = ? AND is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->incrementFailedAttempts($username);
            $this->logAttempt($username, $ip, false, 'Invalid credentials');
            return false;
        }
        
        $this->resetFailedAttempts($username);
        $this->updateLastLogin($user['id']);
        $this->logAttempt($username, $ip, true, 'Login successful');
        
        return $user;
    }
    
    public function updateLastLogin($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE users 
            SET last_login = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    public function createSession($user) {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
    }
    
    public function validateSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['ip_address']) || 
            !isset($_SESSION['user_agent'])) {
            return false;
        }
        
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
            $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            session_destroy();
            return false;
        }
        
        if (time() - $_SESSION['login_time'] > 3600) {
            session_destroy();
            return false;
        }
        
        return true;
    }
    
    public function logout() {
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/', '', true, true);
    }
}

$auth = new AuthManager($pdo);
?>


<?php
// session_config.php
<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 3600);

session_start();
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Commerce</title>
</head>
<body>
    <div class="login-container">
        <h2>Login to Your Account</h2>
        
        <?php
        require_once 'session_config.php';
        
        if (isset($_SESSION['error_message'])) {
            echo '<div class="error-message">' . htmlspecialchars($_SESSION['error_message'], ENT_QUOTES, 'UTF-8') . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>
        
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required maxlength="50" 
                       value="<?php echo isset($_SESSION['last_username']) ? htmlspecialchars($_SESSION['last_username'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required maxlength="255">
            </div>
            
            <input type="hidden" name="csrf_token" value="<?php 
                if (!isset($_SESSION['csrf_token'])) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                }
                echo $_SESSION['csrf_token']; 
            ?>">
            
            <button type="submit">Login</button>
        </form>
        
        <p><a href="register.php">Don't have an account? Register here</a></p>
        <p><a href="forgot_password.php">Forgot your password?</a></p>
    </div>
</body>
</html>


<?php
// login.php
<?php
require_once 'session_config.php';
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login_form.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Invalid request. Please try again.';
    header('Location: login_form.php');
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$_SESSION['last_username'] = $username;

if (empty($username) || empty($password)) {
    $_SESSION['error_message'] = 'Please enter both username and password.';
    header('Location: login_form.php');
    exit;
}

$user = $auth->authenticate($username, $password);

if ($user) {
    unset($_SESSION['last_username']);
    unset($_SESSION['error_message']);
    
    $auth->createSession($user);
    
    $redirectUrl = $_SESSION['redirect_after_login'] ?? 'dashboard.php';
    unset($_SESSION['redirect_after_login']);
    
    header('Location: ' . $redirectUrl);
    exit;
} else {
    $_SESSION['error_message'] = 'Invalid username or password. Please try again.';
    header('Location: login_form.php');
    exit;
}
?>


<?php
// dashboard.php
<?php
require_once 'session_config.php';
require_once 'auth.php';

if (!$auth->validateSession()) {
    $_SESSION['error_message'] = 'Please log in to access this page.';
    $_SESSION['redirect_after_login'] = 'dashboard.php';
    header('Location: login_form.php');
    exit;
}

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - E-Commerce</title>
</head>
<body>
    <header>
        <h1>Welcome, <?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>!</h1>
        <nav>
            <a href="products.php">Browse Products</a>
            <a href="orders.php">My Orders</a>
            <a href="profile.php">My Profile</a>
            <a href="logout.php">Logout</a>
        </nav>
    </header>
    
    <main>
        <div class="dashboard-content">
            <h2>Your Dashboard</h2>
            
            <div class="dashboard-section">
                <h3>Recent Orders</h3>
                <p>View and manage your recent orders.</p>
                <a href="orders.php">View All Orders</a>
            </div>
            
            <div class="dashboard-section">
                <h3>Browse Products</h3>
                <p>Discover new products in our catalog.</p>
                <a href="products.php">Browse Now</a>
            </div>
            
            <div class="dashboard-section">
                <h3>Account Settings</h3>
                <p>Update your profile and account preferences.</p>
                <a href="profile.php">Edit Profile</a>
            </div>
        </div>
    </main>
</body>
</html>


<?php
// logout.php
<?php
require_once 'session_config.php';
require_once 'auth.php';

if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $auth->logAttempt($username, $ip, true, 'Logout successful');
}

$auth->logout();

header('Location: login_form.php');
exit;
?>


sql
-- database_schema.sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    failed_attempts INT DEFAULT 0,
    last_failed_attempt TIMESTAMP NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_failed_attempts (failed_attempts, last_failed_attempt)
);
?>