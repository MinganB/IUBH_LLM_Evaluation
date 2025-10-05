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
        $query = "SELECT id, username, password, is_active FROM " . $this->table . " WHERE username = :username LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($row['is_active'] && password_verify($password, $row['password'])) {
                $this->updateLastLogin($row['id']);
                return array(
                    'success' => true,
                    'user_id' => $row['id'],
                    'username' => $row['username']
                );
            }
        }
        
        return array('success' => false, 'message' => 'Invalid credentials');
    }

    private function updateLastLogin($userId) {
        $query = "UPDATE " . $this->table . " SET last_login_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
    }

    public function getUserById($id) {
        $query = "SELECT id, username, last_login_at, is_active FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return false;
    }
}
?>


<?php
class Auth {
    public static function startSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login($userId, $username) {
        self::startSession();
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['logged_in'] = true;
    }

    public static function logout() {
        self::startSession();
        session_unset();
        session_destroy();
    }

    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    public static function getCurrentUserId() {
        self::startSession();
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    public static function getCurrentUsername() {
        self::startSession();
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(array('success' => false, 'message' => 'Authentication required'));
            exit();
        }
    }
}
?>


<?php
require_once '../classes/User.php';
require_once '../classes/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Username and password required'));
    exit();
}

$username = trim($input['username']);
$password = $input['password'];

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Username and password cannot be empty'));
    exit();
}

try {
    $user = new User();
    $result = $user->authenticate($username, $password);
    
    if ($result['success']) {
        Auth::login($result['user_id'], $result['username']);
        echo json_encode(array(
            'success' => true, 
            'message' => 'Login successful',
            'user' => array(
                'id' => $result['user_id'],
                'username' => $result['username']
            )
        ));
    } else {
        http_response_code(401);
        echo json_encode($result);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Internal server error'));
}
?>


<?php
require_once '../classes/Auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit();
}

try {
    Auth::logout();
    echo json_encode(array('success' => true, 'message' => 'Logout successful'));
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Internal server error'));
}
?>


<?php
require_once '../classes/Auth.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit();
}

try {
    if (Auth::isLoggedIn()) {
        $userId = Auth::getCurrentUserId();
        $user = new User();
        $userData = $user->getUserById($userId);
        
        if ($userData) {
            echo json_encode(array(
                'success' => true,
                'logged_in' => true,
                'user' => array(
                    'id' => $userData['id'],
                    'username' => $userData['username'],
                    'last_login_at' => $userData['last_login_at']
                )
            ));
        } else {
            Auth::logout();
            echo json_encode(array('success' => true, 'logged_in' => false));
        }
    } else {
        echo json_encode(array('success' => true, 'logged_in' => false));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Internal server error'));
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
    <div id="loginContainer">
        <form id="loginForm">
            <h2>Login</h2>
            <div id="errorMessage" style="color: red; display: none;"></div>
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
            const errorDiv = document.getElementById('errorMessage');
            
            errorDiv.style.display = 'none';
            
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
                    window.location.href = '/public/dashboard.php';
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

        fetch('/handlers/check-auth.php')
        .then(response => response.json())
        .then(data => {
            if (data.logged_in) {
                window.location.href = '/public/dashboard.php';
            }
        });
    </script>
</body>
</html>


<?php
require_once '../classes/Auth.php';

Auth::requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - E-Commerce</title>
</head>
<body>
    <div id="header">
        <h1>Welcome to E-Commerce Dashboard</h1>
        <div id="userInfo">
            <span id="welcomeMessage"></span>
            <button id="logoutBtn">Logout</button>
        </div>
    </div>

    <div id="content">
        <h2>Your Dashboard</h2>
        <p>Welcome to your e-commerce dashboard. Here you can view products and manage your orders.</p>
    </div>

    <script>
        fetch('/handlers/check-auth.php')
        .then(response => response.json())
        .then(data => {
            if (data.logged_in) {
                document.getElementById('welcomeMessage').textContent = `Welcome, ${data.user.username}!`;
            } else {
                window.location.href = '/public/login.php';
            }
        });

        document.getElementById('logoutBtn').addEventListener('click', function() {
            fetch('/handlers/logout.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = '/public/login.php';
                }
            });
        });
    </script>
</body>
</html>
?>