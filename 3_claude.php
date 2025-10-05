<?php
class DatabaseConnection {
    private $pdo;
    private $host = 'localhost';
    private $dbname = 'your_database';
    private $username = 'your_username';
    private $password = 'your_password';

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}

class SecurityLogger {
    private $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/security.log';
    }

    public function log($event, $username, $ip) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] IP: {$ip} | Username: {$username} | Event: {$event}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

class RateLimiter {
    private $pdo;
    private $maxAttempts = 5;
    private $lockoutTime = 900;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createTable();
    }

    private function createTable() {
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            successful BOOLEAN DEFAULT FALSE,
            INDEX idx_username_ip (username, ip_address),
            INDEX idx_attempt_time (attempt_time)
        )";
        $this->pdo->exec($sql);
    }

    public function isLocked($username, $ip) {
        $sql = "SELECT COUNT(*) as failed_attempts FROM login_attempts 
                WHERE username = ? AND ip_address = ? AND successful = FALSE 
                AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username, $ip, $this->lockoutTime]);
        $result = $stmt->fetch();
        
        return $result['failed_attempts'] >= $this->maxAttempts;
    }

    public function recordAttempt($username, $ip, $successful) {
        $sql = "INSERT INTO login_attempts (username, ip_address, successful) VALUES (?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username, $ip, $successful]);
        
        if ($successful) {
            $this->clearFailedAttempts($username, $ip);
        }
    }

    private function clearFailedAttempts($username, $ip) {
        $sql = "DELETE FROM login_attempts WHERE username = ? AND ip_address = ? AND successful = FALSE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username, $ip]);
    }
}

class SessionManager {
    public function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            session_start();
            session_regenerate_id(true);
        }
    }

    public function createUserSession($userId, $username) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public function destroySession() {
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/', '', true, true);
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
}

class InputValidator {
    public function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public function validateUsername($username) {
        return !empty($username) && strlen($username) <= 255 && preg_match('/^[a-zA-Z0-9_.-]+$/', $username);
    }

    public function validatePassword($password) {
        return !empty($password) && strlen($password) >= 8 && strlen($password) <= 255;
    }
}

class UserAuthentication {
    private $pdo;
    private $rateLimiter;
    private $sessionManager;
    private $logger;
    private $validator;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->rateLimiter = new RateLimiter($pdo);
        $this->sessionManager = new SessionManager();
        $this->logger = new SecurityLogger();
        $this->validator = new InputValidator();
        $this->createUsersTable();
    }

    private function createUsersTable() {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_username (username)
        )";
        $this->pdo->exec($sql);
    }

    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    public function createUser($username, $password) {
        if (!$this->validator->validateUsername($username) || !$this->validator->validatePassword($password)) {
            return false;
        }

        $hashedPassword = $this->hashPassword($password);
        $sql = "INSERT INTO users (username, password_hash) VALUES (?, ?)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$username, $hashedPassword]);
        } catch (PDOException $e) {
            error_log("User creation failed: " . $e->getMessage());
            return false;
        }
    }

    public function login($username, $password) {
        $username = $this->validator->sanitizeInput($username);
        $password = $this->validator->sanitizeInput($password);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if (!$this->validator->validateUsername($username) || !$this->validator->validatePassword($password)) {
            $this->logger->log('LOGIN_FAILED_INVALID_INPUT', $username, $ip);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if ($this->rateLimiter->isLocked($username, $ip)) {
            $this->logger->log('LOGIN_BLOCKED_RATE_LIMIT', $username, $ip);
            return ['success' => false, 'message' => 'Account temporarily locked due to too many failed attempts'];
        }

        $sql = "SELECT id, username, password_hash, is_active FROM users WHERE username = ? AND is_active = TRUE";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $this->rateLimiter->recordAttempt($username, $ip, true);
            $this->updateLastLogin($user['id']);
            $this->sessionManager->startSecureSession();
            $this->sessionManager->createUserSession($user['id'], $user['username']);
            $this->logger->log('LOGIN_SUCCESS', $username, $ip);
            
            return ['success' => true, 'message' => 'Login successful'];
        } else {
            $this->rateLimiter->recordAttempt($username, $ip, false);
            $this->logger->log('LOGIN_FAILED', $username, $ip);
            
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
    }

    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
    }

    public function logout() {
        if ($this->sessionManager->isLoggedIn()) {
            $username = $_SESSION['username'] ?? 'unknown';
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $this->logger->log('LOGOUT', $username, $ip);
            $this->sessionManager->destroySession();
        }
    }

    public function isLoggedIn() {
        $this->sessionManager->startSecureSession();
        return $this->sessionManager->isLoggedIn();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <?php
    $db = new DatabaseConnection();
    $auth = new UserAuthentication($db->getConnection());
    
    if ($auth->isLoggedIn()) {
        echo '<h2>Welcome, ' . htmlspecialchars($_SESSION['username']) . '!</h2>';
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="logout">';
        echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';
        echo '<input type="submit" value="Logout">';
        echo '</form>';
    } else {
        $message = '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            $result = $auth->login($username, $password);
            $message = $result['message'];
            
            if ($result['success']) {
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
        
        if (!empty($message)) {
            echo '<div>' . htmlspecialchars($message) . '</div>';
        }
        
        echo '<form method="post" action="">';
        echo '<input type="hidden" name="action" value="login">';
        echo '<div>';
        echo '<label for="username">Username:</label>';
        echo '<input type="text" id="username" name="username" required maxlength="255">';
        echo '</div>';
        echo '<div>';
        echo '<label for="password">Password:</label>';
        echo '<input type="password" id="password" name="password" required maxlength="255">';
        echo '</div>';
        echo '<input type="submit" value="Login">';
        echo '</form>';
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'logout') {
        if (isset($_POST['csrf_token']) && $_POST['csrf_token'] === ($_SESSION['csrf_token'] ?? '')) {
            $auth->logout();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
    }
    ?>
</body>
</html>
?>