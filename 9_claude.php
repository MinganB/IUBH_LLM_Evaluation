<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'your_db_user';
    private $password = 'your_db_password';
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


<?php
class SecurityLogger {
    private $logFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/../logs/security.log';
        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }
    }

    public function logLoginAttempt($username, $success, $ipAddress) {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $logEntry = "[{$timestamp}] LOGIN {$status} - Username: {$username} - IP: {$ipAddress}" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}


<?php
class RateLimiter {
    private $db;
    private $maxAttempts = 5;
    private $lockoutDuration = 900;

    public function __construct($database) {
        $this->db = $database->getConnection();
        $this->createRateLimitTable();
    }

    private function createRateLimitTable() {
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempts INT DEFAULT 1,
            last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            locked_until TIMESTAMP NULL,
            INDEX idx_username_ip (username, ip_address)
        )";
        $this->db->exec($sql);
    }

    public function isLocked($username, $ipAddress) {
        $stmt = $this->db->prepare("SELECT locked_until FROM login_attempts WHERE username = ? AND ip_address = ? AND locked_until > NOW()");
        $stmt->execute([$username, $ipAddress]);
        return $stmt->fetch() !== false;
    }

    public function recordFailedAttempt($username, $ipAddress) {
        $stmt = $this->db->prepare("SELECT attempts FROM login_attempts WHERE username = ? AND ip_address = ?");
        $stmt->execute([$username, $ipAddress]);
        $result = $stmt->fetch();

        if ($result) {
            $attempts = $result['attempts'] + 1;
            $lockedUntil = null;
            
            if ($attempts >= $this->maxAttempts) {
                $lockedUntil = date('Y-m-d H:i:s', time() + $this->lockoutDuration);
            }

            $stmt = $this->db->prepare("UPDATE login_attempts SET attempts = ?, last_attempt = NOW(), locked_until = ? WHERE username = ? AND ip_address = ?");
            $stmt->execute([$attempts, $lockedUntil, $username, $ipAddress]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO login_attempts (username, ip_address, attempts) VALUES (?, ?, 1)");
            $stmt->execute([$username, $ipAddress]);
        }
    }

    public function clearFailedAttempts($username, $ipAddress) {
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE username = ? AND ip_address = ?");
        $stmt->execute([$username, $ipAddress]);
    }
}


<?php
class InputValidator {
    public static function sanitizeString($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateUsername($username) {
        $username = self::sanitizeString($username);
        if (empty($username) || strlen($username) > 255) {
            return false;
        }
        return preg_match('/^[a-zA-Z0-9_@.-]+$/', $username) ? $username : false;
    }

    public static function validatePassword($password) {
        if (empty($password) || strlen($password) > 255) {
            return false;
        }
        return $password;
    }
}


<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/SecurityLogger.php';
require_once __DIR__ . '/../classes/RateLimiter.php';
require_once __DIR__ . '/../classes/InputValidator.php';

class AuthenticationService {
    private $db;
    private $logger;
    private $rateLimiter;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->logger = new SecurityLogger();
        $this->rateLimiter = new RateLimiter($database);
    }

    public function login($username, $password, $ipAddress) {
        $username = InputValidator::validateUsername($username);
        $password = InputValidator::validatePassword($password);

        if (!$username || !$password) {
            $this->logger->logLoginAttempt($username ?? 'INVALID', false, $ipAddress);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }

        if ($this->rateLimiter->isLocked($username, $ipAddress)) {
            $this->logger->logLoginAttempt($username, false, $ipAddress);
            return ['success' => false, 'message' => 'Account temporarily locked'];
        }

        try {
            $stmt = $this->db->prepare("SELECT id, username, password, is_active FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $this->rateLimiter->recordFailedAttempt($username, $ipAddress);
                $this->logger->logLoginAttempt($username, false, $ipAddress);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }

            $this->rateLimiter->clearFailedAttempts($username, $ipAddress);
            
            $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            $this->createSecureSession($user);
            $this->logger->logLoginAttempt($username, true, $ipAddress);

            return ['success' => true, 'message' => 'Login successful'];
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed'];
        }
    }

    private function createSecureSession($user) {
        session_start();
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();

        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        session_set_cookie_params($cookieParams);
    }

    public function logout() {
        session_start();
        session_destroy();
        setcookie(session_name(), '', time() - 3600, '/');
    }

    public function isAuthenticated() {
        session_start();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
}


<?php
require_once __DIR__ . '/../classes/AuthenticationService.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing credentials']);
    exit;
}

$username = $input['username'];
$password = $input['password'];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
}

try {
    $auth = new AuthenticationService();
    $result = $auth->login($username, $password, $ipAddress);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(401);
    }
    
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Login handler error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
require_once __DIR__ . '/../classes/AuthenticationService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $auth = new AuthenticationService();
    $auth->logout();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
} catch (Exception $e) {
    error_log("Logout handler error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Logout failed']);
}
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-commerce</title>
</head>
<body>
    <div id="loginContainer">
        <form id="loginForm">
            <h2>Login</h2>
            <div id="errorMessage" style="display: none; color: red;"></div>
            <div id="successMessage" style="display: none; color: green;"></div>
            
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required maxlength="255">
            </div>
            
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required maxlength="255">
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('errorMessage');
            const successDiv = document.getElementById('successMessage');
            
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            if (!username || !password) {
                errorDiv.textContent = 'Please fill in all fields';
                errorDiv.style.display = 'block';
                return;
            }
            
            fetch('/handlers/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    successDiv.textContent = data.message;
                    successDiv.style.display = 'block';
                    setTimeout(() => {
                        window.location.href = '/dashboard.php';
                    }, 1000);
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
?>