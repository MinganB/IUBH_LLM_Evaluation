<?php
class DatabaseConnection {
    private $host = 'localhost';
    private $dbname = 'db_users';
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
        } catch (PDOException $e) {
            throw new Exception('Database connection failed');
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
class User {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function authenticate($username, $password) {
        $stmt = $this->pdo->prepare("SELECT id, password, is_active FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
            $this->updateLastLogin($user['id']);
            return $user['id'];
        }
        return false;
    }

    private function updateLastLogin($userId) {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$userId]);
    }
}
?>


<?php
session_start();
require_once '../classes/DatabaseConnection.php';
require_once '../classes/User.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

try {
    $db = new DatabaseConnection();
    $user = new User($db->getConnection());
    
    $userId = $user->authenticate($username, $password);
    
    if ($userId) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['last_activity'] = time();
        
        echo json_encode(['success' => true, 'redirect' => '/public/dashboard.php']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Authentication service unavailable']);
}
?>


<?php
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <form id="loginForm">
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div>
            <button type="submit">Login</button>
        </div>
        <div id="errorMessage"></div>
    </form>

    <script>
    document.getElementById('loginForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const errorDiv = document.getElementById('errorMessage');
        
        fetch('/handlers/login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = data.redirect;
            } else {
                errorDiv.textContent = data.message;
            }
        })
        .catch(error => {
            errorDiv.textContent = 'An error occurred. Please try again.';
        });
    });
    </script>
</body>
</html>


<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header('Location: /public/login.php');
    exit;
}

$_SESSION['last_activity'] = time();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <p>You have successfully logged in to your dashboard.</p>
    <a href="/handlers/logout.php">Logout</a>
</body>
</html>


<?php
session_start();
session_unset();
session_destroy();
header('Content-Type: application/json');
echo json_encode(['success' => true, 'redirect' => '/public/login.php']);
?>