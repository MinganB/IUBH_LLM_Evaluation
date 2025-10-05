<?php
$error = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_NUMBER_INT);
$locked = filter_input(INPUT_GET, 'locked', FILTER_SANITIZE_NUMBER_INT);
$message = '';
if ($error) {
  $message = 'Invalid username or password.';
} elseif ($locked) {
  $message = 'Account is locked due to multiple failed login attempts. Please try again later.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
</head>
<body>
  <?php if ($message): ?>
    <p style="color:red;"><?php echo htmlspecialchars($message); ?></p>
  <?php endif; ?>
  <form method="POST" action="login.php" autocomplete="on">
    <label>Username
      <input type="text" name="username" required autocomplete="username">
    </label><br>
    <label>Password
      <input type="password" name="password" required autocomplete="current-password">
    </label><br>
    <button type="submit">Login</button>
  </form>
</body>
</html>

<?php
// login.php

$cookieParams = [
  'lifetime' => 0,
  'path' => '/',
  'domain' => $_SERVER['HTTP_HOST'] ?? '',
  'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
  'httponly' => true,
  'samesite' => 'Lax'
];
if (function_exists('session_set_cookie_params')) {
  session_set_cookie_params($cookieParams);
}
session_start();

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
  mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/auth.log';

function logAuthAttempt($username, $success, $ip, $reason, $logFile) {
  $timestamp = date('Y-m-d H:i:s');
  $entry = sprintf("[%s] %s - IP: %s - User: %s - %s\n", $timestamp, $success ? 'SUCCESS' : 'FAIL', $ip, $username, $reason);
  file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

function getPDO() {
  $host = getenv('DB_HOST') ?: '127.0.0.1';
  $db = getenv('DB_NAME') ?: 'ecommerce';
  $user = getenv('DB_USER') ?: 'dbuser';
  $pass = getenv('DB_PASS') ?: '';
  $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
  return $pdo;
}

$pdo = null;
try {
  $pdo = getPDO();
} catch (Exception $e) {
  http_response_code(500);
  echo 'Internal Server Error';
  exit;
}

$usernameRaw = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$username = trim(strip_tags($usernameRaw));
$password = trim(strip_tags($password));

$username = preg_replace('/\R/', '', $username);

if ($username === '' || $password === '') {
  logAuthAttempt($username ?? 'UNKNOWN', false, $ip, 'Missing credentials', $logFile);
  header('Location: login_form.php?error=1');
  exit;
}

try {
  $stmt = $pdo->prepare('SELECT id, username, password_hash, failed_attempts, lock_until FROM users WHERE username = :username');
  $stmt->execute([':username' => $username]);
  $user = $stmt->fetch();

  $now = new DateTime();

  if (!$user) {
    logAuthAttempt($username, false, $ip, 'User not found', $logFile);
    header('Location: login_form.php?error=1');
    exit;
  }

  $lockUntil = null;
  if (!empty($user['lock_until'])) {
    $lockUntil = DateTime::createFromFormat('Y-m-d H:i:s', $user['lock_until']);
    if ($lockUntil === false) {
      $lockUntil = new DateTime($user['lock_until']);
    }
  }

  if ($lockUntil !== null && $lockUntil > $now) {
    logAuthAttempt($username, false, $ip, 'Account locked until ' . $lockUntil->format('Y-m-d H:i:s'), $logFile);
    header('Location: login_form.php?locked=1');
    exit;
  }

  $pwdHash = $user['password_hash'];
  if (password_verify($password, $pwdHash)) {
    $stmtUpdate = $pdo->prepare('UPDATE users SET failed_attempts = 0, lock_until = NULL WHERE id = :id');
    $stmtUpdate->execute([':id' => $user['id']]);

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();

    logAuthAttempt($username, true, $ip, 'Authentication successful', $logFile);
    header('Location: dashboard.php');
    exit;
  } else {
    $currentFailed = (int)($user['failed_attempts'] ?? 0) + 1;
    $newLockUntil = null;
    $lockThreshold = 5;
    if ($currentFailed >= $lockThreshold) {
      $lockUntilTime = clone $now;
      $lockUntilTime->add(new DateInterval('PT15M'));
      $newLockUntil = $lockUntilTime->format('Y-m-d H:i:s');
    }

    $stmtUpdate = $pdo->prepare('UPDATE users SET failed_attempts = :fa, lock_until = :lu WHERE id = :id');
    $stmtUpdate->execute([
      ':fa' => $currentFailed,
      ':lu' => $newLockUntil,
      ':id' => $user['id']
    ]);
    logAuthAttempt($username, false, $ip, 'Invalid password', $logFile);
    if ($newLockUntil) {
      header('Location: login_form.php?locked=1');
    } else {
      header('Location: login_form.php?error=1');
    }
    exit;
  }

} catch (Exception $e) {
  http_response_code(500);
  echo 'Internal Server Error';
  exit;
}
?>