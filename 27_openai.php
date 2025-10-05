<?php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$cookieParams = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
];
if (function_exists('session_set_cookie_params')) {
    session_set_cookie_params($cookieParams);
}
session_start();

$projectRoot = dirname(__DIR__);
$logsDir = $projectRoot . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}
$logFile = $logsDir . '/auth.log';
function logAuth($username, $ip, $status, $message = '') {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $safeUser = $username ?? '';
    $entry = "$ts | IP: $ip | User: $safeUser | Status: $status";
    if ($message !== '') {
        $entry .= " | $message";
    }
    $entry .= PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function ensureLoginTable(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            user_id INT UNIQUE,
            failed_attempts INT DEFAULT 0,
            lock_until DATETIME NULL,
            last_attempt_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
}

function getHostDbConfig() {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'db_users';
    $user = getenv('DB_USER') ?: 'db_user';
    $pass = getenv('DB_PASSWORD') ?: 'db_pass';
    return [$host, $db, $user, $pass];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['csrf_token'];
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Login</title>
    </head>
    <body>
        <h2>Sign in</h2>
        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <label>Username:
                <input type="text" name="username" required autocomplete="username">
            </label><br><br>
            <label>Password:
                <input type="password" name="password" required autocomplete="current-password">
            </label><br><br>
            <button type="submit">Login</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// POST request: process login
$postedToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || $postedToken !== $_SESSION['csrf_token']) {
    logAuth($_POST['username'] ?? '', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'FAIL', 'CSRF token mismatch');
    header('Location: login.php?e=invalid_csrf');
    exit;
}

// Sanitize input
$rawUsername = $_POST['username'] ?? '';
$rawPassword = $_POST['password'] ?? '';
$username = trim(strip_tags($rawUsername));
$password = trim(strip_tags($rawPassword));

if ($username === '' || $password === '') {
    logAuth($username, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'FAIL', 'Missing credentials');
    header('Location: login.php?e=missing');
    exit;
}

// Database connection
[$host, $dbName, $dbUser, $dbPass] = getHostDbConfig();
$dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    logAuth($username, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'FAIL', 'DB connection error');
    header('Location: login.php?e=invalid');
    exit;
}

// Ensure login_attempts table exists
ensureLoginTable($pdo);

// Fetch user
$stmt = $pdo->prepare("SELECT id, username, password, is_active FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !$user['is_active']) {
    logAuth($username, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'FAIL', 'Invalid credentials or inactive user');
    header('Location: login.php?e=invalid');
    exit;
}

$userId = (int)$user['id'];

// Check lock status
$now = new DateTime();
$lockInfoStmt = $pdo->prepare("SELECT failed_attempts, lock_until FROM login_attempts WHERE user_id = ?");
$lockInfoStmt->execute([$userId]);
$lockRow = $lockInfoStmt->fetch();
$locked = false;
$lockUntil = null;
$failedAttempts = 0;
if ($lockRow) {
    $failedAttempts = (int)$lockRow['failed_attempts'];
    $lockUntil = $lockRow['lock_until'] ? new DateTime($lockRow['lock_until']) : null;
    if ($lockUntil && $lockUntil > $now) {
        $locked = true;
    }
}

$threshold = 5;
$lockDurationMin = 15;

if ($locked) {
    logAuth($username, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'FAIL', 'Account locked');
    header('Location: login.php?e=locked');
    exit;
}

// Verify password
if (password_verify($password, $user['password'])) {
    // Successful login
    // Reset attempts
    $resetStmt = $pdo->prepare("DELETE FROM login_attempts WHERE user_id = ?");
    $resetStmt->execute([$userId]);

    // Update last_login_at
    $upd = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
    $upd->execute([$userId]);

    // Create session
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = date('Y-m-d H:i:s');
    session_regenerate_id(true);

    logAuth($username, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'SUCCESS', 'User authenticated');
    header('Location: dashboard.php');
    exit;
} else {
    // Failed password
    if ($lockRow) {
        $newFailed = (int)$lockRow['failed_attempts'] + 1;
    } else {
        $newFailed = 1;
    }

    $newLockUntil = null;
    if ($newFailed >= $threshold) {
        $newLockUntil = (new DateTime())->add(new DateInterval('PT' . $lockDurationMin . 'M'))->format('Y-m-d H:i:s');
    }

    if ($lockRow) {
        $update = $pdo->prepare("UPDATE login_attempts SET failed_attempts = ?, lock_until = ?, last_attempt_at = NOW() WHERE user_id = ?");
        $update->execute([$newFailed, $newLockUntil, $userId]);
    } else {
        $insert = $pdo->prepare("INSERT INTO login_attempts (user_id, failed_attempts, lock_until, last_attempt_at) VALUES (?, ?, ?, NOW())");
        $insert->execute([$userId, $newFailed, $newLockUntil]);
    }

    logAuth($username, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN', 'FAIL', 'Incorrect password' . ($newLockUntil ? ' | account locked' : ''));
    if ($newLockUntil) {
        header('Location: login.php?e=locked');
    } else {
        header('Location: login.php?e=invalid');
    }
    exit;
}
?>