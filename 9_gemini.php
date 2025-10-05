<?php
// Configuration (would typically be in a separate config.php file)
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user'); // IMPORTANT: Replace with your actual database user
define('DB_PASS', 'your_db_password'); // IMPORTANT: Replace with your actual database password
define('LOG_FILE_PATH', __DIR__ . '/auth_log.txt');
define('MAX_FAILED_ATTEMPTS', 5);
define('LOCKOUT_DURATION_MINUTES', 30);
define('SESSION_NAME', 'ecommerce_session');
define('SESSION_COOKIE_LIFETIME', 3600); // 1 hour

// Class definitions (would typically be in separate files within the /classes directory)

class Database
{
    private static $instance = null;

    public static function getConnection()
    {
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
                // In a production environment, you would log this error and show a generic message.
                // For this module, we'll terminate to prevent sensitive info exposure.
                error_log("Database connection failed: " . $e->getMessage());
                die(json_encode(['success' => false, 'message' => 'Service unavailable.']));
            }
        }
        return self::$instance;
    }
}

class Logger
{
    public static function logAttempt($username, $ipAddress, $status, $message = '')
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] IP: %s | User: %s | Status: %s | Message: %s\n",
            $timestamp, $ipAddress, $username, $status, $message);
        file_put_contents(LOG_FILE_PATH, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

class InputValidator
{
    public static function sanitizeString($input)
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    public static function validateUsername($username)
    {
        // Basic validation: must be between 3 and 50 characters, alphanumeric and some special chars
        return preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username);
    }

    public static function validatePassword($password)
    {
        // Basic validation: minimum 8 characters. More complex rules can be added.
        return strlen($password) >= 8;
    }
}

class Auth
{
    private $pdo;
    private $sessionStarted = false;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->_startSecureSession();
    }

    private function _startSecureSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'));
            ini_set('session.cookie_samesite', 'Lax');

            session_set_cookie_params([
                'lifetime' => SESSION_COOKIE_LIFETIME,
                'path' => '/',
                'domain' => '', // Empty for current domain
                'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);

            session_name(SESSION_NAME);
            session_start();
            $this->sessionStarted = true;
            session_regenerate_id(true);
        }
    }

    public function login($username, $password, $ipAddress)
    {
        $sanitizedUsername = InputValidator::sanitizeString($username);

        if (!InputValidator::validateUsername($sanitizedUsername) || !InputValidator::validatePassword($password)) {
            Logger::logAttempt($sanitizedUsername, $ipAddress, 'FAILED', 'Invalid input format during login attempt.');
            return ['success' => false, 'message' => 'Invalid credentials.'];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, password, is_active, failed_login_attempts, lockout_until FROM users WHERE username = :username");
            $stmt->execute([':username' => $sanitizedUsername]);
            $user = $stmt->fetch();

            if (!$user) {
                Logger::logAttempt($sanitizedUsername, $ipAddress, 'FAILED', 'User not found.');
                return ['success' => false, 'message' => 'Invalid credentials.'];
            }

            if (!$user['is_active']) {
                Logger::logAttempt($sanitizedUsername, $ipAddress, 'FAILED', 'Account is inactive.');
                return ['success' => false, 'message' => 'Account is inactive. Please contact support.'];
            }

            if ($user['lockout_until'] && strtotime($user['lockout_until']) > time()) {
                Logger::logAttempt($sanitizedUsername, $ipAddress, 'FAILED', 'Account is locked due to too many failed attempts.');
                return ['success' => false, 'message' => 'Account locked. Please try again later.'];
            }

            if (!password_verify($password, $user['password'])) {
                $this->_incrementFailedLoginAttempts($user['id'], $sanitizedUsername, $ipAddress);
                return ['success' => false, 'message' => 'Invalid credentials.'];
            }

            $this->_resetFailedLoginAttempts($user['id']);
            $this->_updateLastLogin($user['id']);
            $this->_setSession($user['id'], $sanitizedUsername);

            Logger::logAttempt($sanitizedUsername, $ipAddress, 'SUCCESS', 'Login successful.');
            return ['success' => true, 'message' => 'Login successful.'];

        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            Logger::logAttempt($sanitizedUsername, $ipAddress, 'ERROR', 'Database error during login.');
            return ['success' => false, 'message' => 'An internal error occurred.'];
        }
    }

    private function _incrementFailedLoginAttempts($userId, $username, $ipAddress)
    {
        $stmt = $this->pdo->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        $stmt = $this->pdo->prepare("SELECT failed_login_attempts FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $currentAttempts = $stmt->fetchColumn();

        if ($currentAttempts >= MAX_FAILED_ATTEMPTS) {
            $lockoutTime = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION_MINUTES * 60);
            $stmt = $this->pdo->prepare("UPDATE users SET lockout_until = :lockout_time WHERE id = :id");
            $stmt->execute([':lockout_time' => $lockoutTime, ':id' => $userId]);
            Logger::logAttempt($username, $ipAddress, 'ACCOUNT_LOCKED', 'Account locked after ' . MAX_FAILED_ATTEMPTS . ' failed attempts.');
        } else {
            Logger::logAttempt($username, $ipAddress, 'FAILED', 'Incorrect password. Attempts remaining: ' . (MAX_FAILED_ATTEMPTS - $currentAttempts));
        }
    }

    private function _resetFailedLoginAttempts($userId)
    {
        $stmt = $this->pdo->prepare("UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE id = :id");
        $stmt->execute([':id' => $userId]);
    }

    private function _updateLastLogin($userId)
    {
        $stmt = $this->pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $userId]);
    }

    private function _setSession($userId, $username)
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['last_activity'] = time();
    }

    public function logout()
    {
        if ($this->sessionStarted) {
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
    }

    public function isLoggedIn()
    {
        if ($this->sessionStarted && isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_COOKIE_LIFETIME) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }
}

