<?php
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
require_once '../classes/Database.php';

class User {
    private $db;
    private $table = 'users';

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function authenticate($username, $password) {
        $query = "SELECT id, username, password, is_active FROM " . $this->table . " WHERE username = :username AND is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['password'])) {
                $this->updateLastLogin($row['id']);
                return $row;
            }
        }
        return false;
    }

    private function updateLastLogin($userId) {
        $query = "UPDATE " . $this->table . " SET last_login_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
    }

    public function getUserById($id) {
        $query = "SELECT id, username, last_login_at, is_active FROM " . $this->table . " WHERE id = :id AND is_active = 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if($stmt->rowCount() == 1) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }
}
?>


<?php
session_start();

class Session {
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function login($userData) {
        $_SESSION['user_id'] = $userData['id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['logged_in'] = true;
        session_regenerate_id(true);
    }

    public static function logout() {
        session_unset();
        session_destroy();
    }

    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public static function getUsername() {
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }

    public static function getCsrfToken() {
        return isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : null;
    }

    public static function validateCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
?>


<?php
require_once '../classes/User.php';
require_once '../classes/Session.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

Session::start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password']) || !isset($input['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

if (!Session::validateCsrfToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$username = trim($input['username']);
$password = $input['password'];

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

if (strlen($username) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username too long']);
    exit;
}

try {
    $user = new User();
    $userData = $user->authenticate($username, $password);

    if ($userData) {
        Session::login($userData);
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful',
            'user' => [
                'id' => $userData['id'],
                'username' => $userData['username']
            ]
        ]);
    } else {
        sleep(1);
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>


<?php
require_once '../classes/Session.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

Session::start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['csrf_token']) || !Session::validateCsrfToken($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

Session::logout();
echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
?>


<?php
require_once '../classes/Session.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

Session::start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (Session::isLoggedIn()) {
    echo json_encode([
        'success' => true,
        'authenticated' => true,
        'user' => [
            'id' => Session::getUserId(),
            'username' => Session::getUsername()
        ],
        'csrf_token' => Session::getCsrfToken()
    ]);
} else {
    echo json_encode([
        'success' => true,
        'authenticated' => false,
        'csrf_token' => Session::getCsrfToken()
    ]);
}
?>


html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Commerce</title>
</head>
<body>
    <div id="login-container">
        <h2>Login</h2>
        <form id="login-form">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required maxlength="255">
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
        let csrfToken = '';

        async function getAuthStatus() {
            try {
                const response = await fetch('/handlers/auth_check.php');
                const data = await response.json();
                
                if (data.success) {
                    csrfToken = data.csrf_token;
                    if (data.authenticated) {
                        window.location.href = '/dashboard.php';
                        return;
                    }
                }
            } catch (error) {
                console.error('Auth check failed:', error);
            }
        }

        document.getElementById('login-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const messageDiv = document.getElementById('message');
            
            if (!username.trim() || !password) {
                messageDiv.innerHTML = '<p style="color: red;">Please fill in all fields</p>';
                return;
            }

            try {
                const response = await fetch('/handlers/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        username: username,
                        password: password,
                        csrf_token: csrfToken
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    messageDiv.innerHTML = '<p style="color: green;">' + data.message + '</p>';
                    setTimeout(() => {
                        window.location.href = '/dashboard.php';
                    }, 1000);
                } else {
                    messageDiv.innerHTML = '<p style="color: red;">' + data.message + '</p>';
                }
            } catch (error) {
                messageDiv.innerHTML = '<p style="color: red;">Login failed. Please try again.</p>';
                console.error('Login error:', error);
            }
            
            document.getElementById('password').value = '';
        });

        getAuthStatus();
    </script>
</body>
</html>
?>