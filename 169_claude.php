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
        } catch(PDOException $e) {
            throw new Exception("Connection failed: " . $e->getMessage());
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
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("SELECT id, username, password FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['logged_in'] = true;
                
                $sessionId = session_id();
                setcookie('session_token', $sessionId, time() + (86400 * 30), '/', '', false, true);
                
                return true;
            }
            return false;
        } catch(Exception $e) {
            return false;
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    public function getUsername() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }
    
    public function logout() {
        session_destroy();
        setcookie('session_token', '', time() - 3600, '/', '', false, true);
        return true;
    }
    
    public function validateSession() {
        if (isset($_COOKIE['session_token'])) {
            $sessionId = $_COOKIE['session_token'];
            session_id($sessionId);
            session_start();
            return $this->isLoggedIn();
        }
        return false;
    }
}
?>


<?php
// /handlers/login.php
require_once '../classes/Database.php';
require_once '../classes/SessionManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Username and password required']);
    exit;
}

try {
    $sessionManager = new SessionManager();
    $loginSuccess = $sessionManager->login($input['username'], $input['password']);
    
    if ($loginSuccess) {
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful',
            'user' => [
                'id' => $sessionManager->getUserId(),
                'username' => $sessionManager->getUsername()
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Login failed']);
}
?>


<?php
// /handlers/logout.php
require_once '../classes/Database.php';
require_once '../classes/SessionManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $sessionManager = new SessionManager();
    $sessionManager->logout();
    echo json_encode(['success' => true, 'message' => 'Logout successful']);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Logout failed']);
}
?>


<?php
// /handlers/session_check.php
require_once '../classes/Database.php';
require_once '../classes/SessionManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $sessionManager = new SessionManager();
    
    if ($sessionManager->isLoggedIn() || $sessionManager->validateSession()) {
        echo json_encode([
            'success' => true, 
            'logged_in' => true,
            'user' => [
                'id' => $sessionManager->getUserId(),
                'username' => $sessionManager->getUsername()
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'logged_in' => false, 'message' => 'No active session']);
    }
} catch(Exception $e) {
    echo json_encode(['success' => false, 'logged_in' => false, 'message' => 'Session validation failed']);
}
?>


<?php
// /public/dashboard.php
require_once '../classes/Database.php';
require_once '../classes/SessionManager.php';

$sessionManager = new SessionManager();

if (!$sessionManager->isLoggedIn() && !$sessionManager->validateSession()) {
    header('Location: login.php');
    exit;
}

$userId = $sessionManager->getUserId();
$username = $sessionManager->getUsername();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Business Dashboard</title>
</head>
<body>
    <div class="dashboard-container">
        <header>
            <h1>Business Dashboard</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
                <button onclick="logout()">Logout</button>
            </div>
        </header>
        
        <main>
            <div class="dashboard-content">
                <h2>Dashboard Content</h2>
                <p>User ID: <?php echo htmlspecialchars($userId); ?></p>
                <p>This is your protected dashboard content.</p>
            </div>
        </main>
    </div>

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
                    window.location.href = 'login.php';
                } else {
                    alert('Logout failed: ' + data.message);
                }
            });
        }
    </script>
</body>
</html>


<?php
// /public/login.php
require_once '../classes/Database.php';
require_once '../classes/SessionManager.php';

$sessionManager = new SessionManager();

if ($sessionManager->isLoggedIn() || $sessionManager->validateSession()) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Business Dashboard</title>
</head>
<body>
    <div class="login-container">
        <h1>Login</h1>
        <form id="loginForm">
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
        <div id="message"></div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
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
                    window.location.href = 'dashboard.php';
                } else {
                    document.getElementById('message').innerHTML = '<p>Error: ' + data.message + '</p>';
                }
            });
        });
    </script>
</body>
</html>
?>