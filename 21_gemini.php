<?php

// db_config.php - Database connection and configuration settings
// This file should be placed in a secure location, ideally outside the web-root.

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', '');

// Log file path (ensure this directory is writable by the web server but not web-accessible)
define('LOG_FILE', __DIR__ . '/auth.log');

// Rate limiting settings
define('MAX_LOGIN_ATTEMPTS', 5); // Max failed attempts before account lock/IP rate limit
define('LOGIN_ATTEMPT_WINDOW_SECONDS', 300); // 5 minutes - window to count failed attempts
define('ACCOUNT_LOCK_DURATION_SECONDS', 3600); // 1 hour - how long an account is locked

// Session configuration for enhanced security
ini_set('session.gc_maxlifetime', 1800); // Session data lifetime on server (30 minutes)
ini_set('session.cookie_httponly', 1); // Prevents JavaScript access to session cookie
ini_set('session.cookie_secure', 1); // Ensures cookie is sent only over HTTPS (requires HTTPS)
ini_set('session.use_strict_mode', 1); // Prevents session fixation attacks

// Set session cookie parameters
session_set_cookie_params([
    'lifetime' => 1800, // Client-side cookie lifetime (30 minutes)
    'path' => '/',
    'domain' => '', // Set your domain (e.g., 'yourdomain.com') if specific. Empty for current domain.
    'secure' => true, // Only send cookie over HTTPS
    'httponly' => true, // Prevent JavaScript access
    'samesite' => 'Lax' // Prevents CSRF attacks (Strict or Lax)
]);

// Helper function for logging authentication attempts
function log_auth_attempt($username, $ip_address, $status) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] IP: $ip_address, Username: " . ($username ? $username : 'N/A') . ", Status: $status\n";
    error_log($log_entry, 3, LOG_FILE);
}

// Database connection function
function get_db_connection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch results as associative arrays
                PDO::ATTR_EMULATE_PREPARES => false, // Disable emulation for security and performance
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        // Log the actual database error but provide a generic message to the user
        error_log("Database connection error: " . $e->getMessage());
        die("An unexpected error occurred. Please try again later.");
    }
}

// --- SQL Schema for db_users ---
//
// CREATE DATABASE IF NOT EXISTS db_users;
//
// USE db_users;
//
// CREATE TABLE IF NOT EXISTS users (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     username VARCHAR(255) UNIQUE NOT NULL,
//     password VARCHAR(255) NOT NULL, -- Stores password_hash() output
//     email VARCHAR(255) UNIQUE,      -- Optional, but good practice
//     locked_until DATETIME DEFAULT NULL, -- Stores when the account lock expires
//     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
// );
//
// CREATE TABLE IF NOT EXISTS login_attempts (
//     id INT AUTO_INCREMENT PRIMARY KEY,
//     user_id INT,
//     ip_address VARCHAR(45) NOT NULL,
//     attempt_time DATETIME NOT NULL,
//     successful TINYINT(1) NOT NULL, -- 1 for success, 0 for failure
//     FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
// );
//
// -- Example user (password is 'password123')
// -- Generate password hash: echo password_hash('password123', PASSWORD_BCRYPT);
// INSERT INTO users (username, password, email) VALUES ('testuser', '$2y$10$mB/s.eYjK2/fWn/v.R3.gO.W8Z2.qK.0Y9Z.2M.x.2M.x.W', 'test@example.com');
//

?>
<?php

// login.php - Handles login form display and authentication processing

require_once 'db_config.php'; // Include database configuration and helper functions

session_start(); // Start or resume session

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'invalid':
            $error_message = 'Invalid username or password.';
            break;
        case 'locked':
            $error_message = 'Your account has been locked due to too many failed attempts. Please try again later.';
            break;
        case 'rate_limit':
            $error_message = 'Too many login attempts from this IP address. Please wait a few minutes before trying again.';
            break;
        case 'session_expired':
            $error_message = 'Your session has expired. Please log in again.';
            break;
        default:
            $error_message = 'An unexpected login error occurred.';
            break;
    }
}

