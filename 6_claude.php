<?php
session_start();

class DatabaseConnection {
    private $host = 'localhost';
    private $dbname = 'ecommerce_db';
    private $username = 'db_user';
    private $password = 'db_password';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}

class SecurityLogger {
    private $logFile;

    public function __construct() {
        $this->logFile = '/var/log/auth/login_attempts.log';
    }

    public function logAttempt($username, $ip, $success, $reason = '') {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $logEntry = "[{$timestamp}] IP: {$ip} | Username: {$username} | Status: {$status}";
        if ($reason) {
            $logEntry .= " | Reason: {$reason}";
        }
        $logEntry .= PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

class RateLimiter {
    private $pdo;
    private $maxAttempts = 5;
    private $lockoutTime = 900;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function isLocked($username, $ip) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts, MAX(attempt_time) as last_attempt 
            FROM login_attempts 
            WHERE (username = ? OR ip_address = ?) 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND) 
            AND success = 0
        ");
        $stmt->execute([$username, $ip, $this->lockoutTime]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= $this->maxAttempts;
    }

    public function recordAttempt($username, $ip, $success) {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (username, ip_address, success, attempt_time) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $ip, $success ? 1 : 0]);
    }

    public function clearAttempts($username, $ip) {
        $stmt = $this->pdo->prepare("
            DELETE FROM login_attempts 
            WHERE username = ? OR ip_address = ?
        ");
        $stmt->execute([$username, $ip]);
    }
}

class AuthenticationModule {
    private $pdo;
    private $rateLimiter;
    private $logger;

    public function __construct() {
        $db = new DatabaseConnection();
        $this->pdo = $db->getConnection();
        $this->rateLimiter = new RateLimiter($this->pdo);
        $this->logger = new SecurityLogger();
    }

    public function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }

    public function getUserByEmail($email) {
        $stmt = $this->pdo->prepare("
            SELECT id, email, password_hash, is_active 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function createSecureSession($userId) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['last_activity'] = time();
        
        $sessionId = session_id();
        $stmt = $this->pdo->prepare("
            INSERT INTO user_sessions (user_id, session_id, created_at, last_activity) 
            VALUES (?, ?, NOW(), NOW()) 
            ON DUPLICATE KEY UPDATE 
            session_id = VALUES(session_id), 
            last_activity = VALUES(last_activity)
        ");
        $stmt->execute([$userId, $sessionId]);

        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
    }

    public function authenticate($email, $password) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $email = $this->sanitizeInput($email);
        $password = $this->sanitizeInput($password);

        if (!$this->validateEmail($email)) {
            $this->logger->logAttempt($email, $ip, false, 'Invalid email format');
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if ($this->rateLimiter->isLocked($email, $ip)) {
            $this->logger->logAttempt($email, $ip, false, 'Account locked');
            return ['success' => false, 'message' => 'Account temporarily locked'];
        }

        $user = $this->getUserByEmail($email);

        if (!$user || !$user['is_active']) {
            $this->rateLimiter->recordAttempt($email, $ip, false);
            $this->logger->logAttempt($email, $ip, false, 'User not found or inactive');
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if (!$this->verifyPassword($password, $user['password_hash'])) {
            $this->rateLimiter->recordAttempt($email, $ip, false);
            $this->logger->logAttempt($email, $ip, false, 'Invalid password');
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        $this->rateLimiter->clearAttempts($email, $ip);
        $this->rateLimiter->recordAttempt($email, $ip, true);
        $this->createSecureSession($user['id']);
        $this->logger->logAttempt($email, $ip, true);

        return ['success' => true, 'message' => 'Login successful'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';

        $auth = new AuthenticationModule();
        $result = $auth->authenticate($email, $password);

        if ($result['success']) {
            http_response_code(200);
            echo json_encode(['success' => true, 'redirect' => '/dashboard']);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    } catch (Exception $e) {
        error_log("Authentication error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Commerce</title>
</head>
<body>
    <div class="login-container">
        <form id="loginForm">
            <h2>Login</h2>
            <div id="errorMessage" style="display: none; color: red;"></div>
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Login</button>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('errorMessage');
            
            errorDiv.style.display = 'none';
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    errorDiv.textContent = data.message;
                    errorDiv.style.display = 'block';
                }
            })
            .catch(error => {
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.display = 'block';
            });
        });
    </script>
</body>
</html>


sql
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_time (username, attempt_time),
    INDEX idx_ip_time (ip_address, attempt_time)
);

CREATE TABLE IF NOT EXISTS user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_session (user_id)
);
?>