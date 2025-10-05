<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Lax');

session_start();

const DB_HOST = 'localhost';
const DB_NAME = 'ecommerce_db';
const DB_USER = 'db_user';
const DB_PASS = 'db_password';

const LOG_FILE_PATH = __DIR__ . '/auth_log.txt';
const MAX_FAILED_ATTEMPTS = 5;
const LOCKOUT_DURATION_SECONDS = 300;

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("An unexpected error occurred. Please try again later.");
}

function log_auth_attempt(string $username, string $ip_address, string $status): void {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = sprintf("[%s] IP: %s | User: %s | Status: %s\n", $timestamp, $ip_address, $username, $status);
    file_put_contents(LOG_FILE_PATH, $log_entry, FILE_APPEND);
}

function sanitize_input(string $data): string {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

$login_error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    if (empty($username) || empty($password)) {
        $login_error_message = "Please enter both username and password.";
        log_auth_attempt($username, $ip_address, "Failed - Empty credentials");
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, failed_login_attempts, last_failed_attempt, account_locked_until FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        if ($user) {
            if ($user['account_locked_until'] && strtotime($user['account_locked_until']) > time()) {
                $login_error_message = "Your account is temporarily locked. Please try again later.";
                log_auth_attempt($username, $ip_address, "Failed - Account locked");
            } else {
                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['logged_in'] = true;

                    $update_stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, last_failed_attempt = NULL, account_locked_until = NULL WHERE id = :id");
                    $update_stmt->execute([':id' => $user['id']]);

                    log_auth_attempt($username, $ip_address, "Successful");

                    header("Location: /dashboard.php");
                    exit();
                } else {
                    $login_error_message = "Invalid username or password.";
                    log_auth_attempt($username, $ip_address, "Failed - Invalid password");

                    $failed_attempts = $user['failed_login_attempts'] + 1;
                    $lock_until = null;
                    if ($failed_attempts >= MAX_FAILED_ATTEMPTS) {
                        $lock_until = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION_SECONDS);
                        $login_error_message = "Invalid username or password. Your account may be locked after too many attempts.";
                    }

                    $update_stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = :attempts, last_failed_attempt = NOW(), account_locked_until = :lock_until WHERE id = :id");
                    $update_stmt->execute([
                        ':attempts' => $failed_attempts,
                        ':lock_until' => $lock_until,
                        ':id' => $user['id']
                    ]);
                }
            }
        } else {
            $login_error_message = "Invalid username or password.";
            log_auth_attempt($username, $ip_address, "Failed - User not found");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
</head>
<body>
    <div>
        <h2>Login</h2>
        <?php if (!empty($login_error_message)): ?>
            <p><?php echo $login_error_message; ?></p>
        <?php endif; ?>
        <form action="login.php" method="post">
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
?>