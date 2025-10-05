<?php
// config.php
// This file contains constants and initial session configurations.

// Session timeouts in seconds
// Absolute timeout: The maximum lifetime of a session regardless of activity.
define('SESSION_ABSOLUTE_TIMEOUT', 3600); // 1 hour

// Inactivity timeout: The maximum time a session can be inactive before being destroyed.
define('SESSION_INACTIVITY_TIMEOUT', 1800); // 30 minutes

// Log file path for session events.
// It's highly recommended to place this file outside the web root for security.
define('SESSION_LOG_FILE', __DIR__ . '/../logs/session.log');

// Configure session cookie parameters for enhanced security.
// These settings are applied before session_start() is called.
// 'lifetime' => 0 means the cookie expires when the browser closes.
// 'path' => '/' means the cookie is available across the entire domain.
// 'domain' => '' means the domain will be automatically determined (can be set to '.yourdomain.com' for subdomains).
// 'secure' => true ensures the cookie is only sent over HTTPS.
// 'httponly' => true prevents client-side scripts from accessing the cookie.
// 'samesite' => 'Lax' provides protection against CSRF attacks.
$sessionCookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
];
session_set_cookie_params($sessionCookieParams);

// Ensure session ID is only passed via cookies, preventing URL-based session ID exposure.
ini_set('session.use_only_cookies', 1);

// Prevent session ID from being passed in URLs (trans_sid).
ini_set('session.use_trans_sid', 0);
?>
<?php
// session_auth.php
// This is the core module for user session management.
// It provides functions for session initialization, login, logout, and validation.

require_once __DIR__ . '/config.php';

class SessionAuth {

    // Initializes the session and handles timeout checks.
    public static function init() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        self::handleSessionTimeout();
    }

    // Logs session events to a secure file.
    private static function log_event($userId, $eventType, $sessionId = null) {
        if (!defined('SESSION_LOG_FILE')) {
            error_log('SESSION_LOG_FILE is not defined in config.php.');
            return;
        }

        $sessionId = $sessionId ?? session_id();
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [Session ID: $sessionId] [User ID: $userId] Event: $eventType\n";

        $logPath = dirname(SESSION_LOG_FILE);
        if (!is_dir($logPath)) {
            // Attempt to create the log directory if it doesn't exist.
            mkdir($logPath, 0755, true);
        }

        // Attempt to write to the log file, appending new entries and locking the file during write.
        if (file_put_contents(SESSION_LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX) === false) {
            error_log("Failed to write to session log file: " . SESSION_LOG_FILE);
        }
    }

    // Handles user login.
    public static function login($username, $password) {
        self::init(); // Ensure session is started and any prior timeouts are handled.

        // --- IMPORTANT: Replace this with actual secure user authentication ---
        // In a production environment, you would query a database,
        // retrieve the user's hashed password, and use password_verify().
        // For demonstration, a simple hardcoded check is used.
        if ($username === 'testuser' && $password === 'password123') {
            // Authentication successful.

            // Regenerate session ID to prevent session fixation attacks.
            session_regenerate_id(true);

            // Store essential user data in the session.
            $_SESSION['user_id'] = 1; // Example user ID
            $_SESSION['username'] = $username;
            $_SESSION['LAST_ACTIVITY'] = time(); // Timestamp of user's last activity.
            $_SESSION['CREATED'] = time(); // Timestamp of session creation for absolute timeout.

            self::log_event($_SESSION['user_id'], 'Login Successful');
            return true;
        } else {
            // Authentication failed.
            self::log_event(0, 'Login Failed (User: ' . $username . ')'); // Log with user ID 0 for failed attempts.
            self::destroySession(); // Clear any existing session to prevent issues after failed login.
            return false;
        }
    }

    // Handles user logout, destroying the session.
    public static function logout() {
        self::init(); // Ensure session is started before attempting to log out.

        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A';
        self::log_event($userId, 'Logout');

        self::destroySession();
    }

    // Destroys the current session completely.
    private static function destroySession() {
        // Ensure session is started before destroying it.
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = []; // Unset all session variables.

        // Get session cookie parameters to ensure the cookie is deleted correctly.
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );

        session_destroy(); // Destroy the session data on the server.
    }

    // Checks if a session is valid and active.
    public static function checkSession() {
        self::init(); // Initialize session and handle potential timeouts.

        // If 'user_id' is not set, the user is not considered logged in.
        if (!isset($_SESSION['user_id'])) {
            self::log_event('N/A', 'Access Denied (No Session)', session_id());
            self::destroySession(); // Ensure any remnants are cleared.
            return false;
        }

        // If the session is valid, update the last activity timestamp.
        $_SESSION['LAST_ACTIVITY'] = time();
        return true;
    }

    // Handles session absolute and inactivity timeouts.
    private static function handleSessionTimeout() {
        if (session_status() == PHP_SESSION_NONE || !isset($_SESSION['CREATED'])) {
            return; // No active session or session not properly initialized.
        }

        // Check for absolute session timeout.
        if (time() - $_SESSION['CREATED'] > SESSION_ABSOLUTE_TIMEOUT) {
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A';
            self::log_event($userId, 'Session Absolute Timeout');
            self::destroySession();
            return; // Session destroyed, no further checks needed.
        }

        // Check for session inactivity timeout.
        if (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_INACTIVITY_TIMEOUT) {
            $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'N/A';
            self::log_event($userId, 'Session Inactivity Timeout');
            self::destroySession();
            return; // Session destroyed, no further checks needed.
        }
    }
}
?>
<?php
// login.php
// This page provides a login form and handles user authentication.

require_once __DIR__ . '/session_auth.php';

SessionAuth::init(); // Initialize the session (primarily to handle potential prior timeouts).

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (SessionAuth::login($username, $password)) {
        header('Location: dashboard.php'); // Redirect to dashboard on successful login.
        exit;
    } else {
        $error_message = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>
    <?php if (isset($error_message)): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>
    <form method="POST" action="login.php">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required><br><br>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br><br>
        <button type="submit">Login</button>
    </form>
</body>
</html>
<?php
// dashboard.php
// This is a protected page that requires an active user session.

require_once __DIR__ . '/session_auth.php';

// Check if the user is logged in.
// If not, or if the session has timed out, redirect to the login page.
if (!SessionAuth::checkSession()) {
    header('Location: login.php');
    exit;
}

// If the session is valid, display protected content.
$username = $_SESSION['username'] ?? 'Guest'; // Retrieve username from session.
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
    <p>This is your protected dashboard content.</p>
    <p><a href="another_protected_page.php">Go to another protected page</a></p>
    <p><a href="logout.php">Logout</a></p>
</body>
</html>
<?php
// another_protected_page.php
// This is another example of a page that requires an active user session.

require_once __DIR__ . '/session_auth.php';

// Check if the user is logged in.
// If not, or if the session has timed out, redirect to the login page.
if (!SessionAuth::checkSession()) {
    header('Location: login.php');
    exit;
}

// If the session is valid, display protected content.
$username = $_SESSION['username'] ?? 'Guest'; // Retrieve username from session.
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Another Protected Page</title>
</head>
<body>
    <h2>Hello, <?php echo htmlspecialchars($username); ?>!</h2>
    <p>This is another protected page's content.</p>
    <p><a href="dashboard.php">Go back to dashboard</a></p>
    <p><a href="logout.php">Logout</a></p>
</body>
</html>
<?php
// logout.php
// This page handles user logout by destroying the session.

require_once __DIR__ . '/session_auth.php';

// Call the logout method to destroy the session and log the event.
SessionAuth::logout();

// Redirect the user to the login page after logout.
header('Location: login.php');
exit;
?>