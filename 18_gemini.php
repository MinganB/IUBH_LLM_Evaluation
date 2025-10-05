<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

class Database {
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                die("An unexpected error occurred. Please try again later.");
            }
        }
        return self::$instance;
    }
}


<?php

define('LOG_FILE_PATH', __DIR__ . '/../logs/auth.log');

class Logger {
    public static function log(string $level, string $message, ?string $username = null, ?string $ip_address = null): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf(
            "[%s] [%s] %s%s%s%s%s",
            $timestamp,
            strtoupper($level),
            $ip_address ? "[IP: {$ip_address}] " : (self::getClientIpAddress() ? "[IP: ". self::getClientIpAddress()."] " : ""),
            $username ? "[User: {$username}] " : "",
            $message,
            PHP_EOL
        );

        $logDir = dirname(LOG_FILE_PATH);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        file_put_contents(LOG_FILE_PATH, $logMessage, FILE_APPEND | LOCK_EX);
    }

    public static function getClientIpAddress(): string {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        } else {
            $ipAddress = 'UNKNOWN';
        }
        $ipAddress = explode(',', $ipAddress)[0];
        return filter_var($ipAddress, FILTER_VALIDATE_IP) ?: 'UNKNOWN';
    }
}


<?php

require_once 'Database.php';
require_once 'Logger.php';

define('MAX_FAILED_ATTEMPTS', 5);
define('LOCKOUT_DURATION_SECONDS', 300);

class Auth {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->startSecureSession();
    }

    private function startSecureSession(): void {
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Lax');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login(string $username, string $password): bool {
        $username = trim($username);
        $ipAddress = Logger::getClientIpAddress();

        $stmt = $this->db->prepare("SELECT id, username, password, is_active, failed_attempts, lockout_until FROM users WHERE username = :username LIMIT 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            Logger::log('WARNING', 'Login attempt with non-existent username.', $username, $ipAddress);
            return false;
        }

        if (!$user['is_active']) {
            Logger::log('WARNING', 'Login attempt for inactive account.', $username, $ipAddress);
            return false;
        }

        if ($user['lockout_until'] !== null && strtotime($user['lockout_until']) > time()) {
            Logger::log('WARNING', 'Login attempt for locked account.', $username, $ipAddress);
            return false;
        }

        if (password_verify($password, $user['password'])) {
            $this->resetFailedAttempts($user['id']);
            $this->updateLastLogin($user['id']);
            $this->setupSession($user['id'], $user['username']);
            Logger::log('INFO', 'User logged in successfully.', $username, $ipAddress);
            return true;
        } else {
            $this->incrementFailedAttempts($user['id']);
            Logger::log('WARNING', 'Login attempt with incorrect password.', $username, $ipAddress);
            return false;
        }
    }

    private function incrementFailedAttempts(int $userId): void {
        $stmt = $this->db->prepare("UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = :id");
        $stmt->execute(['id' => $userId]);

        $stmt = $this->db->prepare("SELECT failed_attempts FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if ($user && $user['failed_attempts'] >= MAX_FAILED_ATTEMPTS) {
            $lockoutTime = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION_SECONDS);
            $stmt = $this->db->prepare("UPDATE users SET lockout_until = :lockout_until WHERE id = :id");
            $stmt->execute(['lockout_until' => $lockoutTime, 'id' => $userId]);
            Logger::log('CRITICAL', 'Account locked due to too many failed login attempts.', null, Logger::getClientIpAddress());
        }
    }

    private function resetFailedAttempts(int $userId): void {
        $stmt = $this->db->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = :id");
        $stmt->execute(['id' => $userId]);
    }

    private function updateLastLogin(int $userId): void {
        $stmt = $this->db->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $userId]);
    }

    private function setupSession(int $userId, string $username): void {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['LAST_ACTIVITY'] = time();
    }

    public function isLoggedIn(): bool {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
            $this->logout();
            return false;
        }

        $_SESSION['LAST_ACTIVITY'] = time();
        return true;
    }

    public function logout(): void {
        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
        session_destroy();
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}


<?php

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Logger.php';

$auth = new Auth(Database::getInstance());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $username = filter_var($username, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
    $username = trim($username);

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter both username and password.';
        header('Location: ../public/login.php');
        exit;
    }

    try {
        if ($auth->login($username, $password)) {
            unset($_SESSION['login_error']);
            header('Location: ../public/dashboard.php');
            exit;
        } else {
            $_SESSION['login_error'] = 'Invalid credentials or account locked. Please try again or contact support.';
            header('Location: ../public/login.php');
            exit;
        }
    } catch (Exception $e) {
        Logger::log('ERROR', 'Login processing error: ' . $e->getMessage(), $username, null);
        $_SESSION['login_error'] = 'An unexpected error occurred. Please try again later.';
        header('Location: ../public/login.php');
        exit;
    }
} else {
    header('Location: ../public/login.php');
    exit;
}


<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';

$auth = new Auth(Database::getInstance());

if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errorMessage = null;
if (isset($_SESSION['login_error'])) {
    $errorMessage = htmlspecialchars($_SESSION['login_error'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['login_error']);
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
        <?php if ($errorMessage): ?>
            <p style="color: red;"><?php echo $errorMessage; ?></p>
        <?php endif; ?>
        <form action="../handlers/process_login.php" method="POST">
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

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';

$auth = new Auth(Database::getInstance());

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');

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
        <h2>Welcome to your Dashboard, <?php echo $username; ?>!</h2>
        <p>This is your secure dashboard area.</p>
        <p><a href="logout.php">Logout</a></p>
    </div>
</body>
</html>


<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Logger.php';

$auth = new Auth(Database::getInstance());

if ($auth->isLoggedIn()) {
    $username = $_SESSION['username'] ?? 'unknown';
    $ipAddress = Logger::getClientIpAddress();
    $auth->logout();
    Logger::log('INFO', 'User logged out successfully.', $username, $ipAddress);
}

header('Location: login.php');
exit;
?>