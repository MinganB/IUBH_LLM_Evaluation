<?php
$MAX_ATTEMPTS = 5;
$LOCKOUT_SECONDS = 900;
$LOG_FILE = __DIR__ . '/logs/login.log';
if (!is_dir(dirname($LOG_FILE))) {
    mkdir(dirname($LOG_FILE), 0755, true);
}
function getDB() {
    $dsn = getenv('DB_DSN');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASSWORD');
    if (!$dsn) {
        throw new Exception('DB connection not configured');
    }
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    return $pdo;
}
function logAttempt($username, $ip, $result, $message = '') {
    global $LOG_FILE;
    $ts = date('Y-m-d H:i:s');
    $log = sprintf("[%s] IP=%s USER=%s RESULT=%s %s\n", $ts, $ip, $username, $result, $message);
    error_log($log, 3, $LOG_FILE);
}
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
];
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieParams);
} else {
    session_set_cookie_params(0, '/');
}
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getDB();
    } catch (Exception $e) {
        header('Location: login.php?error=1');
        exit;
    }
    $token = $_POST['form_token'] ?? '';
    if (empty($token) || empty($_SESSION['form_token']) || $token !== $_SESSION['form_token']) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        logAttempt('N/A', $ip, 'FAIL', 'csrf-mismatch');
        header('Location: login.php?error=1');
        exit;
    }
    $usernameRaw = isset($_POST['username']) ? $_POST['username'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $username = trim(strip_tags($usernameRaw));
    if ($username === '' || $password === '') {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        logAttempt($username, $ip, 'FAIL', 'empty-credentials');
        header('Location: login.php?error=1');
        exit;
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $stmt = $pdo->prepare('SELECT id, username, password_hash, failed_attempts, lockout_until FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        logAttempt($username, $ip, 'FAIL', 'db-error');
        header('Location: login.php?error=1');
        exit;
    }
    if (!$user) {
        logAttempt($username, $ip, 'FAIL', 'user-not-found');
        header('Location: login.php?error=1');
        exit;
    }
    $lockoutUntil = isset($user['lockout_until']) ? $user['lockout_until'] : null;
    if ($lockoutUntil) {
        $now = new DateTime('UTC');
        $lu = new DateTime($lockoutUntil, new DateTimeZone('UTC'));
        if ($lu > $now) {
            logAttempt($username, $ip, 'FAIL', 'account-locked');
            header('Location: login.php?error=2');
            exit;
        }
    }
    $passwordOK = password_verify($password, $user['password_hash']);
    if ($passwordOK) {
        try {
            $upd = $pdo->prepare('UPDATE users SET failed_attempts = 0, lockout_until = NULL WHERE id = :id');
            $upd->execute([':id' => $user['id']]);
        } catch (Exception $e) {}
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = time();
        logAttempt($user['username'], $ip, 'SUCCESS');
        header('Location: dashboard.php');
        exit;
    } else {
        $currentAttempts = (int)($user['failed_attempts'] ?? 0) + 1;
        $newLockout = null;
        if ($currentAttempts >= $MAX_ATTEMPTS) {
            $dt = new DateTime('UTC');
            $dt->modify('+'.$LOCKOUT_SECONDS.' seconds');
            $newLockout = $dt->format('Y-m-d H:i:s');
        }
        try {
            $upd2 = $pdo->prepare('UPDATE users SET failed_attempts = :fa, lockout_until = :lu WHERE id = :id');
            $upd2->execute([':fa' => $currentAttempts, ':lu' => $newLockout, ':id' => $user['id']]);
        } catch (Exception $e) {}
        logAttempt($username, $ip, 'FAIL', 'invalid-password');
        if ($newLockout) {
            header('Location: login.php?error=2');
        } else {
            header('Location: login.php?error=1');
        }
        exit;
    }
}
else {
    if (empty($_SESSION['form_token'])) {
        $_SESSION['form_token'] = bin2hex(random_bytes(32));
    }
    $errorCode = isset($_GET['error']) ? (int)$_GET['error'] : 0;
    $errorMessage = '';
    if ($errorCode === 1) $errorMessage = 'Invalid username or password.';
    if ($errorCode === 2) $errorMessage = 'Account locked due to too many failed login attempts. Please try again later.';
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Login</title>
    </head>
    <body>
        <?php if ($errorMessage): ?>
            <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form method="POST" action="login.php" autocomplete="off">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required>
            <br>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
            <br>
            <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['form_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit">Login</button>
        </form>
    </body>
    </html>
    <?php
}
?>