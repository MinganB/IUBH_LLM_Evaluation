<?php
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

$LOG_FILE = __DIR__ . '/auth.log';
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'db_users';
$DB_USER = getenv('DB_USER') ?: '';
$DB_PASSWORD = getenv('DB_PASSWORD') ?: '';

$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

function logAuthEvent($username, $ip, $status, $logFile) {
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] IP=$ip USER=\"$username\" STATUS=$status\n";
    if ($logFile) {
        error_log($line, 3, $logFile);
    }
}

function sanitize_input($input) {
    if ($input === null) return '';
    $input = trim($input);
    $input = strip_tags($input);
    $input = preg_replace('/[\r\n\t]+/', ' ', $input);
    return $input;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameRaw = isset($_POST['username']) ? $_POST['username'] : '';
    $passwordRaw = isset($_POST['password']) ? $_POST['password'] : '';
    $username = sanitize_input($usernameRaw);
    $password = $passwordRaw;

    if ($username === '' || $password === '') {
        logAuthEvent($username, $ip, 'FAIL_EMPTY', $LOG_FILE);
        header('Location: login.php?error=' . urlencode('Invalid credentials.'));
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        logAuthEvent($username, $ip, 'FAIL_INVALID_INPUT', $LOG_FILE);
        header('Location: login.php?error=' . urlencode('Invalid credentials.'));
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $e) {
        header('Location: login.php?error=' . urlencode('An error occurred. Please try again.'));
        exit;
    }

    $stmt = $pdo->prepare('SELECT id, username, password_hash, failed_attempts, lockout_time FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        logAuthEvent($username, $ip, 'FAIL_INVALID_CREDENTIALS', $LOG_FILE);
        header('Location: login.php?error=' . urlencode('Invalid credentials.'));
        exit;
    }

    $lockoutUntil = $user['lockout_time'] ? new DateTime($user['lockout_time']) : null;
    $now = new DateTime();

    if ($lockoutUntil && $now < $lockoutUntil) {
        logAuthEvent($username, $ip, 'FAIL_LOCKOUT', $LOG_FILE);
        header('Location: login.php?error=' . urlencode('Account is temporarily locked due to multiple failed login attempts. Please try again later.'));
        exit;
    }

    $passwordHash = $user['password_hash'];
    $isValid = password_verify($password, $passwordHash);

    if ($isValid) {
        $upd = $pdo->prepare('UPDATE users SET failed_attempts = 0, lockout_time = NULL WHERE id = :id');
        $upd->execute([':id' => $user['id']]);

        $cookieParams = [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ];
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params($cookieParams);
        } else {
            session_set_cookie_params($cookieParams['lifetime'], $cookieParams['path'], $cookieParams['domain'], $cookieParams['secure'], $cookieParams['httponly']);
        }

        session_start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        logAuthEvent($username, $ip, 'SUCCESS', $LOG_FILE);
        header('Location: dashboard.php');
        exit;
    } else {
        $attempts = (int)$user['failed_attempts'];
        $attempts += 1;
        $lockoutTime = null;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockoutUntil = new DateTime();
            $lockoutUntil->add(new DateInterval('PT' . LOCKOUT_MINUTES . 'M'));
            $lockoutTime = $lockoutUntil->format('Y-m-d H:i:s');
        }
        $upd = $pdo->prepare('UPDATE users SET failed_attempts = :attempts, lockout_time = :lockout_time WHERE id = :id');
        $upd->execute([':attempts' => $attempts, ':lockout_time' => $lockoutTime, ':id' => $user['id']]);

        logAuthEvent($username, $ip, 'FAIL_INVALID_PASSWORD', $LOG_FILE);

        if ($lockoutTime) {
            header('Location: login.php?error=' . urlencode('Account locked due to too many failed attempts. Try again later.'));
        } else {
            header('Location: login.php?error=' . urlencode('Invalid credentials.'));
        }
        exit;
    }
} else {
    $error = isset($_GET['error']) ? $_GET['error'] : '';
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Login</title>
    </head>
    <body>
        <h1>Login</h1>
        <?php if ($error !== ''): ?>
            <p style="color:red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST" autocomplete="on">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
            <br>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <br>
            <button type="submit">Login</button>
        </form>
    </body>
    </html>
    <?php
}
?>