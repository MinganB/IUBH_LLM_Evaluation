<?php
session_start();

class Database {
    private $host = 'localhost';
    private $dbname = 'ecommerce';
    private $username = 'your_db_username';
    private $password = 'your_db_password';
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4", 
                               $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch(PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}

class Auth {
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function login($email, $password) {
        if ($this->isLockedOut($email)) {
            return ['success' => false, 'message' => 'Account temporarily locked due to too many failed attempts'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT id, email, password_hash, is_active, failed_attempts, last_failed_attempt 
                 FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->logFailedAttempt($email);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Account is deactivated'];
            }
            
            if (password_verify($password, $user['password_hash'])) {
                $this->clearFailedAttempts($email);
                $this->createSession($user);
                $this->updateLastLogin($user['id']);
                return ['success' => true, 'message' => 'Login successful'];
            } else {
                $this->logFailedAttempt($email);
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
        } catch(Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Login failed. Please try again.'];
        }
    }
    
    private function isLockedOut($email) {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT failed_attempts, last_failed_attempt FROM users WHERE email = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) return false;
            
            if ($user['failed_attempts'] >= $this->maxLoginAttempts) {
                $timeDiff = time() - strtotime($user['last_failed_attempt']);
                return $timeDiff < $this->lockoutTime;
            }
            
            return false;
        } catch(Exception $e) {
            error_log("Lockout check error: " . $e->getMessage());
            return false;
        }
    }
    
    private function logFailedAttempt($email) {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE users SET failed_attempts = failed_attempts + 1, 
                 last_failed_attempt = NOW() WHERE email = ?"
            );
            $stmt->execute([$email]);
        } catch(Exception $e) {
            error_log("Failed attempt logging error: " . $e->getMessage());
        }
    }
    
    private function clearFailedAttempts($email) {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE users SET failed_attempts = 0, last_failed_attempt = NULL WHERE email = ?"
            );
            $stmt->execute([$email]);
        } catch(Exception $e) {
            error_log("Clear failed attempts error: " . $e->getMessage());
        }
    }
    
    private function createSession($user) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    private function updateLastLogin($userId) {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "UPDATE users SET last_login = NOW() WHERE id = ?"
            );
            $stmt->execute([$userId]);
        } catch(Exception $e) {
            error_log("Last login update error: " . $e->getMessage());
        }
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
        session_destroy();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && 
               isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id']);
    }
    
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }
        
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 86400) {
            $this->logout();
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expired']);
            exit;
        }
    }
    
    public function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function getCSRFToken() {
        return $_SESSION['csrf_token'] ?? '';
    }
}

class SecurityHeaders {
    public static function setHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Content-Security-Policy: default-src \'self\'');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

class RateLimiter {
    private $db;
    private $maxAttempts = 10;
    private $timeWindow = 300;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function checkLimit($ip) {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "SELECT COUNT(*) as attempts FROM login_attempts 
                 WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)"
            );
            $stmt->execute([$ip, $this->timeWindow]);
            $result = $stmt->fetch();
            
            return $result['attempts'] < $this->maxAttempts;
        } catch(Exception $e) {
            error_log("Rate limit check error: " . $e->getMessage());
            return true;
        }
    }
    
    public function logAttempt($ip) {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "INSERT INTO login_attempts (ip_address, attempt_time) VALUES (?, NOW())"
            );
            $stmt->execute([$ip]);
        } catch(Exception $e) {
            error_log("Rate limit logging error: " . $e->getMessage());
        }
    }
    
    public function cleanOldAttempts() {
        try {
            $stmt = $this->db->getConnection()->prepare(
                "DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? SECOND)"
            );
            $stmt->execute([$this->timeWindow]);
        } catch(Exception $e) {
            error_log("Cleanup error: " . $e->getMessage());
        }
    }
}
?>


<?php
require_once 'auth.php';

SecurityHeaders::setHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

$rateLimiter = new RateLimiter();
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!$rateLimiter->checkLimit($clientIP)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many attempts. Please try again later.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['email']) || !isset($input['password']) || !isset($input['csrf_token'])) {
    $rateLimiter->logAttempt($clientIP);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$auth = new Auth();

if (!$auth->validateCSRF($input['csrf_token'])) {
    $rateLimiter->logAttempt($clientIP);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$email = trim($input['email']);
$password = $input['password'];

if (empty($email) || empty($password)) {
    $rateLimiter->logAttempt($clientIP);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

if (strlen($password) > 255) {
    $rateLimiter->logAttempt($clientIP);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid password length']);
    exit;
}

$rateLimiter->logAttempt($clientIP);
$result = $auth->login($email, $password);

if (!$result['success']) {
    http_response_code(401);
}

$rateLimiter->cleanOldAttempts();
echo json_encode($result);
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-commerce Store</title>
</head>
<body>
    <div id="login-container">
        <h2>Login to Your Account</h2>
        <form id="loginForm">
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <input type="hidden" id="csrf_token" name="csrf_token">
            <button type="submit" id="loginBtn">Login</button>
        </form>
        <div id="message"></div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetchCSRFToken();
            
            document.getElementById('loginForm').addEventListener('submit', function(e) {
                e.preventDefault();
                handleLogin();
            });
        });

        async function fetchCSRFToken() {
            try {
                const response = await fetch('get_csrf.php');
                const data = await response.json();
                if (data.success) {
                    document.getElementById('csrf_token').value = data.token;
                }
            } catch (error) {
                console.error('Error fetching CSRF token:', error);
            }
        }

        async function handleLogin() {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const csrfToken = document.getElementById('csrf_token').value;
            const messageDiv = document.getElementById('message');
            const loginBtn = document.getElementById('loginBtn');

            if (!email || !password) {
                showMessage('Please fill in all fields', 'error');
                return;
            }

            if (!validateEmail(email)) {
                showMessage('Please enter a valid email address', 'error');
                return;
            }

            loginBtn.disabled = true;
            loginBtn.textContent = 'Logging in...';

            try {
                const response = await fetch('login_process.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password,
                        csrf_token: csrfToken
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('Login successful! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1500);
                } else {
                    showMessage(data.message || 'Login failed', 'error');
                    fetchCSRFToken();
                }
            } catch (error) {
                console.error('Login error:', error);
                showMessage('Network error. Please try again.', 'error');
            } finally {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Login';
            }
        }

        function validateEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function showMessage(message, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = message;
            messageDiv.className = type;
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const form = document.getElementById('loginForm');
                if (document.activeElement.form === form) {
                    handleLogin();
                }
            }
        });
    </script>
</body>
</html>


<?php
require_once 'auth.php';

SecurityHeaders::setHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$auth = new Auth();
$token = $auth->
?>