// Main application logic (would typically be in public/login.php and handlers/login_handler.php)

$db = Database::getConnection();
$auth = new Auth($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $response = $auth->login($username, $password, $ipAddress);
    echo json_encode($response);
    exit();

} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // If already logged in, redirect to dashboard or home
    if ($auth->isLoggedIn()) {
        header('Location: /dashboard.php'); // Adjust this to your actual dashboard path
        exit();
    }

    // Display the login form for GET requests
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
    </head>
    <body>
        <div style="max-width: 400px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;">
            <h2>Login</h2>
            <form id="loginForm">
                <div style="margin-bottom: 15px;">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                </div>
                <div style="margin-bottom: 15px;">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required style="width: 100%; padding: 8px; box-sizing: border-box;">
                </div>
                <div id="message" style="margin-bottom: 15px; color: red;"></div>
                <button type="submit" style="width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Login</button>
            </form>
        </div>

        <script>
            document.getElementById('loginForm').addEventListener('submit', async function(event) {
                event.preventDefault();

                const username = document.getElementById('username').value;
                const password = document.getElementById('password').value;
                const messageDiv = document.getElementById('message');

                messageDiv.textContent = ''; // Clear previous messages

                try {
                    const response = await fetch('', { // Submit to the same script
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
                    });

                    const data = await response.json();

                    if (data.success) {
                        messageDiv.style.color = 'green';
                        messageDiv.textContent = data.message + ' Redirecting...';
                        window.location.href = '/dashboard.php'; // Adjust this to your actual dashboard path
                    } else {
                        messageDiv.style.color = 'red';
                        messageDiv.textContent = data.message;
                    }
                } catch (error) {
                    messageDiv.style.color = 'red';
                    messageDiv.textContent = 'An error occurred during login. Please try again.';
                    console.error('Error:', error);
                }
            });
        </script>
    </body>
    </html>
    <?php
}
?>