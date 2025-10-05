<?php
define('DB_HOST','localhost');
define('DB_NAME','db_users');
define('DB_USER','db_user');
define('DB_PASS','db_pass');

$rateLimitPath = __DIR__.'/rate_limit.json';
$logDir = __DIR__.'/logs';
$logFile = $logDir.'/password_reset.log';
if (!is_dir($logDir)) { mkdir($logDir, 0755, true); }

function writeLog($message, $logFile) {
  $line = '['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL;
  @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

function isRateLimited($ip, $rateLimitPath) {
  $current = time();
  $window = 900;
  $max = 5;
  if (!file_exists($rateLimitPath)) {
    $data = [];
  } else {
    $contents = @file_get_contents($rateLimitPath);
    $data = $contents ? json_decode($contents, true) : [];
  }
  if (!isset($data[$ip])) {
    $data[$ip] = ['count'=>0, 'start'=>$current];
  }
  if ($current - $data[$ip]['start'] > $window) {
    $data[$ip] = ['count'=>0, 'start'=>$current];
  }
  $data[$ip]['count'] += 1;
  @file_put_contents($rateLimitPath, json_encode($data), LOCK_EX);
  return $data[$ip]['count'] > $max;
}

$email = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $emailInput = isset($_POST['email']) ? trim($_POST['email']) : '';
  if (filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
    $email = $emailInput;
  }
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  if (isRateLimited($ip, $rateLimitPath)) {
    writeLog("RATE_LIMIT_EXCEEDED ip=$ip email=${email}", $logFile);
    echo '<html><body><p>If an account with that email exists, a password reset link has been sent.</p></body></html>';
    exit;
  }

  try {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if ($email !== null) {
      $stmt = $pdo->prepare('SELECT email FROM users WHERE email = :email');
      $stmt->execute(['email' => $email]);
      $row = $stmt->fetch();
      $found = $row ? true : false;
      if ($found) {
        $token = bin2hex(random_bytes(16)); // 32-character token
        $stmt = $pdo->prepare('INSERT INTO password_resets (token, email, created_at, used) VALUES (:token, :email, NOW(), 0)');
        $stmt->execute(['token' => $token, 'email' => $email]);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $reset_link = $scheme.'://'.$host.'/reset_password.php?token='.$token;
        $to = $email;
        $subject = 'Password Reset Request';
        $message = 'You requested a password reset. If you did not make this request, please ignore this email.
        
Reset your password using the following link (valid for 30 minutes): '.$reset_link;
        $headers = 'From: noreply@'.$host.PHP_EOL.'Reply-To: noreply@'.$host;
        @mail($to, $subject, $message, $headers);
        writeLog("PASSWORD_RESET_REQUEST email=$email ip=$ip token=$token", $logFile);
      } else {
        writeLog("PASSWORD_RESET_REQUEST email=$email ip=$ip", $logFile);
      }
    } else {
      writeLog("PASSWORD_RESET_REQUEST invalid_email_ip=$ip", $logFile);
    }
  } catch (Exception $e) {
    writeLog("PASSWORD_RESET_ERROR ip=$ip error=".$e->getMessage(), $logFile);
  }
  echo '<html><body><p>If an account with that email exists, a password reset link has been sent.</p></body></html>';
  exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Password Reset Request</title>
</head>
<body>
  <h2>Forgot Your Password?</h2>
  <form method="post" action="request_reset.php">
    <label for="email">Email:</label>
    <input type="email" id="email" name="email" required />
    <button type="submit">Send Reset Link</button>
  </form>
</body>
</html>
<?php
?>


<?php
define('DB_HOST','localhost');
define('DB_NAME','db_users');
define('DB_USER','db_user');
define('DB_PASS','db_pass');

$logDir = __DIR__.'/logs';
$logFile = $logDir.'/password_reset.log';
if (!is_dir($logDir)) { mkdir($logDir, 0755, true); }

function writeLog($message, $logFile) {
  $line = '['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL;
  @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

$token = null;
$password = null;
$confirm = null;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = isset($_POST['token']) ? trim($_POST['token']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

  $valid = true;
  if (empty($token)) $valid = false;
  if (empty($password) || empty($confirm) || $password !== $confirm || strlen($password) < 8) $valid = false;

  try {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    if ($valid) {
      $stmt = $pdo->prepare('SELECT email, created_at, used FROM password_resets WHERE token = :token');
      $stmt->execute(['token' => $token]);
      $row = $stmt->fetch();
      if ($row && $row['used'] == 0) {
        $email = $row['email'];
        $createdAt = strtotime($row['created_at']);
        $now = time();
        if (($now - $createdAt) <= 1800) {
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $stmt2 = $pdo->prepare('UPDATE users SET password = :password WHERE email = :email');
          $stmt2->execute(['password' => $hash, 'email' => $email]);
          $stmt3 = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
          $stmt3->execute(['token' => $token]);
          writeLog("PASSWORD_RESET_SUCCESS token=$token email=$email ip=$ip", $logFile);
          echo '<html><body><p>Your password has been reset if the token was valid.</p></body></html>';
          exit;
        } else {
          writeLog("PASSWORD_RESET_EXPIRED token=$token email=$email ip=$ip", $logFile);
        }
      } else {
        writeLog("PASSWORD_RESET_INVALID token=$token ip=$ip", $logFile);
      }
    } else {
      writeLog("PASSWORD_RESET_INVALID_INPUT ip=$ip token=$token", $logFile);
    }
  } catch (Exception $e) {
    writeLog("PASSWORD_RESET_ERROR ip=$ip token=$token error=".$e->getMessage(), $logFile);
  }
  echo '<html><body><p>Your password has been reset if the token was valid.</p></body></html>';
  exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Reset Password</title>
</head>
<body>
<?php
$prefillToken = isset($_GET['token']) ? $_GET['token'] : '';
?>
<h2>Reset Password</h2>
<form method="post" action="reset_password.php">
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($prefillToken, ENT_QUOTES); ?>" />
  <label for="password">New Password:</label>
  <input type="password" id="password" name="password" required />
  <br/>
  <label for="confirm_password">Confirm Password:</label>
  <input type="password" id="confirm_password" name="confirm_password" required />
  <br/>
  <button type="submit">Reset Password</button>
</form>
</body>
</html>
?>