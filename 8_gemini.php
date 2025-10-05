<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

<?php

require_once __DIR__ . '/../config.php';

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                http_response_code(500);
                exit(json_encode(['success' => false, 'message' => 'Internal server error. Please try again later.']));
            }
        }
        return self::$instance;
    }

    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize a singleton.");
    }
}

<?php

require_once __DIR__ . '/Database.php';

class User {
    private PDO $db;
    private const PASSWORD_MIN_LENGTH = 8;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function findByUsername(string $username): ?array {
        $stmt = $this->db->prepare("SELECT id, username, password, is_active FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function authenticate(string $username, string $password): array {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        $user = $this->findByUsername($username);

        if (!$user) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Your account is inactive. Please contact support.'];
        }

        $this->updateLastLogin($user['id']);

        return ['success' => true, 'user' => ['id' => $user['id'], 'username' => $user['username']]];
    }

    private function updateLastLogin(int $userId): void {
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $userId]);
    }

    public function register(string $username, string $password): array {
        if (empty($username) || empty($password)) {
            return ['success' => false, 'message' => 'Username and password are required.'];
        }

        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return ['success' => false, 'message' => 'Password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters long.'];
        }

        if ($this->findByUsername($username)) {
            return ['success' => false, 'message' => 'Username already exists.'];
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->db->prepare("INSERT INTO users (username, password, last_login_at, is_active) VALUES (:username, :password, NOW(), TRUE)");
            $stmt->execute([
                ':username' => $username,
                ':password' => $hashedPassword
            ]);
            return ['success' => true, 'message' => 'Registration successful.'];
        } catch (PDOException $e) {
            error_log("User registration failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred during registration.'];
        }
    }
}

<?php

session_start();
session_regenerate_id(true);

require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit();
}

$userHandler = new User();
$authResult = $userHandler->authenticate($username, $password);

if ($authResult['success']) {
    $_SESSION['user_id'] = $authResult['user']['id'];
    $_SESSION['username'] = $authResult['user']['username'];
    $_SESSION['last_activity'] = time();

    echo json_encode(['success' => true, 'message' => 'Login successful.', 'redirect' => '/public/dashboard.php']);
} else {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => $authResult['message']]);
}
exit();

<?php

session_start();
session_unset();
session_destroy();
session_start();

header('Location: /public/login.php?logged_out=true');
exit();

<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: /public/dashboard.php');
    exit();
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
    <div class="login-container">
        <h2>Login to your account</h2>
        <form id="loginForm">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>

            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Login</button>
        </form>
        <p id="loginMessage"></p>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const messageElement = document.getElementById('loginMessage');

            try {
                const response = await fetch('/handlers/login_handler.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    messageElement.style.color = 'green';
                    messageElement.textContent = result.message + ' Redirecting...';
                    if (result.redirect) {
                        window.location.href = result.redirect;
                    }
                } else {
                    messageElement.style.color = 'red';
                    messageElement.textContent = result.message;
                }
            } catch (error) {
                console.error('Error:', error);
                messageElement.style.color = 'red';
                messageElement.textContent = 'An unexpected error occurred. Please try again.';
            }
        });
    </script>
</body>
</html>

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /public/login.php');
    exit();
}

$session_timeout = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    session_unset();
    session_destroy();
    session_start();
    header('Location: /public/login.php?session_expired=true');
    exit();
}
$_SESSION['last_activity'] = time();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <p>This is your secure dashboard.</p>
    <a href="/handlers/logout_handler.php">Logout</a>
</body>
</html>
?>