<?php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php
        session_start();
        if (isset($_SESSION['error'])) {
            echo '<div class="error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
        ?>
        <form action="login.php" method="POST">
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
        </form>
    </div>
</body>
</html>


<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.html');
    exit();
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Username and password are required';
    header('Location: login.html');
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    $stmt = $pdo->prepare('SELECT id, password, is_active FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['is_active'] || !password_verify($password, $user['password'])) {
        $_SESSION['error'] = 'Invalid username or password';
        header('Location: login.html');
        exit();
    }
    
    $updateStmt = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $updateStmt->execute([$user['id']]);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $username;
    $_SESSION['logged_in'] = true;
    
    header('Location: dashboard.php');
    exit();
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Database connection failed';
    header('Location: login.html');
    exit();
}
?>


<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <div class="dashboard">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
        <div class="navigation">
            <a href="products.php">View Products</a>
            <a href="orders.php">Manage Orders</a>
            <a href="logout.php">Logout</a>
        </div>
        <div class="content">
            <h2>Dashboard</h2>
            <p>Welcome to your e-commerce dashboard. Use the navigation above to browse products and manage your orders.</p>
        </div>
    </div>
</body>
</html>


<?php
session_start();
session_destroy();
header('Location: login.html');
exit();
?>


<?php
class DatabaseConnection {
    private $pdo;
    
    public function __construct() {
        try {
            $this->pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
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
require_once '../classes/DatabaseConnection.php';

class AuthHandler {
    private $db;
    
    public function __construct() {
        $dbConnection = new DatabaseConnection();
        $this->db = $dbConnection->getConnection();
    }
    
    public function authenticate($username, $password) {
        try {
            $stmt = $this->db->prepare('SELECT id, password, is_active FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user || !$user['is_active'] || !password_verify($password, $user['password'])) {
                return ['success' => false, 'message' => 'Invalid credentials'];
            }
            
            $this->updateLastLogin($user['id']);
            
            return ['success' => true, 'user_id' => $user['id'], 'username' => $username];
            
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }
}
?>


<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit();
}

require_once '../handlers/AuthHandler.php';

try {
    $authHandler = new AuthHandler();
    $result = $authHandler->authenticate($username, $password);
    
    if ($result['success']) {
        $_SESSION['user_id'] = $result['user_id'];
        $_SESSION['username'] = $result['username'];
        $_SESSION['logged_in'] = true;
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Authentication service unavailable']);
}
?>