<?php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();
if (!isset($_SESSION['initialized'])) { $_SESSION['initialized'] = true; }

function getPDO(): PDO {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $db   = getenv('DB_NAME') ?: 'db_users';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn  = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    return new PDO($dsn, $user, $pass, $opts);
}

function ensureLoginTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            failed_attempts INT NOT NULL DEFAULT 0,
            last_attempt_at TIMESTAMP NULL DEFAULT NULL,
            locked_until TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uniq_username (username)
        )
    ");
}

function sanitizeUsername(string $input): string {
    $input = trim($input);
    $input = preg_replace('/[^\p{L}\p{N}_-]/u', '', $input);
    return $input;
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function logAuthAttempt(string $username, bool $success, string $ip, string $reason = ''): void {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) { mkdir($logDir, 0755, true); }
    $logFile = $logDir . '/auth.log';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAIL';
    $entry  = sprintf("%s | IP=%s | USER=%s | %s", $timestamp, $ip, $username, $status);
    if ($reason !== '') { $entry .= " | REASON=" . $reason; }
    $entry .= PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND);
}

function getClientIP(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isAccountLocked(PDO $pdo, string $username): array {
    $stmt = $pdo->prepare("SELECT failed_attempts, locked_until FROM login_attempts WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();
    if (!$row) return ['locked' => false, 'lockedUntil' => null];
    $now = new DateTime('now', new DateTimeZone('UTC'));
    $lockedUntil = $row['locked_until'] ? new DateTime($row['locked_until'], new DateTimeZone('UTC')) : null;
    if ($lockedUntil && $now < $lockedUntil) {
        return ['locked' => true, 'lockedUntil' => $lockedUntil];
    }
    return ['locked' => false, 'lockedUntil' => $lockedUntil];
}

function recordFailedAttempt(PDO $pdo, string $username, ?DateTime $lockedUntil = null): void {
    $stmtSel = $pdo->prepare("SELECT failed_attempts FROM login_attempts WHERE username = :username");
    $stmtSel->execute([':username' => $username]);
    $row = $stmtSel->fetch();
    $fa = ($row['failed_attempts'] ?? 0) + 1;
    if ($lockedUntil) {
        $lu = $lockedUntil->format('Y-m-d H:i:s');
    } else {
        $lu = null;
    }
    if ($row) {
        $stmt = $pdo->prepare("UPDATE login_attempts SET failed_attempts = :fa, last_attempt_at = NOW(), locked_until = :lu WHERE username = :username");
        $stmt->execute([':fa' => $fa, ':lu' => $lu, ':username' => $username]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO login_attempts (username, failed_attempts, last_attempt_at, locked_until) VALUES (:username, :fa, NOW(), :lu)");
        $stmt->execute([':username' => $username, ':fa' => $fa, ':lu' => $lu]);
    }
}

function resetLoginAttempts(PDO $pdo, string $username): void {
    $stmt = $pdo->prepare("UPDATE login_attempts SET failed_attempts = 0, last_attempt_at = NOW(), locked_until = NULL WHERE username = :username");
    $stmt->execute([':username' => $username]);
}

function upgradePasswordIfNeeded(PDO $pdo, array $user, string $password): void {
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = :hash WHERE id = :id");
        $stmt->execute([':hash' => $newHash, ':id' => $user['id']]);
    }
}

function isValidUser(array $user): bool {
    return isset($user['id']) && (int)$user['is_active'] === 1;
}

function renderLoginForm(string $error = ''): void {
    $errMap = [
        'invalid_input' => 'Please enter both username and password.',
        'invalid_credentials' => 'Invalid username or password.',
        'account_locked' => 'Your account is temporarily locked. Please try again later.',
        'csrf' => 'Invalid form submission. Please try again.'
    ];
    $message = '';
    if ($error && isset($errMap[$error])) {
        $message = $errMap[$error];
    }
    $token = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>Login</title></head>
<body>
<form method="POST" action="login.php" autocomplete="off">
    <input type="hidden" name="csrf_token" value="' . $token . '">
    <div>';
    if ($message !== '') {
        echo '<p style="color:red;">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
    }
    echo '</div>
    <label for="username">Username</label>
    <input id="username" name="username" type="text" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>
    <button type="submit">Login</button>
</form>
</body>
</html>';
}

$pdo = null;
try {
    $pdo = getPDO();
    ensureLoginTables($pdo);
} catch (Exception $e) {
    http_response_code(500);
    echo 'Internal Server Error';
    exit;
}

$ip = getClientIP();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameRaw = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $submittedCsrf = $_POST['csrf_token'] ?? '';

    if (empty($submittedCsrf) || !isset($_SESSION['csrf_token']) || $submittedCsrf !== $_SESSION['csrf_token']) {
        logAuthAttempt('unknown', false, $ip, 'csrf_mismatch');
        header('Location: login.php?error=csrf');
        exit;
    }

    $username = sanitizeUsername($usernameRaw);

    if ($username === '' || $password === '') {
        logAuthAttempt($username, false, $ip, 'empty_fields');
        header('Location: login.php?error=invalid_input');
        exit;
    }

    // Check account lock status
    $lockInfo = isAccountLocked($pdo, $username);
    if ($lockInfo['locked']) {
        logAuthAttempt($username, false, $ip, 'account_locked');
        header('Location: login.php?error=account_locked');
        exit;
    }

    // Fetch user
    $stmtUser = $pdo->prepare("SELECT id, username, password, last_login_at, is_active FROM users WHERE username = :username");
    $stmtUser->execute([':username' => $username]);
    $user = $stmtUser->fetch();

    if (!$user || !isValidUser($user)) {
        logAuthAttempt($username, false, $ip, 'invalid_user');
        recordFailedAttempt($pdo, $username, $lockInfo['lockedUntil'] ?? null);
        header('Location: login.php?error=invalid_credentials');
        exit;
    }

    if (password_verify($password, $user['password'])) {
        upgradePasswordIfNeeded($pdo, $user, $password);
        resetLoginAttempts($pdo, $username);

        $stmtUpdateLogin = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $stmtUpdateLogin->execute([':id' => $user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['login_time'] = date('Y-m-d H:i:s');
        logAuthAttempt($username, true, $ip, 'login_success');
        header('Location: dashboard.php');
        exit;
    } else {
        logAuthAttempt($username, false, $ip, 'invalid_password');
        // update failed attempts
        $nowLocked = null;
        $failedCountStmt = $pdo->prepare("SELECT failed_attempts FROM login_attempts WHERE username = :username");
        $failedCountStmt->execute([':username' => $username]);
        $row = $failedCountStmt->fetch();
        $newFailed = (($row['failed_attempts'] ?? 0) + 1);
        if ($newFailed >= 5) {
            $dt = new DateTime('now', new DateTimeZone('UTC'));
            $dt->add(new DateInterval('PT15M'));
            $nowLocked = $dt;
            $lockedUntil = $dt->format('Y-m-d H:i:s');
        } else {
            $lockedUntil = null;
        }
        if ($row) {
            $updateStmt = $pdo->prepare("UPDATE login_attempts SET failed_attempts = :fa, last_attempt_at = NOW(), locked_until = :lu WHERE username = :username");
            $updateStmt->execute([':fa' => $newFailed, ':lu' => $lockedUntil, ':username' => $username]);
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO login_attempts (username, failed_attempts, last_attempt_at, locked_until) VALUES (:username, :fa, NOW(), :lu)");
            $insertStmt->execute([':username' => $username, ':fa' => $newFailed, ':lu' => $lockedUntil]);
        }
        header('Location: login.php?error=invalid_credentials');
        exit;
    }
} else {
    $err = $_GET['error'] ?? '';
    renderLoginForm($err);
}
?>