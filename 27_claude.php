<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <form action="login.php" method="POST">
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Login</button>
        <?php if (isset($_SESSION['login_error'])): ?>
            <div><?php echo htmlspecialchars($_SESSION['login_error']); unset($_SESSION['login_error']); ?></div>
        <?php endif; ?>
    </form>
</body>
</html>


<?php
session_start();

class Database {
    private $pdo;
    
    public function __construct() {
        $dsn = 'mysql:host=localhost;dbname=db_users;charset=utf8mb4';
        $username = 'db_username';
        $password = 'db_password';
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed');
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

class Auth {
    private $db;
    private $maxAttempts = 5;
    private $lockoutTime = 900;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password) {
        $username = $this->sanitizeInput($username);
        $clientIP = $this->getClientIP();
        
        if ($this->isAccountLocked($username)) {
            $this->logAttempt($username, $clientIP, false, 'Account locked');
            return ['success' => false, 'message' => 'Account temporarily locked'];
        }
        
        $user = $this->getUserByUsername($username);
        
        if (!$user || !$user['is_active']) {
            $this->recordFailedAttempt($username, $clientIP);
            $this->logAttempt($username, $clientIP, false, 'Invalid credentials');
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        if (!password_verify($password, $user['password'])) {
            $this->recordFailedAttempt($username, $clientIP);
            $this->logAttempt($username, $clientIP, false, 'Invalid password');
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        $this->clearFailedAttempts($username);
        $this->updateLastLogin($user['id']);
        $this->createSecureSession($user);
        $this->logAttempt($username, $clientIP, true, 'Successful login');
        
        return ['success' => true, 'message' => 'Login successful'];
    }
    
    private function sanitizeInput($input) {
        return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
    }
    
    private function getUserByUsername($username) {
        $stmt = $this->db->prepare("SELECT id, username, password, is_active FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    private function isAccountLocked($username) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as attempts, MAX(attempted_at) as last_attempt FROM login_attempts WHERE username = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND) AND success = 0");
        $stmt->execute([$username, $this->lockoutTime]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= $this->maxAttempts;
    }
    
    private function recordFailedAttempt($username, $ip) {
        $stmt = $this->db->prepare("INSERT INTO login_attempts (username, ip_address, success, attempted_at) VALUES (?, ?, 0, NOW())");
        $stmt->execute([$username, $ip]);
    }
    
    private function clearFailedAttempts($username) {
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE username = ? AND success = 0");
        $stmt->execute([$username]);
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    private function createSecureSession($user) {
        session_regenerate_id(true);
        
        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        session_set_cookie_params($cookieParams);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
    }
    
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function logAttempt($username, $ip, $success, $message) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $ip,
            'username' => $username,
            'success' => $success,
            'message' => $message,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $stmt = $this->db->prepare("INSERT INTO login_attempts (username, ip_address, success, attempted_at, user_agent, message) VALUES (?, ?, ?, NOW(), ?, ?)");
        $stmt->execute([$username, $ip, $success ? 1 : 0, $logEntry['user_agent'], $message]);
        
        error_log('Login attempt: ' . json_encode($logEntry));
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $auth = new Auth($database);
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            $_SESSION['login_error'] = 'Username and password are required';
            header('Location: index.php');
            exit;
        }
        
        $result = $auth->login($username, $password);
        
        if ($result['success']) {
            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['login_error'] = $result['message'];
            header('Location: index.php');
            exit;
        }
        
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        $_SESSION['login_error'] = 'An error occurred. Please try again later.';
        header('Location: index.php');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}
?>


<?php
session_start();

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <p>You have successfully logged in to your dashboard.</p>
    <a href="logout.php">Logout</a>
</body>
</html>


<?php
session_start();
session_destroy();
header('Location: index.php');
exit;
?>


sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    last_login_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_active (is_active)
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    user_agent TEXT,
    message VARCHAR(255),
    INDEX idx_username_time (username, attempted_at),
    INDEX idx_ip_time (ip_address, attempted_at),
    INDEX idx_success (success)
);
?>