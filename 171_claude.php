<?php
// /classes/Database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'root';
    private $password = '';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
// /classes/SessionManager.php
class SessionManager {
    private $db;
    private $sessionTimeout = 1800;
    private $logFile = '/secure/logs/session.log';

    public function __construct() {
        $this->db = new Database();
        $this->initializeSessionTable();
    }

    private function initializeSessionTable() {
        $sql = "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_id VARCHAR(255) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            is_active TINYINT(1) DEFAULT 1,
            INDEX idx_session_id (session_id),
            INDEX idx_user_id (user_id)
        )";
        $this->db->getConnection()->exec($sql);
    }

    public function createSession($userId) {
        session_regenerate_id(true);
        $sessionId = session_id();
        
        $this->destroyExistingUserSessions($userId);

        $sql = "INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent) VALUES (?, ?, ?, ?)";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([
            $userId,
            $sessionId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);

        $_SESSION['user_id'] = $userId;
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['created_at'] = time();
        $_SESSION['last_activity'] = time();

        $this->setCookie($sessionId);
        $this->logSessionEvent('SESSION_CREATED', $userId, $sessionId);

        return $sessionId;
    }

    private function setCookie($sessionId) {
        $cookieOptions = [
            'expires' => time() + $this->sessionTimeout,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        
        setcookie('PHPSESSID', $sessionId, $cookieOptions);
    }

    public function validateSession() {
        if (!isset($_SESSION['session_id'])) {
            return false;
        }

        $sessionId = $_SESSION['session_id'];
        
        $sql = "SELECT user_id, last_activity FROM user_sessions WHERE session_id = ? AND is_active = 1";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            $this->destroySession();
            return false;
        }

        if (time() - strtotime($session['last_activity']) > $this->sessionTimeout) {
            $this->destroySession();
            $this->logSessionEvent('SESSION_TIMEOUT', $session['user_id'], $sessionId);
            return false;
        }

        $this->updateSessionActivity($sessionId);
        $_SESSION['last_activity'] = time();
        
        return true;
    }

    private function updateSessionActivity($sessionId) {
        $sql = "UPDATE user_sessions SET last_activity = CURRENT_TIMESTAMP WHERE session_id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$sessionId]);
    }

    public function destroySession() {
        if (isset($_SESSION['session_id'])) {
            $sessionId = $_SESSION['session_id'];
            $userId = $_SESSION['user_id'] ?? null;

            $sql = "UPDATE user_sessions SET is_active = 0 WHERE session_id = ?";
            $stmt = $this->db->getConnection()->prepare($sql);
            $stmt->execute([$sessionId]);

            $this->logSessionEvent('SESSION_DESTROYED', $userId, $sessionId);
        }

        session_unset();
        session_destroy();
        
        setcookie('PHPSESSID', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
        ]);
    }

    private function destroyExistingUserSessions($userId) {
        $sql = "UPDATE user_sessions SET is_active = 0 WHERE user_id = ? AND is_active = 1";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$userId]);
    }

    public function cleanupExpiredSessions() {
        $sql = "UPDATE user_sessions SET is_active = 0 WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND) AND is_active = 1";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$this->sessionTimeout]);
    }

    private function logSessionEvent($event, $userId, $sessionId) {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry = "[{$timestamp}] {$event} - User ID: {$userId}, Session ID: {$sessionId}, IP: {$ipAddress}" . PHP_EOL;
        
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0700, true);
        }
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
}
?>


<?php
// /classes/User.php
class User {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    public function authenticate($username, $password) {
        $sql = "SELECT id, username, password FROM users WHERE username = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }

        return false;
    }

    public function getUserById($userId) {
        $sql = "SELECT id, username FROM users WHERE id = ?";
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>


<?php
// /handlers/login.php
session_start();
header('Content-Type: application/json');

require_once '../classes/Database.php';
require_once '../classes/User.php';
require_once '../classes/SessionManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

try {
    $user = new User();
    $sessionManager = new SessionManager();

    $userData = $user->authenticate($input['username'], $input['password']);

    if ($userData) {
        $sessionId = $sessionManager->createSession($userData['id']);
        
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
// /handlers/logout.php
session_start();
header('Content-Type: application/json');

require_once '../classes/Database.php';
require_once '../classes/SessionManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $sessionManager = new SessionManager();
    $sessionManager->destroySession();
    
    echo json_encode(['success' => true, 'message' => 'Logout successful']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
// /handlers/session_check.php
session_start();
header('Content-Type: application/json');

require_once '../classes/Database.php';
require_once '../classes/SessionManager.php';
require_once '../classes/User.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $sessionManager = new SessionManager();
    
    if ($sessionManager->validateSession()) {
        $userId = $sessionManager->getCurrentUserId();
        $user = new User();
        $userData = $user->getUserById($userId);
        
        echo json_encode([
            'success' => true,
            'authenticated' => true,
            'user' => $userData
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'authenticated' => false,
            'message' => 'Invalid or expired session'
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
// /public/dashboard.php
session_start();

require_once '../classes/Database.php';
require_once '../classes/SessionManager.php';
require_once '../classes/User.php';

$sessionManager = new SessionManager();

if (!$sessionManager->validateSession()) {
    header('Location: login.html');
    exit;
}

$userId = $sessionManager->getCurrentUserId();
$user = new User();
$userData = $user->getUserById($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard</title>
</head>
<body>
    <header>
        <h1>Business Dashboard</h1>
        <div>
            Welcome, <?php echo htmlspecialchars($userData['username']); ?>
            <button onclick="logout()">Logout</button>
        </div>
    </header>

    <main>
        <h2>Dashboard Content</h2>
        <p>This is your secure dashboard area.</p>
    </main>

    <script>
        function logout() {
            fetch('/handlers/logout.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'login.html';
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        setInterval(() => {
            fetch('/handlers/session_check.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (!data.authenticated) {
                    window.location.href = 'login.html';
                }
            })
            .catch(error => {
                console.error('Session check error:', error);
            });
        }, 60000);
    </script>
</body>
</html>


<?php
// /handlers/cleanup_sessions.php
require_once '../classes/Database.php';
require_once '../classes/SessionManager.php';

$sessionManager = new SessionManager();
$sessionManager->cleanupExpiredSessions();
?>