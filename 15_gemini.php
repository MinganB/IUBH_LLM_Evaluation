<?php
// config.php
// This file contains global configurations, database connection, and utility functions.

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'your_db_username'); // IMPORTANT: Replace with your actual database username
define('DB_PASS', 'your_db_password'); // IMPORTANT: Replace with your actual database password

// Session configuration
// Ensures cookies are HttpOnly to prevent XSS attacks from accessing them
// Ensures cookies are Secure (sent only over HTTPS) for production environments
// Sets SameSite policy to 'Lax' to mitigate CSRF attacks
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1); // Set to 0 for development on HTTP, 1 for production on HTTPS
ini_set('session.use_strict_mode', 1); // Prevents session fixation for known session IDs
session_set_cookie_params([
    'lifetime' => 3600, // Session duration: 1 hour (in seconds)
    'path' => '/',
    'domain' => '', // Set to your domain (e.g., 'yourdomain.com') in production
    'secure' => true, // Must be true in production with HTTPS
    'httponly' => true,
    'samesite' => 'Lax' // Can be 'Lax' or 'Strict'
]);

// Rate limiting constants for brute-force prevention
define('MAX_LOGIN_ATTEMPTS', 5); // Maximum failed attempts before account lock
define('LOCKOUT_TIME', 300); // Account lockout duration: 5 minutes (in seconds)

// Logging configuration
// Path to the authentication log file
define('LOG_FILE', __DIR__ . '/auth.log'); // Ensure this file is writable by the web server

// Function to establish and return a PDO database connection
function get_pdo() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,     // Fetch results as associative arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                // Disable emulation for true prepared statements
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the detailed database connection error internally
            error_log('Database connection error: ' . $e->getMessage());
            // Do NOT expose sensitive error details to the user
            die('A system error has occurred. Please try again later.');
        }
    }
    return $pdo;
}

// Function to log authentication events
function log_auth_event($username, $ip, $status) {
    $timestamp = date('Y-m-d H:i:s');
    // Sanitize username for logging to prevent log injection
    $sanitized_username = preg_replace('/[^\w\s\-\.]/', '', $username);
    $log_message = sprintf("[%s] IP: %s | User: %s | Status: %s\n", $timestamp, $ip, $sanitized_username, $status);
    // Use error_log to write to the specified log file (mode 3)
    error_log($log_message, 3, LOG_FILE);
}
?>
<?php
// login.php
// This script handles the display of the login form and the authentication logic.

session_start(); // Start the session, must be at the very top
require_once 'config.php'; // Include configuration and utility functions

// If a user is already logged in, redirect them to the dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error_message = ''; // Variable to store and display error messages

