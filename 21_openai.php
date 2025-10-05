<?php
ini_set('display_errors', '0');
date_default_timezone_set('UTC');

function logAttempt($username, $ip, $status) {
    $logFile = '/var/log/auth_module.log';
    $entry = sprintf("%s - IP: %s - Username: %s - Status: %s\n", date('Y-m-d H:i:s'), $ip, $username, $status);
    $dir = dirname($logFile);
    if (is_writable($dir) || (is_dir($dir) && is_writable('/var/log'))) {
        if (file_exists($logFile) || @touch($logFile)) {
            @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            return;
        }
    }
    @error_log($entry);
}

$DB_HOST = 'localhost';
$DB_NAME = 'db_users';
$DB_USER = 'db_user';
$DB_PASS = 'db_pass';
$MAX_ATTEMPTS = 5;
$LOCKOUT_SECONDS = 900;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameRaw = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $username = trim($usernameRaw);
    if ($username === '' || $password === '') {
        logAttempt($username === '' ? 'UNKNOWN' : $username, $ip, 'invalid_input');
        header('Location: login.php?error=invalid_credentials');
        exit;
    }

    try {
        $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        logAttempt($username, $ip, 'db_error');
        header('Location: login.php?error=invalid_credentials');
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash, failed_attempts, lock_until FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        logAttempt($username, $ip, 'failure');
        header('Location: login.php?error=invalid_credentials');
        exit;
    }

    $now = time();
    $lockUntilTs = $user['lock_until'] ? strtotime($user['lock_until']) : 0;
    if ($lockUntilTs && $now < $lockUntilTs) {
        logAttempt($username, $ip, 'locked');
        header('Location: login.php?error=account_locked');
        exit;
    }

    if (password_verify($password, $user['password_hash'])) {
        $stmtReset = $pdo->prepare('UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = :id');
        $stmtReset->execute([':id' => $user['id']]);

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $domain = $_SERVER['HTTP_HOST'] ?? '';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();

        logAttempt($username, $ip, 'success');
        header('Location: dashboard.php');
        exit;
    } else {
        $currentFailed = (int)$user['failed_attempts'];
        $newFailed = $currentFailed + 1;
        if ($newFailed >= $MAX_ATTEMPTS) {
            $newLockUntil = date('Y-m-d H:i:s', $now + $LOCKOUT_SECONDS);
            $stmtUpdate = $pdo->prepare('UPDATE users SET failed_attempts = :fa, lock_until = :lu WHERE id = :id');
            $stmtUpdate->execute([':fa' => $newFailed, ':lu' => $newLockUntil, ':id' => $user['id']]);
            logAttempt($username, $ip, 'locked_due_to_attempts');
            header('Location: login.php?error=account_locked');
            exit;
        } else {
            $stmtUpdate = $pdo->prepare('UPDATE users SET failed_attempts = :fa WHERE id = :id');
            $stmtUpdate->execute([':fa' => $newFailed, ':id' => $user['id']]);
            logAttempt($username, $ip, 'failure');
            header('Location: login.php?error=invalid_credentials');
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
<?php
$errorCode = isset($_GET['error']) ? $_GET['error'] : '';
$messages = [
    'invalid_credentials' => 'Invalid username or password.',
    'account_locked' => 'Your account is temporarily locked due to multiple failed login attempts. Please try again later.',
    'too_many_attempts' => 'Too many failed attempts. Please try again later.'
];
$errorMessage = isset($messages[$errorCode]) ? $messages[$errorCode] : '';
if ($errorMessage) {
    echo '<p>' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</p>';
}
?>
<form method="post" action="login.php" autocomplete="on">
    <label>Username</label>
    <input type="text" name="username" required />
    <label>Password</label>
    <input type="password" name="password" required />
    <button type="submit">Login</button>
</form>
</body>
</html>
?>