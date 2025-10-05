<?php
$cookieSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $cookieSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

define('MAX_ATTEMPTS', 5);
define('LOCKOUT_SECONDS', 900);

function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function logAuth($status, $username, $ip) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0700, true);
    }
    $logFile = $logDir . '/auth.log';
    $entry = sprintf("[%s] %s - IP: %s - User: %s\n", date('Y-m-d H:i:s'), $status, $ip, $username ?? 'UNKNOWN');
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function sanitizeInput($data) {
    if ($data === null) return '';
    $data = trim($data);
    $data = strip_tags($data);
    return $data;
}

$dsn = getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=ecommerce';
$dbUser = getenv('DB_USER') ?: '';
$dbPass = getenv('DB_PASS') ?: '';

$pdo = null;
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // Do not reveal details to user
    $pdo = null;
}

$errors = [];
$inputUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawUsername = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    $inputUsername = sanitizeInput($rawUsername);

    if ($pdo && $inputUsername !== '' && $passwordInput !== '') {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, failed_attempts, lockout_until FROM users WHERE username = :username');
        $stmt->execute([':username' => $inputUsername]);
        $user = $stmt->fetch();

        $ip = getClientIP();

        if ($user) {
            $now = time();
            $lockoutUntil = $user['lockout_until'] ? strtotime($user['lockout_until']) : 0;
            if ($lockoutUntil > $now) {
                $errors[] = 'Account is locked. Please try again later.';
                logAuth('LOCKOUT', $inputUsername, $ip);
            } else {
                if (password_verify($passwordInput, $user['password_hash'])) {
                    $upd = $pdo->prepare('UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = :id');
                    $upd->execute([':id' => $user['id']]);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['login_time'] = time();
                    logAuth('SUCCESS', $inputUsername, $ip);
                    header('Location: /dashboard.php');
                    exit;
                } else {
                    $failed = (int)($user['failed_attempts'] ?? 0) + 1;
                    $lockoutValue = null;
                    if ($failed >= MAX_ATTEMPTS) {
                        $lockoutValue = date('Y-m-d H:i:s', time() + LOCKOUT_SECONDS);
                    }
                    $upd = $pdo->prepare('UPDATE users SET failed_attempts = :failed, lockout_until = :lockout WHERE id = :id');
                    $upd->execute([':failed' => $failed, ':lockout' => $lockoutValue, ':id' => $user['id']]);
                    logAuth('FAIL', $inputUsername, $ip);
                    $errors[] = 'Invalid username or password.';
                }
            }
        } else {
            logAuth('FAIL', $inputUsername, $ip);
            $errors[] = 'Invalid username or password.';
        }
    } else {
        $errors[] = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
<?php if (!empty($errors)) {
    foreach ($errors as $err) {
        echo '<div>' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>';
    }
} ?>
<form method="post" action="">
    <div>
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($inputUsername, ENT_QUOTES, 'UTF-8'); ?>" required>
    </div>
    <div>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
    </div>
    <div>
        <button type="submit">Login</button>
    </div>
</form>
</body>
</html>
?>