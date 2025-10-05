<?php
// config.php - Placed in the root of your project
// Example path: /config.php

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user'); // REPLACE with your actual DB user
define('DB_PASS', 'your_db_password'); // REPLACE with your actual DB password

// Session Configuration
define('SESSION_ABSOLUTE_TIMEOUT', 3600); // 1 hour in seconds
define('SESSION_COOKIE_NAME', 'APP_SESSION');

// Logging Configuration
define('LOG_FILE_PATH', __DIR__ . '/session_events.log');

// PHP Session Security Configurations
// These settings help secure sessions against common attacks
ini_set('session.use_strict_mode', 1); // Only accept valid session IDs
ini_set('session.use_cookies', 1); // Always use cookies for session IDs
ini_set('session.use_only_cookies', 1); // Never pass session IDs in URLs
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookie
ini_set('session.cookie_secure', 1); // Only send session cookie over HTTPS (set to 0 for local dev if not using HTTPS)
ini_set('session.cookie_samesite', 'Lax'); // Protect against CSRF attacks ('Lax' or 'Strict')
?>
<?php
// classes/Database.php
// Example path: /classes/Database.php

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
                // In a production environment, this error should be logged securely
                // and a generic error message displayed to the user.
                exit('Database connection failed.');
            }
        }
        return self::$pdo;
    }
}
?>
<?php
// classes/Logger.php
// Example path: /classes/Logger.php

class Logger {
    public static function log(string $message, ?int $userId = null): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp]";
        if ($userId !== null) {
            $logMessage .= " [User ID: $userId]";
        }
        $logMessage .= " $message" . PHP_EOL;

        $logFilePath = LOG_FILE_PATH;
        $logDir = dirname($logFilePath);

        if (!is_dir($logDir)) {
            // Attempt to create the directory if it doesn't exist
            // Set appropriate permissions (e.g., 0755)
            @mkdir($logDir, 0755, true);
        }

        // Use LOCK_EX to prevent race conditions during file write
        @file_put_contents($logFilePath, $logMessage, FILE_APPEND | LOCK_EX);
    }
}
?>
<?php
// classes/SessionManager.php
// Example path: /classes/SessionManager.php

class SessionManager {
    public function __construct() {
        // Configure session cookie parameters
        session_set_cookie_params([
            'lifetime' => SESSION_ABSOLUTE_TIMEOUT,
            'path' => '/',
            'domain' => '', // Leave empty for current domain, or specify your domain (e.g., '.yourdomain.com')
            'secure' => true, // Enforce HTTPS for session cookie
            'httponly' => true, // Prevent JavaScript access to session cookie
            'samesite' => 'Lax' // Protect against CSRF
        ]);
        session_name(SESSION_COOKIE_NAME);
    }

    public function startSession(): bool {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        // Check for absolute session timeout
        if (isset($_SESSION['created_at']) && (time() - $_SESSION['created_at'] > SESSION_ABSOLUTE_TIMEOUT)) {
            $this->destroySession();
            Logger::log('Session expired (absolute timeout).', $_SESSION['user_id'] ?? null);
            return false;
        }

        // Update last activity timestamp
        $_SESSION['last_activity'] = time();

        return true;
    }

    public function login(int $userId, string $username): void {
        if (session_status() == PHP_SESSION_NONE) {
            $this->startSession();
        }
        
        // Regenerate session ID to prevent session fixation attacks
        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['created_at'] = time(); // Record session creation time for absolute timeout
        $_SESSION['last_activity'] = time(); // Record last activity for potential inactivity timeout

        Logger::log('User logged in, session created.', $userId);
    }

    public function isLoggedIn(): bool {
        if (session_status() == PHP_SESSION_NONE) {
            return false; // Session not started
        }
        // Check if user ID is set AND if the session is still valid (not timed out)
        return isset($_SESSION['user_id']) && $this->startSession();
    }

    public function destroySession(): void {
        $userIdToLog = $_SESSION['user_id'] ?? null; // Capture user ID before clearing session

        // Unset all of the session variables
        $_SESSION = [];

        // Invalidate the session cookie by setting its expiration to a past time
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        // Finally, destroy the session
        session_destroy();
        Logger::log('Session destroyed.', $userIdToLog);
    }
}
?>
<?php
// handlers/session_handler.php
// Example path: /handlers/session_handler.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/SessionManager.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $sessionManager = new SessionManager();
    // Always start session to ensure cookie parameters are set and existing sessions are handled
    $sessionManager->startSession();

    switch ($_POST['action']) {
        case 'login':
            if (isset($_POST['username'], $_POST['password'])) {
                $username = $_POST['username'];
                $password = $_POST['password'];

                try {
                    $pdo = Database::getConnection();
                    $stmt = $pdo->prepare("SELECT id, username, password FROM users WHERE username = :username");
                    $stmt->execute(['username' => $username]);
                    $user = $stmt->fetch();

                    // IMPORTANT: In a production environment, you MUST hash passwords
                    // and use password_verify($password, $user['password'])
                    // For this example, it's a direct comparison based on the prompt's `password` column
                    if ($user && $user['password'] === $password) { // REPLACE WITH password_verify($password, $user['password'])
                        $sessionManager->login($user['id'], $user['username']);
                        $response = ['success' => true, 'message' => 'Login successful'];
                    } else {
                        Logger::log('Login failed for username: ' . $username, null);
                        $response = ['success' => false, 'message' => 'Invalid username or password'];
                    }
                } catch (PDOException $e) {
                    Logger::log('Database error during login: ' . $e->getMessage(), null);
                    $response = ['success' => false, 'message' => 'A database error occurred during login.'];
                }
            } else {
                $response = ['success' => false, 'message' => 'Username and password are required.'];
            }
            break;

        case 'logout':
            // The Logger::log for session destruction is handled inside SessionManager::destroySession
            $sessionManager->destroySession();
            $response = ['success' => true, 'message' => 'Logout successful'];
            break;

        default:
            $response = ['success' => false, 'message' => 'Unknown action'];
            break;
    }
}

echo json_encode($response);
exit();
?>
<?php
// public/dashboard.php
// Example path: /public/dashboard.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/SessionManager.php';

$sessionManager = new SessionManager();
$sessionManager->startSession();

if (!$sessionManager->isLoggedIn()) {
    // Check if the request is an AJAX request
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.', 'redirect' => '/login.html']);
        exit();
    } else {
        // Not an AJAX request, redirect to a login page or display an error
        http_response_code(401); // Unauthorized
        echo '<html><head><title>Unauthorized</title></head><body><h1>Unauthorized Access</h1><p>Please <a href="/login.html">log in</a> to view this page.</p></body></html>';
        exit();
    }
}

// If we reach here, the user is logged in and the session is active.
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Dashboard</title>
</head>
<body>
    <h1>Welcome to the Dashboard, <?php echo htmlspecialchars($username); ?>!</h1>
    <p>This is your personalized business dashboard content.</p>
    <p>Your User ID: <?php echo htmlspecialchars($userId); ?></p>
    <p>Session created at: <?php echo date('Y-m-d H:i:s', $_SESSION['created_at']); ?></p>
    <p>Last activity: <?php echo date('Y-m-d H:i:s', $_SESSION['last_activity']); ?></p>

    <form action="/handlers/session_handler.php" method="POST">
        <input type="hidden" name="action" value="logout">
        <button type="submit">Logout</button>
    </form>
</body>
</html>
?>