<?php
// classes/Database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'your_db_username';
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
?>


<?php
// classes/Auth.php
class Auth {
    private $db;
    private $maxLoginAttempts = 5;
    private $lockoutTime = 900;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->createLoginAttemptsTable();
    }
    
    private function createLoginAttemptsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success BOOLEAN DEFAULT FALSE,
            INDEX idx_username_ip (username, ip_address),
            INDEX idx_attempt_time (attempt_time)
        )";
        $this->db->exec($sql);
    }
    
    public function login($username, $password, $ipAddress) {
        $username = $this->sanitizeInput($username);
        
        if ($this->isAccountLocked($username, $ipAddress)) {
            $this->logLoginAttempt($username, $ipAddress, false);
            return ['success' => false, 'message' => 'Account temporarily locked due to multiple failed attempts'];
        }
        
        $stmt = $this->db->prepare("SELECT id, username, password, is_active FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            $this->logLoginAttempt($username, $ipAddress, false);
            return ['success' => false, 'message' => 'Invalid credentials'];
        }
        
        $this->updateLastLogin($user['id']);
        $this->logLoginAttempt($username, $ipAddress, true);
        $this->clearFailedAttempts($username, $ipAddress);
        $this->createSession($user);
        
        return ['success' => true, 'message' => 'Login successful'];
    }
    
    private function isAccountLocked($username, $ipAddress) {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as attempts FROM login_attempts 
             WHERE username = ? AND ip_address = ? AND success = 0 
             AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$username, $ipAddress, $this->lockoutTime]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= $this->maxLoginAttempts;
    }
    
    private function logLoginAttempt($username, $ipAddress, $success) {
        $stmt = $this->db->prepare(
            "INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)"
        );
        $stmt->execute([$username, $ipAddress, $success]);
        
        $logMessage = sprintf(
            "[%s] Login %s - Username: %s, IP: %s",
            date('Y-m-d H:i:s'),
            $success ? 'SUCCESS' : 'FAILED',
            $username,
            $ipAddress
        );
        error_log($logMessage, 3, '/var/log/auth.log');
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$userId]);
    }
    
    private function clearFailedAttempts($username, $ipAddress) {
        $stmt = $this->db->prepare(
            "DELETE FROM login_attempts WHERE username = ? AND ip_address = ? AND success = 0"
        );
        $stmt->execute([$username, $ipAddress]);
    }
    
    private function createSession($user) {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.cookie_samesite', 'Strict');
            session_start();
        }
        
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();
    }
    
    public function isLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
    
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }
    
    private function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }
}
?>


<?php
// handlers/login_handler.php
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['username']) || !isset($_POST['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$username = $_POST['username'];
$password = $_POST['password'];
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

if (strlen($username) > 255 || strlen($password) > 255) {
    echo json_encode(['success' => false, 'message' => 'Invalid input length']);
    exit;
}

try {
    $auth = new Auth();
    $result = $auth->login($username, $password, $ipAddress);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(401);
    }
    
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred during login']);
}
?>


<?php
// public/login.php
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

$auth = new Auth();

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if (isset($_GET['error'])) {
    $error = htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    if (!empty($username) && !empty($password)) {
        try {
            $result = $auth->login($username, $password, $ipAddress);
            
            if ($result['success']) {
                header('Location: dashboard.php');
                exit;
            } else {
                $error = $result['message'];
            }
        } catch (Exception $e) {
            $error = 'An error occurred during login';
        }
    } else {
        $error = 'Please enter both username and password';
    }
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
        <h2>Login</h2>
        
        <?php if (!empty($error)): ?>
        <div class="error-message">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php" id="loginForm">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required maxlength="255" 
                       value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required maxlength="255">
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please enter both username and password');
                return;
            }
            
            if (username.length > 255 || password.length > 255) {
                e.preventDefault();
                alert('Username and password must be less than 255 characters');
                return;
            }
        });
    </script>
</body>
</html>


<?php
// public/dashboard.php
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (isset($_POST['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - E-Commerce</title>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Welcome to your Dashboard</h1>
            <p>Hello, <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?>!</p>
        </header>
        
        <nav>
            <ul>
                <li><a href="#products">View Products</a></li>
                <li><a href="#orders">Manage Orders</a></li>
                <li><a href="#profile">Profile Settings</a></li>
            </ul>
        </nav>
        
        <main>
            <section id="products">
                <h2>Products</h2>
                <p>Browse and manage your product catalog.</p>
            </section>
            
            <section id="orders">
                <h2>Orders</h2>
                <p>View and manage your orders.</p>
            </section>
            
            <section id="profile">
                <h2>Profile</h2>
                <p>Update your profile information.</p>
            </section>
        </main>
        
        <form method="POST" action="dashboard.php">
            <button type="submit" name="logout">Logout</button>
        </form>
    </div>
</body>
</html>


<?php
// handlers/logout_handler.php
require_once '../classes/Database.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $auth = new Auth();
    $auth->logout();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred during logout']);
}
?>