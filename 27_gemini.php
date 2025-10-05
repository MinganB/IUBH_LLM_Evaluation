<?php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', 'your_db_password');

// Session configuration
define('SESSION_NAME', 'ECOMMERCE_SESSION_ID');
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

// Rate limiting configuration
define('MAX_FAILED_ATTEMPTS', 5);
define('LOCKOUT_TIME_MINUTES', 30); // 30 minutes lockout

// Logging configuration
define('LOG_FILE_PATH', __DIR__ . '/logs/auth.log');

// Paths
define('LOGIN_PAGE', 'index.php');
define('DASHBOARD_PAGE', 'dashboard.php');

?>
<?php

class Database {
    private PDO $pdo;

    public function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            http_response_code(500);
            exit('An internal server error occurred.');
        }
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}

?>
<?php

class Auth {
    private PDO $pdo;
    private string $ipAddress;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ipAddress = $this->getClientIp();

        if (session_status() == PHP_SESSION_NONE) {
            $this->configureSession();
            session_start();
        }
    }

    private function configureSession(): void {
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_set_cookie_params(
            SESSION_LIFETIME,
            '/',
            $_SERVER['HTTP_HOST'],
            true, // Secure (HTTPS only)
            true  // HttpOnly
        );
        session_name(SESSION_NAME);
    }

    public function checkLogin(): void {
        if (!isset($_SESSION['user_id']) || !$_SESSION['is_logged_in']) {
            $this->redirect(LOGIN_PAGE . '?error=auth');
        }

        if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    public function attemptLogin(string $username, string $password): bool {
        $username = filter_var(trim($username), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $password = trim($password);

        if (empty($username) || empty($password)) {
            $this->logAttempt($username, 'FAILED_EMPTY_CREDENTIALS');
            return false;
        }

        $stmt = $this->pdo->prepare("SELECT id, username, password, is_active FROM users WHERE username = :username");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->logAttempt($username, 'FAILED_USER_NOT_FOUND');
            usleep(rand(500000, 1500000));
            return false;
        }

        if (!$user['is_active']) {
            $this->logAttempt($username, 'FAILED_ACCOUNT_INACTIVE');
            return false;
        }

        if ($this->isAccountLocked($user['id'])) {
            $this->logAttempt($username, 'LOCKED_ACCOUNT');
            return false;
        }

        if (password_verify($password, $user['password'])) {
            $this->startSecureSession($user['id'], $user['username']);
            $this->resetFailedAttempts($user['id']);
            $this->updateLastLogin($user['id']);
            $this->logAttempt($user['username'], 'SUCCESS');
            return true;
        } else {
            $this->incrementFailedAttempt($user['id']);
            $this->logAttempt($user['username'], 'FAILED_BAD_PASSWORD');
            return false;
        }
    }

    private function startSecureSession(int $userId, string $username): void {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['is_logged_in'] = true;
        $_SESSION['last_regeneration'] = time();
        $_SESSION['ip_address'] = $this->ipAddress;
    }

    public function logout(): void {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public function isAccountLocked(int $userId): bool {
        $stmt = $this->pdo->prepare("SELECT locked_until FROM login_attempts WHERE user_id = :user_id AND ip_address = :ip_address");
        $stmt->execute(['user_id' => $userId, 'ip_address' => $this->ipAddress]);
        $attempt = $stmt->fetch();

        if ($attempt && $attempt['locked_until'] && strtotime($attempt['locked_until']) > time()) {
            return true;
        }
        return false;
    }

    private function incrementFailedAttempt(int $userId): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (user_id, ip_address, attempts, last_attempt_at)
            VALUES (:user_id, :ip_address, 1, NOW())
            ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt_at = NOW(), locked_until = IF(attempts + 1 >= :max_attempts, DATE_ADD(NOW(), INTERVAL :lockout_minutes MINUTE), NULL)
        ");
        $stmt->execute([
            'user_id' => $userId,
            'ip_address' => $this->ipAddress,
            'max_attempts' => MAX_FAILED_ATTEMPTS,
            'lockout_minutes' => LOCKOUT_TIME_MINUTES
        ]);
    }

    private function resetFailedAttempts(int $userId): void {
        $stmt = $this->pdo->prepare("DELETE FROM login_attempts WHERE user_id = :user_id AND ip_address = :ip_address");
        $stmt->execute(['user_id' => $userId, 'ip_address' => $this->ipAddress]);
    }

    private function updateLastLogin(int $userId): void {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $userId]);
    }

    private function logAttempt(string $username, string $status): void {
        $logMessage = sprintf(
            "[%s] IP: %s | User: %s | Status: %s%s",
            date('Y-m-d H:i:s'),
            $this->ipAddress,
            $username,
            $status,
            PHP_EOL
        );
        file_put_contents(LOG_FILE_PATH, $logMessage, FILE_APPEND);
    }

    private function getClientIp(): string {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ?: 'INVALID_IP';
    }

    private function redirect(string $location): void {
        header('Location: ' . $location);
        exit;
    }
}

?>
<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);

$errorMessage = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid') {
        $errorMessage = 'Invalid username or password.';
    } elseif ($_GET['error'] === 'locked') {
        $errorMessage = 'Your account is locked due to too many failed attempts. Please try again later.';
    } elseif ($_GET['error'] === 'auth') {
        $errorMessage = 'Please log in to access this page.';
    }
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
        <h2>Login</h2>
        <?php if ($errorMessage): ?>
            <p style="color: red;"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php endif; ?>
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

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . LOGIN_PAGE);
    exit;
}

$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if ($auth->attemptLogin($username, $password)) {
    header('Location: ' . DASHBOARD_PAGE);
    exit;
} else {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && $auth->isAccountLocked($user['id'])) {
        header('Location: ' . LOGIN_PAGE . '?error=locked');
    } else {
        header('Location: ' . LOGIN_PAGE . '?error=invalid');
    }
    exit;
}

?>
<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);
$auth->checkLogin();

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
<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

$database = new Database();
$pdo = $database->getConnection();
$auth = new Auth($pdo);

$auth->logout();
header('Location: ' . LOGIN_PAGE);
exit;

?>
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `last_login_at` DATETIME NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE `login_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `attempts` INT DEFAULT 0,
    `last_attempt_at` DATETIME NULL,
    `locked_until` DATETIME NULL,
    UNIQUE KEY `uq_user_ip` (`user_id`, `ip_address`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
?>