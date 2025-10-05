<?php

// File: classes/DBConnector.php

class DBConnector {
    private static ?PDO $pdo = null;

    private function __construct() {}

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $host = 'localhost';
            $db   = 'db_users';
            $user = 'root';
            $pass = '';
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (\PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                die(json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']));
            }
        }
        return self::$pdo;
    }
}
?>
<?php

// File: classes/SessionManager.php

class SessionManager {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            $params = session_get_cookie_params();
            session_set_cookie_params([
                'lifetime' => $params['lifetime'],
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }

    public static function set(string $key, mixed $value): void {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key): mixed {
        self::start();
        return $_SESSION[$key] ?? null;
    }

    public static function isLoggedIn(): bool {
        self::start();
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    public static function destroy(): void {
        self::start();

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite']
            ]);
        }

        session_destroy();
    }

    public static function regenerateId(): void {
        self::start();
        session_regenerate_id(true);
    }
}
?>
<?php

// File: classes/UserManager.php

require_once __DIR__ . '/DBConnector.php';

class UserManager {
    private PDO $pdo;

    public function __construct() {
        $this->pdo = DBConnector::getConnection();
    }

    public function authenticate(string $username, string $password): ?array {
        $stmt = $this->pdo->prepare("SELECT id, username, password FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            return [
                'id' => $user['id'],
                'username' => $user['username']
            ];
        }

        return null;
    }

    public function createUser(string $username, string $password): bool {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->pdo->prepare("INSERT INTO users (username, password) VALUES (:username, :password)");
        try {
            return $stmt->execute(['username' => $username, 'password' => $hashedPassword]);
        } catch (\PDOException $e) {
            error_log("Error creating user: " . $e->getMessage());
            return false;
        }
    }
}
?>
<?php

// File: handlers/login_handler.php

require_once __DIR__ . '/../classes/SessionManager.php';
require_once __DIR__ . '/../classes/UserManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

$userManager = new UserManager();
$user = $userManager->authenticate($username, $password);

if ($user) {
    SessionManager::start();
    SessionManager::regenerateId();
    SessionManager::set('user_id', $user['id']);
    SessionManager::set('username', $user['username']);
    echo json_encode(['success' => true, 'message' => 'Login successful.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
}
?>
<?php

// File: handlers/logout_handler.php

require_once __DIR__ . '/../classes/SessionManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

SessionManager::destroy();
echo json_encode(['success' => true, 'message' => 'Logout successful.']);
?>
<?php

// File: public/index.php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>
    <form id="loginForm">
        <label for="username">Username:</label><br>
        <input type="text" id="username" name="username" required><br><br>
        <label for="password">Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        <button type="submit">Login</button>
    </form>
    <div id="message"></div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(event) {
            event.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const messageDiv = document.getElementById('message');

            try {
                const response = await fetch('/handlers/login_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ username, password })
                });

                const data = await response.json();

                if (data.success) {
                    messageDiv.textContent = data.message;
                    messageDiv.style.color = 'green';
                    window.location.href = '/public/dashboard.php';
                } else {
                    messageDiv.textContent = data.message;
                    messageDiv.style.color = 'red';
                }
            } catch (error) {
                console.error('Error:', error);
                messageDiv.textContent = 'An unexpected error occurred.';
                messageDiv.style.color = 'red';
            }
        });
    </script>
</body>
</html>
<?php

// File: public/dashboard.php

require_once __DIR__ . '/../classes/SessionManager.php';

SessionManager::start();

if (!SessionManager::isLoggedIn()) {
    header('Location: /public/index.php');
    exit;
}

$username = htmlspecialchars(SessionManager::get('username'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome to the Dashboard, <?php echo $username; ?>!</h2>
    <p>This is your secure business dashboard content.</p>
    <button id="logoutButton">Logout</button>

    <script>
        document.getElementById('logoutButton').addEventListener('click', async function() {
            try {
                const response = await fetch('/handlers/logout_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    window.location.href = '/public/index.php';
                } else {
                    alert('Logout failed: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An unexpected error occurred during logout.');
            }
        });
    </script>
</body>
</html>
?>