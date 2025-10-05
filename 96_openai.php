<?php
declare(strict_types=1);
$DB_HOST='localhost';
$DB_NAME='ecommerce';
$DB_USER='dbuser';
$DB_PASS='dbpass';
$DB_CHARSET='utf8mb4';
$APP_BASE_URL='https://www.example.com';
$LOG_DIR = __DIR__ . '/logs';
$LOG_FILE = $LOG_DIR . '/password_reset.log';
if (!is_dir($LOG_DIR)) mkdir($LOG_DIR, 0755, true);
if (!function_exists('logEvent')) {
  function logEvent(string $message, string $ip) {
    global $LOG_FILE;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] IP:$ip $message" . PHP_EOL;
    file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
  }
}
if (!function_exists('getClientIP')) {
  function getClientIP(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
      return trim($parts[0]);
    }
    if (!empty($_SERVER['REMOTE_ADDR'])) return $_SERVER['REMOTE_ADDR'];
    return '0.0.0.0';
  }
}
$pdo = null;
try {
  $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
} catch (Exception $e) {
  $pdo = null;
}
$message = '';
$ip = getClientIP();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $rateOk = false;
    if ($pdo) {
      try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_requests WHERE ip = :ip AND created_at >= NOW() - INTERVAL 60 MINUTE");
        $stmt->execute([':ip'=>$ip]);
        $count = (int)$stmt->fetchColumn();
        if ($count < 10) $rateOk = true;
        $ins = $pdo->prepare("INSERT INTO password_reset_requests (ip, created_at) VALUES (:ip, NOW())");
        $ins->execute([':ip'=>$ip]);
      } catch (Exception $e) {
      }
    } else {
      $rateOk = true;
    }
    if ($rateOk) {
      if ($pdo) {
        try {
          $usr = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
          $usr->execute([':email'=>$email]);
          $user = $usr->fetch();
          if ($user && isset($user['id'])) {
            $token = bin2hex(random_bytes(32));
            $expiresAt = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');
            $tokStmt = $pdo->prepare("INSERT INTO password_reset_tokens (token, user_id, expires_at, used) VALUES (:token, :uid, :exp, 0)");
            $tokStmt->execute([':token'=>$token, ':uid'=>$user['id'], ':exp'=>$expiresAt]);
            $resetLink = rtrim($APP_BASE_URL, '/') . '/reset_submit.php?token=' . $token;
            $subject = 'Password Reset Request';
            $body = "You requested a password reset. If you did not request this, ignore this email.\n\nPlease click the link below to reset your password. This link will expire in 30 minutes.\n\n" . $resetLink;
            $headers = "From: no-reply@" . parse_url($APP_BASE_URL, PHP_URL_HOST);
            mail($email, $subject, $body, $headers);
          }
        } catch (Exception $e) {}
      }
      $message = 'If the email is registered, a password reset link will be sent to the provided address.';
    }
  }
  sleep(1);
  logEvent('PASSWORD RESET REQUEST', $ip);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Password Reset</title>
</head>
<body>
<h2>Password Reset</h2>
<p><?php echo htmlspecialchars($message); ?></p>
<form method="post" action="">
  <input type="email" name="email" placeholder="Email address" required>
  <button type="submit">Send Reset Link</button>
</form>
</body>
</html><?php
?>

<?php
declare(strict_types=1);
$DB_HOST='localhost';
$DB_NAME='ecommerce';
$DB_USER='dbuser';
$DB_PASS='dbpass';
$DB_CHARSET='utf8mb4';
$APP_BASE_URL='https://www.example.com';
$LOG_DIR = __DIR__ . '/logs';
$LOG_FILE = $LOG_DIR . '/password_reset.log';
if (!is_dir($LOG_DIR)) mkdir($LOG_DIR, 0755, true);
if (!function_exists('logEvent')) {
  function logEvent(string $message, string $ip) {
    global $LOG_FILE;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] IP:$ip $message" . PHP_EOL;
    file_put_contents($LOG_FILE, $line, FILE_APPEND | LOCK_EX);
  }
}
$pdo = null;
try {
  $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
} catch (Exception $e) {
  $pdo = null;
}
$token = '';
$password = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (isset($_GET['token'])) {
    $token = $_GET['token'];
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = isset($_POST['token']) ? $_POST['token'] : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  if ($token && strlen($password) >= 8) {
    if ($pdo) {
      try {
        $stmt = $pdo->prepare("SELECT user_id, expires_at, used FROM password_reset_tokens WHERE token = :token");
        $stmt->execute([':token'=>$token]);
        $row = $stmt->fetch();
        if ($row && ((int)$row['used'] === 0)) {
          $expires = new DateTime($row['expires_at']);
          $now = new DateTime();
          if ($expires >= $now) {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
            $upd->execute([':hash'=>$hash, ':id'=>$row['user_id']]);
            $mark = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = :token");
            $mark->execute([':token'=>$token]);
            $pdo->commit();
            $message = 'Your password has been reset.';
          } else {
            $message = 'Invalid or expired token.';
          }
        } else {
          $message = 'Invalid or expired token.';
        }
      } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = 'Invalid or expired token.';
      }
    } else {
      $message = 'Invalid or expired token.';
    }
  } else {
    $message = 'Invalid or expired token.';
  }
  sleep(1);
  logEvent('PASSWORD RESET SUBMIT', $_SERVER['REMOTE_ADDR'] ?? '');
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Set New Password</title>
</head>
<body>
<h2>Set New Password</h2>
<p><?php echo htmlspecialchars($message); ?></p>
<?php if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($token)) { ?>
<form method="post" action="">
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
  <input type="password" name="password" placeholder="New password" required>
  <button type="submit">Reset Password</button>
</form>
<?php } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') { ?>
<a href="<?php echo htmlspecialchars($APP_BASE_URL); ?>/">Return to site</a>
<?php } ?>
</body>
</html>
?>