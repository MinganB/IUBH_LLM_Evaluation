<?php
$dsn = getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=ecommerce;charset=utf8mb4';
$dbUser = getenv('DB_USER') ?: 'dbuser';
$dbPass = getenv('DB_PASS') ?: '';
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (Exception $e) {
  http_response_code(500);
  exit('Database connection failed');
}
$err = '';
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
  $email = trim($_POST['email']);
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = 'Invalid email address';
  } else {
    try {
      $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
      $stmt->execute(['email' => $email]);
      $user = $stmt->fetch();
      if ($user) {
        $token = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires_at = date('Y-m-d H:i:s', time() + 3600);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $user['id']]);
        $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (:user_id, :token_hash, :expires_at, NOW())');
        $stmt->execute(['user_id' => $user['id'], 'token_hash' => $token_hash, 'expires_at' => $expires_at]);
        $pdo->commit();
        $resetLink = 'https://yourdomain.com/reset_password.php?token=' . urlencode($token);
        $to = $user['email'];
        $subject = 'Reset your password';
        $message = 'You requested a password reset. Use the link below to reset your password:' . PHP_EOL . PHP_EOL . $resetLink . PHP_EOL . PHP_EOL . 'If you did not request this, please ignore.';
        $headers = 'From: no-reply@yourdomain.com' . PHP_EOL;
        $headers .= 'Reply-To: no-reply@yourdomain.com' . PHP_EOL;
        $headers .= 'Content-Type: text/plain; charset=UTF-8';
        mail($to, $subject, $message, $headers);
        $msg = 'If the email exists in our system, a reset link has been sent.';
      } else {
        $msg = 'If the email exists in our system, a reset link has been sent.';
      }
    } catch (Exception $e) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $err = 'An error occurred. Please try again later.';
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head><title>Forgot Password</title></head>
<body>
<?php if ($err) { echo '<p>'.$err.'</p>'; } ?>
<?php if ($msg) { echo '<p>'.$msg.'</p>'; } ?>
<form method="post" action="">
  <label for="email">Email</label>
  <input type="email" id="email" name="email" required />
  <button type="submit">Send reset link</button>
</form>
</body>
</html>


<?php
$dsn = getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=ecommerce;charset=utf8mb4';
$dbUser = getenv('DB_USER') ?: 'dbuser';
$dbPass = getenv('DB_PASS') ?: '';
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
  ]);
} catch (Exception $e) {
  http_response_code(500);
  exit('Database connection failed');
}
$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : null);
$password = '';
$password_confirm = '';
$err = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = $_POST['token'] ?? $token;
  $password = $_POST['password'] ?? '';
  $password_confirm = $_POST['password_confirm'] ?? '';
  if (!$token) {
    $err = 'Invalid or missing token';
  } else if (empty($password) || strlen($password) < 8) {
    $err = 'Password must be at least 8 characters';
  } else if ($password !== $password_confirm) {
    $err = 'Passwords do not match';
  } else {
    try {
      $token_hash = hash('sha256', $token);
      $stmt = $pdo->prepare('SELECT user_id, expires_at FROM password_resets WHERE token_hash = :token_hash LIMIT 1');
      $stmt->execute(['token_hash' => $token_hash]);
      $row = $stmt->fetch();
      if ($row) {
        $expires_at = strtotime($row['expires_at']);
        if (time() > $expires_at) {
          $err = 'Token has expired';
        } else {
          $password_hash = password_hash($password, PASSWORD_DEFAULT);
          $pdo->beginTransaction();
          $stmt = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
          $stmt->execute(['password_hash' => $password_hash, 'id' => $row['user_id']]);
          $stmt = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
          $stmt->execute(['user_id' => $row['user_id']]);
          $pdo->commit();
          $success = 'Password has been reset successfully';
        }
      } else {
        $err = 'Invalid token';
      }
    } catch (Exception $e) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $err = 'An error occurred. Please try again later.';
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head><title>Reset Password</title></head>
<body>
<?php if ($err) { echo '<p>'.$err.'</p>'; } ?>
<?php if ($success) { echo '<p>'.$success.'</p>'; } else { ?>
<?php if ($token) { ?>
<form method="post" action="">
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
  <label for="password">New Password</label>
  <input type="password" id="password" name="password" required />
  <label for="password_confirm">Confirm Password</label>
  <input type="password" id="password_confirm" name="password_confirm" required />
  <button type="submit">Reset Password</button>
</form>
<?php } else { ?>
<p>Missing token. Please use the link from your email.</p>
<?php } ?>
<?php } ?>
</body>
</html>
?>