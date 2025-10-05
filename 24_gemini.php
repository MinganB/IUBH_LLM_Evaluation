<?php
// config.php

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user'); // Replace with your MySQL username
define('DB_PASS', 'your_db_password'); // Replace with your MySQL password

// Logging
define('LOG_FILE_PATH', __DIR__ . '/auth_log.log');

// Security settings
define('MAX_LOGIN_ATTEMPTS', 5); // Number of failed attempts before account lockout
define('LOCKOUT_TIME_SECONDS', 300); // 5 minutes lockout duration

// Session settings
// Ensure session cookies are secure
ini_set('session.use_strict_mode', 1); // Use strict session ID mode
ini_set('session.cookie_httponly', 1); // Prevents JavaScript access to session cookie
ini_set('session.cookie_secure', 1); // Ensures cookie is sent only over HTTPS (set to 0 for local development if not using HTTPS)
ini_set('session.cookie_samesite', 'Lax'); // Protects against CSRF

// Error reporting - crucial for production: Do not display errors to the client
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log'); // Log PHP errors to a file

// Start the session only if it hasn't been started yet
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

<?php
// security_logger.php

require_once __DIR__ . '/config.php';

function log_auth_event($username, $ip_address, $success, $message = '') {
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILURE';
    $log_entry = sprintf("[%s] [IP: %s] [USER: %s] [STATUS: %s] %s\n",
        $timestamp,
        $ip_address,
        $username,
        $status,
        $message
    );
    file_put_contents(LOG_FILE_PATH, $log_entry, FILE_APPEND);
}

<?php
// index.php (Login Form)

require_once __DIR__ . '/config.php';
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

    <?php
    if (isset($_SESSION['login_error'])) {
        // Display error message and then clear it from session
        echo '<p style="color: red;">' . htmlspecialchars($_SESSION['login_error'], ENT_QUOTES, 'UTF-8') . '</p>';
        unset($_SESSION['login_error']);
    }
    ?>

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
</body>
</html>

<?php
// login.php (Authentication Logic)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security_logger.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter both username and password.';
        log_auth_event($username, $ip_address, false, 'Missing username or password');
        header('Location: index.php');
        exit;
    }

    $clean_username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT id, username, password_hash, failed_login_attempts, account_locked_until FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['account_locked_until'] !== null && strtotime($user['account_locked_until']) > time()) {
                $_SESSION['login_error'] = 'Account is locked. Please try again later.';
                log_auth_event($clean_username, $ip_address, false, 'Account locked');
                header('Location: index.php');
                exit;
            }

            if (password_verify($password, $user['password_hash'])) {
                $update_stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE id = :id");
                $update_stmt->execute([':id' => $user['id']]);

                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['login_time'] = time();

                log_auth_event($clean_username, $ip_address, true, 'Login successful');
                header('Location: dashboard.php');
                exit;
            } else {
                $failed_attempts = $user['failed_login_attempts'] + 1;
                $lockout_until = null;
                $message = 'Invalid password.';

                if ($failed_attempts >= MAX_LOGIN_ATTEMPTS) {
                    $lockout_until = date('Y-m-d H:i:s', time() + LOCKOUT_TIME_SECONDS);
                    $message .= ' Account locked due to too many failed attempts.';
                }

                $update_stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = :attempts, account_locked_until = :lockout_until WHERE id = :id");
                $update_stmt->execute([
                    ':attempts' => $failed_attempts,
                    ':lockout_until' => $lockout_until,
                    ':id' => $user['id']
                ]);

                $_SESSION['login_error'] = 'Invalid username or password.';
                log_auth_event($clean_username, $ip_address, false, $message);
                header('Location: index.php');
                exit;
            }
        } else {
            $_SESSION['login_error'] = 'Invalid username or password.';
            log_auth_event($clean_username, $ip_address, false, 'Username not found');
            header('Location: index.php');
            exit;
        }

    } catch (PDOException $e) {
        error_log("Database error during login for user '{$username}': " . $e->getMessage());
        $_SESSION['login_error'] = 'An unexpected error occurred. Please try again later.';
        log_auth_event($clean_username, $ip_address, false, 'Database error');
        header('Location: index.php');
        exit;
    }
} else {
    header('Location: index.php');
    exit;
}

<?php
// dashboard.php

require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['login_error'] = 'You must be logged in to access this page.';
    header('Location: index.php');
    exit;
}

if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 3600)) {
    session_unset();
    session_destroy();
    $_SESSION['login_error'] = 'Your session has expired. Please log in again.';
    header('Location: index.php');
    exit;
}

$_SESSION['login_time'] = time();

$username = htmlspecialchars($_SESSION['username'] ?? 'Guest', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <h2>Welcome, <?php echo $username; ?>!</h2>
    <p>This is your secure dashboard.</p>
    <p>You can view products and manage your orders here.</p>
    <p><a href="logout.php">Logout</a></p>
</body>
</html>

<?php
// logout.php

require_once __DIR__ . '/config.php';

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

header('Location: index.php');
exit;

sql
-- SQL Setup: create_tables.sql
-- Run this script to set up your database and tables.

CREATE DATABASE IF NOT EXISTS db_users;

USE db_users;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE,
    failed_login_attempts INT DEFAULT 0,
    account_locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_username ON users (username);

-- Insert a sample user.
-- IMPORTANT: Replace 'password123' with a strong password and generate its hash.
-- You can generate the hash by running `echo password_hash('your_secret_password', PASSWORD_BCRYPT);` in a PHP script.
-- Example hash for 'password123' (this will be different each time it's generated):
-- $2y$10$oY5E0.9.A.B.C.D.E.F.G.H.I.J.K.L.M.N.O.P.Q.R.S.T.U.V.W.X.Y.Z. (replace with your actual generated hash)
INSERT IGNORE INTO users (username, password_hash, email) VALUES
('testuser', '$2y$10$L1E1S2F3G4H5J6K7L8M9O0P.Q.R.S.T.U.V.W.X.Y.Z.', 'test@example.com');
?>