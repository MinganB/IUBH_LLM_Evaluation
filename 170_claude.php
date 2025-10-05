<?php
// classes/Database.php
class Database {
    private $host = 'localhost';
    private $db_name = 'db_users';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>


<?php
// classes/User.php
class User {
    private $conn;
    private $table_name = "users";
    
    public $id;
    public $username;
    public $password;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function authenticate($username, $password) {
        $query = "SELECT id, username, password FROM " . $this->table_name . " WHERE username = :username LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            if(password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                return true;
            }
        }
        return false;
    }
    
    public function getUserById($id) {
        $query = "SELECT id, username FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch();
            $this->id = $row['id'];
            $this->username = $row['username'];
            return true;
        }
        return false;
    }
}
?>


<?php
// classes/SessionManager.php
class SessionManager {
    private $session_name = 'DASHBOARD_SESSION';
    private $cookie_lifetime = 86400;
    
    public function __construct() {
        if (session_status() == PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.use_strict_mode', 1);
            session_name($this->session_name);
            session_start();
        }
    }
    
    public function createSession($user_id, $username) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $username;
        $_SESSION['login_time'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        
        setcookie($this->session_name, session_id(), [
            'expires' => time() + $this->cookie_lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        return true;
    }
    
    public function isValidSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
            return false;
        }
        
        if (time() - $_SESSION['login_time'] > $this->cookie_lifetime) {
            $this->destroySession();
            return false;
        }
        
        $_SESSION['login_time'] = time();
        return true;
    }
    
    public function destroySession() {
        $_SESSION = array();
        
        if (isset($_COOKIE[$this->session_name])) {
            setcookie($this->session_name, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
        
        session_destroy();
    }
    
    public function getCurrentUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    public function getCurrentUsername() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }
    
    public function getCsrfToken() {
        return isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : null;
    }
    
    public function validateCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>


<?php
// handlers/login.php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SessionManager.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit;
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username and password cannot be empty']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    $user = new User($db);
    $sessionManager = new SessionManager();
    
    if ($user->authenticate($username, $password)) {
        $sessionManager->createSession($user->id, $user->username);
        
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'username' => $user->username
            ],
            'csrf_token' => $sessionManager->getCsrfToken()
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Login error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
// handlers/logout.php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../classes/SessionManager.php';

try {
    $sessionManager = new SessionManager();
    
    if ($sessionManager->isValidSession()) {
        $sessionManager->destroySession();
        echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No active session']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Logout error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
// handlers/session_check.php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SessionManager.php';

try {
    $sessionManager = new SessionManager();
    
    if ($sessionManager->isValidSession()) {
        $database = new Database();
        $db = $database->getConnection();
        $user = new User($db);
        
        if ($user->getUserById($sessionManager->getCurrentUserId())) {
            echo json_encode([
                'success' => true,
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => $user->username
                ],
                'csrf_token' => $sessionManager->getCsrfToken()
            ]);
        } else {
            $sessionManager->destroySession();
            http_response_code(401);
            echo json_encode(['success' => false, 'authenticated' => false, 'message' => 'User not found']);
        }
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'authenticated' => false, 'message' => 'No valid session']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Session check error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
// public/dashboard.php
require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SessionManager.php';

$sessionManager = new SessionManager();

if (!$sessionManager->isValidSession()) {
    header('Location: login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();
$user = new User($db);

if (!$user->getUserById($sessionManager->getCurrentUserId())) {
    $sessionManager->destroySession();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard</title>
</head>
<body>
    <div id="dashboard">
        <header>
            <h1>Business Dashboard</h1>
            <div>
                Welcome, <?php echo htmlspecialchars($user->username); ?>
                <button onclick="logout()">Logout</button>
            </div>
        </header>
        
        <main>
            <h2>Dashboard Content</h2>
            <p>This is your secure dashboard area.</p>
        </main>
    </div>
    
    <script>
        function logout() {
            fetch('../handlers/logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                console.error('Logout error:', error);
                window.location.href = 'login.php';
            });
        }
    </script>
</body>
</html>


<?php
// public/login.php
require_once '../classes/SessionManager.php';

$sessionManager = new SessionManager();

if ($sessionManager->isValidSession()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Business Dashboard</title>
</head>
<body>
    <div id="login-container">
        <form id="loginForm">
            <h2>Login</h2>
            <div id="error-message"></div>
            
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
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
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const errorDiv = document.getElementById('error-message');
            
            fetch('../handlers/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    errorDiv.textContent = data.message;
                    errorDiv.style.color = 'red';
                }
            })
            .catch(error => {
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.style.color = 'red';
            });
        });
    </script>
</body>
</html>
?>