// Process login form submission if it's a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize user input
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'; // Get client IP address

    // 1. Basic Input Validation and Sanitization
    $username = trim($username); // Remove leading/trailing whitespace
    $password = trim($password); // Remove leading/trailing whitespace from password

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
        log_auth_event($username, $ip_address, 'FAILED - Empty credentials');
    } else {
        $pdo = get_pdo(); // Get PDO database connection

        try {
            // Retrieve user data using a parameterized query to prevent SQL injection
            $stmt = $pdo->prepare('SELECT id, username, password_hash, failed_login_attempts, last_failed_login, account_locked_until FROM users WHERE username = :username');
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user) {
                // 2. Rate Limiting Check
                $now = new DateTime();
                $account_locked_until = new DateTime($user['account_locked_until'] ?? '1970-01-01 00:00:00'); // Default to past date if NULL

                if ($account_locked_until > $now) {
                    // Account is currently locked
                    $error_message = 'Account is locked. Please try again later.';
                    log_auth_event($username, $ip_address, 'FAILED - Account locked');
                } else {
                    // Check if last failed login was within the lockout time and attempts exceed limit
                    $last_failed_login_dt = new DateTime($user['last_failed_login'] ?? '1970-01-01 00:00:00');
                    if ($user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS && ($now->getTimestamp() - $last_failed_login_dt->getTimestamp() < LOCKOUT_TIME)) {
                        // This indicates the account was previously locked, and the lockout period is still active
                        $error_message = 'Too many failed login attempts. Account temporarily locked.';
                        log_auth_event($username, $ip_address, 'FAILED - Account still in lockout period');
                    } else {
                        // 3. Password Verification using a robust hashing algorithm
                        if (password_verify($password, $user['password_hash'])) {
                            // Login successful
                            session_regenerate_id(true); // Regenerate session ID to prevent session fixation
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username'];
                            $_SESSION['last_activity'] = time(); // Record last activity for session timeout

                            // Reset failed login attempts on successful login
                            $stmt_reset = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, last_failed_login = NULL, account_locked_until = NULL WHERE id = :id');
                            $stmt_reset->execute([':id' => $user['id']]);

                            log_auth_event($username, $ip_address, 'SUCCESS');
                            header('Location: dashboard.php'); // Redirect to dashboard
                            exit;
                        } else {
                            // Invalid password - increment failed attempts
                            $user['failed_login_attempts']++;
                            $account_locked_until_db = NULL;

                            if ($user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS) {
                                // Lock account if attempts reach the maximum limit
                                $lock_time_dt = $now->modify('+' . LOCKOUT_TIME . ' seconds');
                                $account_locked_until_db = $lock_time_dt->format('Y-m-d H:i:s');
                                $error_message = 'Invalid credentials. Your account has been temporarily locked due to too many failed attempts.';
                                log_auth_event($username, $ip_address, 'FAILED - Account locked after ' . $user['failed_login_attempts'] . ' attempts');
                            } else {
                                $error_message = 'Invalid username or password.'; // Generic error message
                                log_auth_event($username, $ip_address, 'FAILED - Invalid password, attempt ' . $user['failed_login_attempts']);
                            }

                            // Update user's failed login attempts and lockout status
                            $stmt_update = $pdo->prepare('UPDATE users SET failed_login_attempts = :attempts, last_failed_login = NOW(), account_locked_until = :locked_until WHERE id = :id');
                            $stmt_update->execute([
                                ':attempts' => $user['failed_login_attempts'],
                                ':locked_until' => $account_locked_until_db,
                                ':id' => $user['id']
                            ]);
                        }
                    }
                }
            } else {
                // User not found - provide generic error message to prevent username enumeration
                $error_message = 'Invalid username or password.';
                log_auth_event($username, $ip_address, 'FAILED - User not found');
                // A small delay can be added here to mitigate timing attacks against username enumeration, e.g., usleep(500000);
            }
        } catch (PDOException $e) {
            // Log database errors internally, do not expose to the user
            error_log('Login PDO Error: ' . $e->getMessage());
            $error_message = 'A system error occurred. Please try again later.';
            log_auth_event($username, $ip_address, 'ERROR - Database issue');
        }
    }
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
        <?php if (!empty($error_message)): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?></p>
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
// dashboard.php
// This is a protected page that only authenticated users can access.

session_start(); // Start the session
require_once 'config.php'; // Include configuration

// Check if the user is not logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Optional: Session timeout check
// Invalidate session if inactive for too long
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) { // 3600 seconds = 1 hour
    session_unset();   // Unset all session variables
    session_destroy(); // Destroy the session
    session_start();   // Start a new session to display the message
    header('Location: login.php?message=session_expired'); // Redirect with a message
    exit;
}
$_SESSION['last_activity'] = time(); // Update last activity time for renewed session validity

// Sanitize username before displaying to prevent XSS
$username = htmlspecialchars($_SESSION['username'] ?? 'Guest', ENT_QUOTES, 'UTF-8');
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
        <h2>Welcome, <?php echo $username; ?>!</h2>
        <p>This is your e-commerce dashboard. You can view products and manage your orders here.</p>
        <p><a href="logout.php">Logout</a></p>
        
        <h3>Products</h3>
        <ul>
            <li>Product A - Price: $19.99</li>
            <li>Product B - Price: $29.99</li>
            <li>Product C - Price: $39.99</li>
        </ul>
        
        <h3>Your Orders</h3>
        <p>You currently have no active orders.</p>
        <!-- More dashboard content can go here -->
    </div>
</body>
</html>
<?php
// logout.php
// This script handles logging out a user by destroying their session.

session_start(); // Start the session

// Unset all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie.
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session.
session_destroy();

// Redirect to the login page after logout
header('Location: login.php');
exit;
?>