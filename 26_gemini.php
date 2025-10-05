<?php
class Database {
    private static $instance = null;
    private $conn;

    private $host = 'localhost';
    private $db_name = 'db_users';
    private $username = 'root';
    private $password = '';

    private function __construct() {
        try {
            $this->conn = new PDO(
                'mysql:host=' . $this->host . ';dbname=' . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            error_log('Connection error: ' . $exception->getMessage());
            die('Database connection failed.');
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
}
?><?php
session_start();

$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = htmlspecialchars($_SESSION['error_message']);
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <div>
        <h2>Login to your account</h2>
        <?php if ($error_message): ?>
            <p style="color: red;"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <form action="../handlers/login.php" method="POST">
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
    $_SESSION['error_message'] = 'Invalid request method.';
    header('Location: ../public/index.php');
    exit;
}

require_once '../classes/Database.php';

$pdo = Database::getInstance();

$username = trim($_POST['username'] ?? '');
$password_input = $_POST['password'] ?? '';

if (empty($username) || empty($password_input)) {
    $_SESSION['error_message'] = 'Please enter both username and password.';
    header('Location: ../public/index.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, username, password, is_active FROM users WHERE username = :username');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && $user['is_active'] && password_verify($password_input, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;

        $update_stmt = $pdo->prepare('UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id');
        $update_stmt->execute(['id' => $user['id']]);

        header('Location: ../public/dashboard.php');
        exit;
    } else {
        $_SESSION['error_message'] = 'Invalid username or password.';
        header('Location: ../public/index.php');
        exit;
    }
} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    $_SESSION['error_message'] = 'An unexpected error occurred. Please try again.';
    header('Location: ../public/index.php');
    exit;
}
?><?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['error_message'] = 'You must be logged in to access this page.';
    header('Location: index.php');
    exit;
}

$username = htmlspecialchars($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <div>
        <h2>Welcome, <?php echo $username; ?>!</h2>
        <p>This is your dashboard. You are successfully logged in.</p>
        <p><a href="logout.php">Logout</a></p>
    </div>
</body>
</html>
?>