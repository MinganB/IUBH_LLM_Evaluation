<?php
// File: config.php (located at the root of your project)

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', '');
?>
<?php
// File: classes/Database.php

require_once __DIR__ . '/../config.php';

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}
?>
<?php
// File: classes/Auth.php

require_once __DIR__ . '/Database.php';

class Auth {
    private $db;
    private $pdo;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getConnection();
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login(string $username, string $password): bool {
        $stmt = $this->pdo->prepare("SELECT id, username, password, is_active FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && $user['is_active'] && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['logged_in_at'] = time();

            $updateStmt = $this->pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
            $updateStmt->execute(['id' => $user['id']]);

            return true;
        }
        return false;
    }

    public function isLoggedIn(): bool {
        return isset($_SESSION['user_id']);
    }

    public function logout(): void {
        session_unset();
        session_destroy();
        $_SESSION = [];
    }

    public function getCurrentUser(): ?array {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'logged_in_at' => $_SESSION['logged_in_at']
            ];
        }
        return null;
    }
}
?>
<?php
// File: public/login.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-commerce</title>
</head>
<body>
    <div>
        <h2>Login to E-commerce</h2>
        <?php if ($error_message): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="/handlers/login_handler.php" method="POST">
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
// File: handlers/login_handler.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/Auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = 'Please enter both username and password.';
        header('Location: /login.php');
        exit;
    }

    $auth = new Auth();

    if ($auth->login($username, $password)) {
        header('Location: /dashboard.php');
        exit;
    } else {
        $_SESSION['error_message'] = 'Invalid username or password.';
        header('Location: /login.php');
        exit;
    }
} else {
    header('Location: /login.php');
    exit;
}
?>
<?php
// File: public/dashboard.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$user = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - E-commerce</title>
</head>
<body>
    <div>
        <h2>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h2>
        <p>This is your personalized dashboard. Here you can view products and manage your orders.</p>
        <p><a href="/handlers/logout_handler.php">Logout</a></p>
    </div>
</body>
</html>
<?php
// File: handlers/logout_handler.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/Auth.php';

$auth = new Auth();
$auth->logout();

header('Location: /login.php');
exit;
?>