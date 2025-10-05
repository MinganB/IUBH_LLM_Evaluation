**classes/SessionManager.php**
<?php
class SessionManager {
    private $db;
    private $sessionTimeout = 1800; // 30 minutes
    private $logFile;
    
    public function __construct($database, $logFile = '/var/log/sessions.log') {
        $this->db = $database;
        $this->logFile = $logFile;
        $this->configureSession();
    }
    
    private function configureSession() {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.gc_maxlifetime', $this->sessionTimeout);
    }
    
    public function startSession($userId) {
        session_start();
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['start_time'] = time();
        $_SESSION['last_activity'] = time();
        
        $sessionId = session_id();
        
        setcookie(session_name(), $sessionId, [
            'expires' => time() + $this->sessionTimeout,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        $this->logSessionEvent('LOGIN', $userId, $sessionId);
        
        return $sessionId;
    }
    
    public function validateSession() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['start_time'])) {
            return false;
        }
        
        if (time() - $_SESSION['start_time'] > $this->sessionTimeout) {
            $this->destroySession();
            return false;
        }
        
        if (time() - $_SESSION['last_activity'] > 900) { // 15 minutes inactivity
            $this->destroySession();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function destroySession() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $sessionId = session_id();
        
        $_SESSION = array();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
        
        session_destroy();
        
        if ($userId) {
            $this->logSessionEvent('LOGOUT', $userId, $sessionId);
        }
    }
    
    public function getUserId() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    private function logSessionEvent($event, $userId, $sessionId) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] %s - User ID: %s - Session ID: %s\n", $timestamp, $event, $userId, $sessionId);
        
        if (is_writable(dirname($this->logFile))) {
            file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }
}


**classes/User.php**
<?php
class User {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function authenticate($username, $password) {
        $stmt = $this->db->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                return $row;
            }
        }
        
        return false;
    }
    
    public function getUserById($id) {
        $stmt = $this->db->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
}


**handlers/session_handler.php**
<?php
header('Content-Type: application/json');

require_once '../classes/SessionManager.php';
require_once '../classes/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action required']);
    exit;
}

$mysqli = new mysqli('localhost', 'username', 'password', 'db_users');

if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$sessionManager = new SessionManager($mysqli);
$user = new User($mysqli);

switch ($input['action']) {
    case 'login':
        if (!isset($input['username']) || !isset($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username and password required']);
            exit;
        }
        
        $userData = $user->authenticate($input['username'], $input['password']);
        
        if ($userData) {
            $sessionId = $sessionManager->startSession($userData['id']);
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $userData['id'],
                    'username' => $userData['username']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
        break;
        
    case 'logout':
        $sessionManager->destroySession();
        echo json_encode(['success' => true, 'message' => 'Logout successful']);
        break;
        
    case 'validate':
        if ($sessionManager->validateSession()) {
            $userId = $sessionManager->getUserId();
            $userData = $user->getUserById($userId);
            echo json_encode([
                'success' => true,
                'user' => $userData
            ]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Invalid session']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

$mysqli->close();


**public/dashboard.php**
<?php
require_once '../classes/SessionManager.php';
require_once '../classes/User.php';

$mysqli = new mysqli('localhost', 'username', 'password', 'db_users');

if ($mysqli->connect_error) {
    die('Database connection failed');
}

$sessionManager = new SessionManager($mysqli);

if (!$sessionManager->validateSession()) {
    header('Location: login.php');
    exit;
}

$user = new User($mysqli);
$userId = $sessionManager->getUserId();
$userData = $user->getUserById($userId);

if (!$userData) {
    $sessionManager->destroySession();
    header('Location: login.php');
    exit;
}

$mysqli->close();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Business Dashboard</title>
</head>
<body>
    <h1>Welcome to Business Dashboard</h1>
    <p>Hello, <?php echo htmlspecialchars($userData['username']); ?>!</p>
    
    <div id="dashboard-content">
        <h2>Dashboard Overview</h2>
        <div id="stats">
            <p>User ID: <?php echo htmlspecialchars($userData['id']); ?></p>
            <p>Session Status: Active</p>
        </div>
    </div>
    
    <button onclick="logout()">Logout</button>
    
    <script>
        function logout() {
            fetch('../handlers/session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'logout'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        setInterval(function() {
            fetch('../handlers/session_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'validate'
                })
            })
            .then(response => {
                if (!response.ok) {
                    window.location.href = 'login.php';
                }
            })
            .catch(error => {
                window.location.href = 'login.php';
            });
        }, 60000);
    </script>
</body>
</html>