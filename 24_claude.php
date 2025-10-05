<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <div>
        <h2>Login</h2>
        <?php if (isset($_SESSION['error'])): ?>
            <div><?php echo htmlspecialchars($_SESSION['error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required maxlength="100">
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required maxlength="255">
            </div>
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
</body>
</html>


<?php
session_start();

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

class DatabaseConnection {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $host = 'localhost';
        $dbname = 'db_users';
        $username = 'db_username';
        $password = 'db_password';
        
        try {
            $this->pdo = new PDO(
                "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Connection failed");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

class AuthLogger {
    private static function log($message) {
        $logFile = '/var/log/auth.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry = "[$timestamp] IP: $ip - $message" . PHP_EOL;
        error_log($logEntry, 3, $logFile);
    }
    
    public static function logSuccess($username) {
        self::log("Successful login for user: " . $username);
    }
    
    public static function logFailure($username) {
        self::log("Failed login attempt for user: " . $username);
    }
    
    public static function logLockout($username) {
        self::log("Account locked for user: " . $username);
    }
}

class RateLimiter {
    private $pdo;
    private $maxAttempts = 5;
    private $lockoutTime = 900;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function isLocked($username) {
        $stmt = $this->pdo->prepare("SELECT failed_attempts, last_attempt FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        
        if ($result) {
            $timeDiff = time() - strtotime($result['last_attempt']);
            if ($result['failed_attempts'] >= $this->maxAttempts && $timeDiff < $this->lockoutTime) {
                return true;
            }
        }
        return false;
    }
    
    public function recordFailure($username) {
        $stmt = $this->pdo->prepare("INSERT INTO login_attempts (username, failed_attempts, last_attempt) VALUES (?, 1, NOW()) ON DUPLICATE KEY UPDATE failed_attempts = failed_attempts + 1, last_attempt = NOW()");
        $stmt->execute([$username]);
        
        $stmt = $this->pdo->prepare("SELECT failed_attempts FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);
        $result = $stmt->fetch();
        
        if ($result && $result['failed_attempts'] >= $this->maxAttempts) {
            AuthLogger::logLockout($username);
        }
    }
    
    public function resetFailures($username) {
        $stmt = $this->pdo->prepare("DELETE FROM login_attempts WHERE username = ?");
        $stmt->execute([$username]);
    }
}

class Auth {
    private $pdo;
    private $rateLimiter;
    
    public function __construct() {
        $this->pdo = DatabaseConnection::getInstance()->getConnection();
        $this->rateLimiter = new RateLimiter($this->pdo);
    }
    
    public function login($username, $password) {
        $username = trim($username);
        $password = trim($password);
        
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        if (strlen($username) > 100 || strlen($password) > 255) {
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        
        if ($this->rateLimiter->isLocked($username)) {
            return ['success' => false, 'message' => 'Account temporarily locked'];
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT id, username, password FROM users WHERE username = ? AND active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $this->rateLimiter->resetFailures($username);
                $this->createSession($user);
                AuthLogger::logSuccess($username);
                return ['success' => true, 'message' => 'Login successful'];
            } else {
                $this->rateLimiter->recordFailure($username);
                AuthLogger::logFailure($username);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }
    
    private function createSession($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$auth = new Auth();
$result = $auth->login($username, $password);

if ($result['success']) {
    header('Location: dashboard.php');
    exit();
} else {
    $_SESSION['error'] = $result['message'];
    header('Location: index.php');
    exit();
}
?>


<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <div>
        <h1>Welcome to Dashboard</h1>
        <p>Hello, <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>!</p>
        <nav>
            <ul>
                <li><a href="products.php">View Products</a></li>
                <li><a href="orders.php">Manage Orders</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>
        <div>
            <h2>Dashboard Content</h2>
            <p>Welcome to your e-commerce dashboard. Use the navigation above to manage your products and orders.</p>
        </div>
    </div>
</body>
</html>


<?php
session_start();
session_destroy();
session_start();
session_regenerate_id(true);
header('Location: index.php');
exit();
?>


sql
CREATE DATABASE IF NOT EXISTS db_users CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE db_users;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    failed_attempts INT DEFAULT 0,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_username (username)
);

INSERT INTO users (username, password, email) VALUES 
('admin', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewdBPj6ukdYlfUGO', 'admin@example.com');
?>