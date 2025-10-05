<?php
// login.php
// Set secure session cookie parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'], // Set your specific domain, e.g., 'yourdomain.com'
    'secure' => true, // Only send cookie over HTTPS
    'httponly' => true, // Prevent JavaScript access to cookie
    'samesite' => 'Lax' // Protect against CSRF
]);
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php'); // Redirect to a protected page after successful login
    exit();
}

$error_message = '';
if (isset($_SESSION['login_error'])) {
    $error_message = htmlspecialchars($_SESSION['login_error'], ENT_QUOTES, 'UTF-8');
    unset($_SESSION['login_error']); // Clear the error after displaying
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
            <p style="color: red;"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <form action="process_login.php" method="post">
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
// process_login.php
// Set secure session cookie parameters
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'], // Set your specific domain, e.g., 'yourdomain.com'
    'secure' => true, // Only send cookie over HTTPS
    'httponly' => true, // Prevent JavaScript access to cookie
    'samesite' => 'Lax' // Protect against CSRF
]);
session_start();

// Constants for database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_database');

// Constants for rate limiting
define('MAX_FAILED_ATTEMPTS', 5);
define('LOCKOUT_DURATION_SECONDS', 300); // 5 minutes

// Constants for logging
define('LOG_FILE', '/var/log/auth.log'); // Ensure this path is writable by the web server and outside web root

function get_db_connection() {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($mysqli->connect_error) {
        error_log('Database connection failed: ' . $mysqli->connect_error);
        return null;
    }
    return $mysqli;
}

function log_attempt($username, $ip_address, $status, $user_id = null) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = sprintf("[%s] IP: %s, User: %s, Status: %s, UserID: %s\n",
        $timestamp, $ip_address, $username, $status, $user_id ?? 'N/A');
    error_log($log_entry, 3, LOG_FILE);

    $mysqli = get_db_connection();
    if ($mysqli) {
        $stmt = $mysqli->prepare("INSERT INTO login_logs (user_id, username_attempt, ip_address, status) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $user_id, $username, $ip_address, $status);
            $stmt->execute();
            $stmt->close();
        } else {
            error_log('Failed to prepare log statement for DB: ' . $mysqli->error);
        }
        $mysqli->close();
    }
}

if (isset($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Invalid username or password.';
    header('Location: login.php');
    exit();
}

$mysqli = get_db_connection();
if (!$mysqli) {
    $_SESSION['login_error'] = 'An unexpected error occurred. Please try again later.';
    log_attempt($username, $_SERVER['REMOTE_ADDR'], 'FAILED_DB_CONNECT');
    header('Location: login.php');
    exit();
}

$stmt = $mysqli->prepare("SELECT id, username, password, failed_attempts, lockout_until FROM users WHERE username = ?");
if (!$stmt) {
    error_log('Failed to prepare statement for user retrieval: ' . $mysqli->error);
    $_SESSION['login_error'] = 'An unexpected error occurred. Please try again later.';
    log_attempt($username, $_SERVER['REMOTE_ADDR'], 'FAILED_PREPARE_STMT');
    $mysqli->close();
    header('Location: login.php');
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    $user_id = $user['id'];
    $current_time = new DateTime();
    $lockout_until = !empty($user['lockout_until']) ? new DateTime($user['lockout_until']) : null;

    if ($lockout_until && $lockout_until > $current_time) {
        $_SESSION['login_error'] = 'Account is locked. Please try again later.';
        log_attempt($username, $_SERVER['REMOTE_ADDR'], 'FAILED_LOCKED_ACCOUNT', $user_id);
        $mysqli->close();
        header('Location: login.php');
        exit();
    }

    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $user['username'];

        session_regenerate_id(true);

        $update_stmt = $mysqli->prepare("UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("i", $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            error_log('Failed to reset failed attempts: ' . $mysqli->error);
        }

        log_attempt($username, $_SERVER['REMOTE_ADDR'], 'SUCCESS', $user_id);
        $mysqli->close();
        header('Location: /dashboard.php');
        exit();
    } else {
        $failed_attempts = $user['failed_attempts'] + 1;
        $lockout_until_str = null;

        if ($failed_attempts >= MAX_FAILED_ATTEMPTS) {
            $lockout_time = $current_time->add(new DateInterval('PT' . LOCKOUT_DURATION_SECONDS . 'S'));
            $lockout_until_str = $lockout_time->format('Y-m-d H:i:s');
            $_SESSION['login_error'] = 'Too many failed login attempts. Account locked temporarily.';
        } else {
            $_SESSION['login_error'] = 'Invalid username or password.';
        }

        $update_stmt = $mysqli->prepare("UPDATE users SET failed_attempts = ?, lockout_until = ? WHERE id = ?");
        if ($update_stmt) {
            $update_stmt->bind_param("isi", $failed_attempts, $lockout_until_str, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
        } else {
            error_log('Failed to update failed attempts: ' . $mysqli->error);
        }

        log_attempt($username, $_SERVER['REMOTE_ADDR'], 'FAILED_BAD_PASSWORD', $user_id);
        $mysqli->close();
        header('Location: login.php');
        exit();
    }
} else {
    $_SESSION['login_error'] = 'Invalid username or password.';
    log_attempt($username, $_SERVER['REMOTE_ADDR'], 'FAILED_NON_EXISTENT_USER');
    $mysqli->close();
    header('Location: login.php');
    exit();
}
?>