// Handle POST request for login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate user input
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Password is not sanitized to preserve special characters for password_verify

    // Basic validation
    if (empty($username) || empty($password)) {
        header('Location: login.php?error=invalid');
        exit;
    }

    // Sanitize username for display/database interaction (but not for verification)
    $sanitized_username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

    $ip_address = $_SERVER['REMOTE_ADDR'];
    $pdo = get_db_connection();

    // --- IP-based rate limiting ---
    // Count failed attempts from this IP within the window
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip_address AND attempt_time > DATE_SUB(NOW(), INTERVAL :window_seconds SECOND) AND successful = 0");
    $stmt->execute([':ip_address' => $ip_address, ':window_seconds' => LOGIN_ATTEMPT_WINDOW_SECONDS]);
    $failed_attempts_ip = $stmt->fetchColumn();

    // If too many failed attempts from this IP, redirect
    if ($failed_attempts_ip >= MAX_LOGIN_ATTEMPTS * 2) { // Allow slightly more attempts per IP than per user before IP lock
        log_auth_attempt($sanitized_username, $ip_address, 'IP Rate Limited');
        header('Location: login.php?error=rate_limit');
        exit;
    }

    // --- Retrieve user details ---
    $stmt = $pdo->prepare("SELECT id, username, password, locked_until FROM users WHERE username = :username");
    $stmt->execute([':username' => $sanitized_username]);
    $user = $stmt->fetch();

    $user_id = $user['id'] ?? null;
    $hashed_password = $user['password'] ?? null;
    $locked_until = $user['locked_until'] ?? null;

    // --- Account lock check (if user exists) ---
    if ($user && $locked_until && new DateTime() < new DateTime($locked_until)) {
        log_auth_attempt($sanitized_username, $ip_address, 'Account Locked Attempt');
        header('Location: login.php?error=locked');
        exit;
    }

    // --- Password verification ---
    if ($user && password_verify($password, $hashed_password)) {
        // --- Successful Login ---
        session_regenerate_id(true); // Generate a new session ID to prevent session fixation
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $sanitized_username;

        log_auth_attempt($sanitized_username, $ip_address, 'Successful');

        // Record successful attempt in log table
        $stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, ip_address, attempt_time, successful) VALUES (:user_id, :ip_address, NOW(), 1)");
        $stmt->execute([':user_id' => $user_id, ':ip_address' => $ip_address]);

        // Clear any account lock on successful login
        $stmt = $pdo->prepare("UPDATE users SET locked_until = NULL WHERE id = :user_id");
        $stmt->execute([':user_id' => $user_id]);

        header('Location: dashboard.php');
        exit;
    } else {
        // --- Failed Login ---
        log_auth_attempt($sanitized_username, $ip_address, 'Failed');

        // Record failed attempt in log table (even if user doesn't exist, log with null user_id)
        $stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, ip_address, attempt_time, successful) VALUES (:user_id, :ip_address, NOW(), 0)");
        $stmt->execute([':user_id' => $user_id, ':ip_address' => $ip_address]);

        // --- User-based rate limiting (account lock) ---
        if ($user_id) { // Only count for actual existing users
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE user_id = :user_id AND attempt_time > DATE_SUB(NOW(), INTERVAL :window_seconds SECOND) AND successful = 0");
            $stmt->execute([':user_id' => $user_id, ':window_seconds' => LOGIN_ATTEMPT_WINDOW_SECONDS]);
            $failed_attempts_user = $stmt->fetchColumn();

            if ($failed_attempts_user >= MAX_LOGIN_ATTEMPTS) {
                // Lock the account for a defined duration
                $stmt = $pdo->prepare("UPDATE users SET locked_until = DATE_ADD(NOW(), INTERVAL :lock_duration SECOND) WHERE id = :user_id");
                $stmt->execute([':lock_duration' => ACCOUNT_LOCK_DURATION_SECONDS, ':user_id' => $user_id]);
                log_auth_attempt($sanitized_username, $ip_address, 'Account Locked');
                header('Location: login.php?error=locked');
                exit;
            }
        }
        header('Location: login.php?error=invalid');
        exit;
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
        <?php if ($error_message): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
</body>
</html>
<?php

// dashboard.php - A secure page accessible only after successful login

require_once 'db_config.php'; // Include database configuration and helper functions

session_start(); // Start or resume session

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // If not logged in, redirect to login page with an error message
    header('Location: login.php?error=session_expired');
    exit;
}

// Sanitize username for display to prevent XSS
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
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
        <p>This is your secure dashboard.</p>
        <p><a href="logout.php">Logout</a></p>
    </div>
</body>
</html>
<?php

// logout.php - Handles user logout by destroying the session

require_once 'db_config.php'; // Include database configuration (for session settings, if any)

session_start(); // Start or resume session

// Unset all of the session variables
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

// Redirect to the login page
header('Location: login.php');
exit;
?>