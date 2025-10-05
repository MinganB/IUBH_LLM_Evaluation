<?php
declare(strict_types=1);

$MAX_ATTEMPTS = 5;
$LOCKOUT_SECONDS = 900;

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'authdb');
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";

$secureCookie = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    ensureTablesExist($pdo);
} catch (Exception $e) {
    http_response_code(500);
    echo 'Internal Server Error';
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_regenerate_id(true);
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $loginError = 'Invalid request.';
    } else {
        $usernameRaw = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $username = preg_replace('/[^a-zA-Z0-9_.@-]+/', '', trim($usernameRaw));

        if ($username === '' || $password === '') {
            $loginError = 'Invalid credentials';
        } else {
            $ip = getClientIp();
            $row = fetchUser($pdo, $username);
            if (!$row) {
                logLoginAttempt($pdo, $username, $ip, 'FAIL');
                $loginError = 'Invalid credentials';
            } else {
                if ($row['lock_until'] !== null && strtotime($row['lock_until']) > time()) {
                    logLoginAttempt($pdo, $username, $ip, 'FAIL');
                    $loginError = 'Account is locked. Please try again later.';
                } else {
                    if (password_verify($password, $row['password_hash'])) {
                        $stmt = $pdo->prepare('UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = ?');
                        $stmt->execute([$row['id']]);
                        logLoginAttempt($pdo, $username, $ip, 'SUCCESS');
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int)$row['id'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['login_time'] = time();
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } else {
                        $newFailed = (int)($row['failed_attempts'] ?? 0) + 1;
                        $lockUntil = null;
                        if ($newFailed >= $MAX_ATTEMPTS) {
                            $lockUntil = date('Y-m-d H:i:s', time() + $LOCKOUT_SECONDS);
                        }
                        $stmt = $pdo->prepare('UPDATE users SET failed_attempts = ?, lock_until = ? WHERE id = ?');
                        $stmt->execute([$newFailed, $lockUntil, $row['id']]);
                        logLoginAttempt($pdo, $username, $ip, 'FAIL');
                        if ($lockUntil) {
                            $loginError = 'Account locked due to multiple failed attempts. Try again later.';
                        } else {
                            $loginError = 'Invalid credentials';
                        }
                    }
                }
            }
        }
    }
}

function getClientIp(): string
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function fetchUser(PDO $pdo, string $username)
{
    $stmt = $pdo->prepare('SELECT id, username, password_hash, failed_attempts, lock_until FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function logLoginAttempt(PDO $pdo, string $username, string $ip, string $status): void
{
    $stmt = $pdo->prepare('INSERT INTO login_logs (username, ip_address, status, attempted_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$username, $ip, $status]);
}

function ensureTablesExist(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            lock_until DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255),
            ip_address VARCHAR(45),
            status ENUM('SUCCESS','FAIL') NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Authentication</title>
</head>
<body>
<?php if (isset($_SESSION['user_id'])): ?>
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></h2>
    <p>You are logged in.</p>
    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>?action=logout">Logout</a>
<?php else: ?>
    <h2>Login</h2>
    <?php if ($loginError): ?>
        <p><?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <label>Username:
            <input type="text" name="username" autocomplete="username" required>
        </label><br>
        <label>Password:
            <input type="password" name="password" autocomplete="current-password" required>
        </label><br>
        <button type="submit" name="action" value="login">Login</button>
    </form>
<?php endif; ?>
</body>
</html>
?>