<?php

// All constants should ideally be loaded from environment variables or a secure configuration file
// For this example, they are defined directly.
// Ensure these values are appropriate for your production environment.

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user'); // REPLACE with your actual database username
define('DB_PASS', 'your_db_password'); // REPLACE with your actual database password

// Session configuration
// How long the session cookie should persist on the client-side (e.g., 7 days)
define('SESSION_COOKIE_LIFETIME', 3600 * 24 * 7);
// Maximum inactivity time before session is destroyed (e.g., 30 minutes)
define('SESSION_INACTIVITY_TIMEOUT', 1800);
// Absolute maximum lifetime of a session, regardless of activity (e.g., 8 hours)
define('SESSION_ABSOLUTE_TIMEOUT', 28800);

// Path to the session log file.
// This should be outside the web-accessible directory for security.
// Assuming a 'logs' directory parallel to 'classes', 'handlers', 'public'.
define('SESSION_LOG_FILE', __DIR__ . '/../../logs/session_events.log');

// --- classes/Database.php ---
// This file would be located at /classes/Database.php
class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Log the actual error for debugging, but return a generic message to the client
                error_log('Database connection error: ' . $e->getMessage());
                die(json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']));
            }
        }
        return self::$pdo;
    }

    public static function getUserByUsername(string $username): ?array {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }
}

// --- classes/Logger.php ---
// This file would be located at /classes/Logger.php
class Logger {
    private string $logFilePath;

    public function __construct(string $logFilePath) {
        $this->logFilePath = $logFilePath;
    }

    public function log(string $message, ?int $userId = null): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp]";
        if ($userId !== null) {
            $logMessage .= " [User ID: $userId]";
        }
        $logMessage .= " $message" . PHP_EOL;

        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true); // Create directory if it doesn't exist
        }

        file_put_contents($this->logFilePath, $logMessage, FILE_APPEND | LOCK_EX);
    }
}

// --- classes/SessionManager.php ---
// This file would be located at /classes/SessionManager.php
class SessionManager {
    private Logger $logger;

    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->initSession();
    }

    private function initSession(): void {
        // Set session cookie parameters for enhanced security
        session_set_cookie_params([
            'lifetime' => SESSION_COOKIE_LIFETIME,
            'path'     => '/',
            'domain'   => $_SERVER['HTTP_HOST'], // Use HTTP_HOST or specify your domain explicitly
            'secure'   => true, // Cookie only sent over HTTPS (REQUIRED for production)
            'httponly' => true, // Cookie not accessible via JavaScript
            'samesite' => 'Lax' // Helps mitigate CSRF attacks
        ]);

        // Ensure session ID is only passed via cookies, not URLs
        ini_set('session.use_only_cookies', 1);
        // Reject session IDs that have not been initialized by the server
        ini_set('session.use_strict_mode', 1);

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function createSession(int $userId): void {
        // Prevent session fixation by generating a new session ID
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;
        $_SESSION['LAST_ACTIVITY'] = time(); // Timestamp for tracking inactivity
        $_SESSION['CREATED_AT'] = time(); // Timestamp for absolute session timeout

        $this->logger->log('Session created', $userId);
    }

    public function checkSession(): bool {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        $currentTime = time();

        // Check for absolute session timeout
        if ($currentTime - $_SESSION['CREATED_AT'] > SESSION_ABSOLUTE_TIMEOUT) {
            $this->logger->log('Session expired (absolute timeout)', $_SESSION['user_id']);
            $this->destroySession();
            return false;
        }

        // Check for inactivity timeout
        if ($currentTime - $_SESSION['LAST_ACTIVITY'] > SESSION_INACTIVITY_TIMEOUT) {
            $this->logger->log('Session expired (inactivity timeout)', $_SESSION['user_id']);
            $this->destroySession();
            return false;
        }

        // Update last activity timestamp to prevent inactivity timeout on active sessions
        $_SESSION['LAST_ACTIVITY'] = $currentTime;
        return true;
    }

    public function destroySession(): void {
        $userId = $_SESSION['user_id'] ?? null;
        if ($userId !== null) {
            $this->logger->log('Session destroyed', $userId);
        } else {
            $this->logger->log('Session destroyed (user ID not found)');
        }

        $_SESSION = []; // Unset all of the session variables

        // Delete the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy(); // Destroy the session on the server side
    }

    public function getUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }
}

// --- handlers/login.php ---
// This file would be located at /handlers/login.php
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Logger.php';
require_once __DIR__ . '/../../classes/SessionManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username'], $input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit;
}

$username = $input['username'];
$password = $input['password'];

try {
    $logger = new Logger(SESSION_LOG_FILE);
    $sessionManager = new SessionManager($logger);

    $user = Database::getUserByUsername($username);

    if ($user && password_verify($password, $user['password'])) { // Assumes passwords in DB are hashed using password_hash()
        $sessionManager->createSession($user['id']);
        echo json_encode(['success' => true, 'message' => 'Login successful.']);
    } else {
        $logger->log('Failed login attempt for username: ' . $username);
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred during login.']);
}
exit;

// --- handlers/logout.php ---
// This file would be located at /handlers/logout.php
require_once __DIR__ . '/../../classes/Logger.php';
require_once __DIR__ . '/../../classes/SessionManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

try {
    $logger = new Logger(SESSION_LOG_FILE);
    $sessionManager = new SessionManager($logger);

    $sessionManager->destroySession();
    echo json_encode(['success' => true, 'message' => 'Logout successful.']);
} catch (Exception $e) {
    error_log('Logout error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred during logout.']);
}
exit;

// --- public/dashboard.php ---
// This file would be located at /public/dashboard.php
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/SessionManager.php';

header('Content-Type: application/json');

try {
    $logger = new Logger(SESSION_LOG_FILE);
    $sessionManager = new SessionManager($logger);

    if ($sessionManager->checkSession()) {
        $userId = $sessionManager->getUserId();
        echo json_encode(['success' => true, 'message' => 'Welcome to the dashboard!', 'user_id' => $userId, 'data' => 'Your secure dashboard content goes here.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.', 'redirect' => '/login.html']); // Redirect path to your login page
    }
} catch (Exception $e) {
    error_log('Dashboard access error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred while accessing the dashboard.']);
}
exit;
?>