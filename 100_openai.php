<?php
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = 'db_users';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];
$pdo = null;
$status = null;
try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  $pdo = null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    if ($pdo) {
      $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
      $stmt->execute(['email' => $email]);
      $userRow = $stmt->fetch();
      if ($userRow) {
        $token = bin2hex(random_bytes(16));
        $insert = $pdo->prepare('INSERT INTO password_resets (email, token, created_at, used) VALUES (:email, :token, NOW(), 0)');
        $insert->execute(['email' => $email, 'token' => $token]);
        $baseUrl = $_ENV['APP_BASE_URL'] ?? 'http://localhost';
        $resetLink = $baseUrl . '/reset_password.php?token=' . urlencode($token);
        $to = $email;
        $subject = 'Password Reset Request';
        $message = '<p>You requested a password reset. Click the link below to reset your password:</p>';
        $message .= '<p><a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '</a></p>';
        $headers = "From: no-reply@example.com\r\n";
        $headers .= "Reply-To: no-reply@example.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        mail($to, $subject, $message, $headers);
        $status = 'success';
      } else {
        $status = 'not_found';
      }
    } else {
      $status = 'not_found';
    }
  } else {
    $status = 'invalid';
  }
}
?>
<!DOCTYPE html>
<html>
<head><title>Request Password Reset</title></head>
<body>
<?php if ($status === 'success') { ?>
<p>If an account with that email exists, a password reset link has been sent.</p>
<?php } elseif ($status === 'not_found') { ?>
<p>No account found with that email.</p>
<?php } elseif ($status === 'invalid') { ?>
<p>Invalid email address.</p>
<?php } else { ?>
<form method="post" action="request_reset.php">
<label for="email">Email</label>
<input type="email" name="email" id="email" required>
<button type="submit">Send Reset Link</button>
</form>
<?php } ?>
</body>
</html>

<?php
$host = $_ENV['DB_HOST'] ?? 'localhost';
$db   = 'db_users';
$user = $_ENV['DB_USER'] ?? 'root';
$pass = $_ENV['DB_PASS'] ?? '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];
$pdo = null;
try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  $pdo = null;
}
$status = null;
$token = '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
  $token = $_GET['token'] ?? '';
}
if ($method === 'POST') {
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';
  $token = $_POST['token'] ?? '';
  if ($password !== '' && $confirm_password !== '' && $token !== '') {
    if ($password === $confirm_password) {
      if ($pdo) {
        $stmt = $pdo->prepare('SELECT email, used FROM password_resets WHERE token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();
        if ($row && !$row['used']) {
          $email = $row['email'];
          $hash = password_hash($password, PASSWORD_DEFAULT);
          $updateUser = $pdo->prepare('UPDATE users SET password = :password WHERE email = :email');
          $updateUser->execute(['password' => $hash, 'email' => $email]);
          $updateToken = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
          $updateToken->execute(['token' => $token]);
          $status = 'success';
        } else {
          $status = 'invalid_token';
        }
      } else {
        $status = 'invalid_token';
      }
    } else {
      $status = 'mismatch';
    }
  } else {
    $status = 'incomplete';
  }
}
?>
<!DOCTYPE html>
<html>
<head><title>Reset Password</title></head>
<body>
<?php if ($status === 'success') { ?>
<p>Password has been reset successfully.</p>
<?php } else { ?>
<form method="post" action="reset_password.php">
<label for="password">New Password</label>
<input type="password" name="password" id="password" required>
<label for="confirm_password">Confirm Password</label>
<input type="password" name="confirm_password" id="confirm_password" required>
<label for="token">Token</label>
<input type="text" name="token" id="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>" required>
<button type="submit">Reset Password</button>
</form>
<?php if (isset($status) && $status === 'mismatch') { ?><p>Passwords do not match.</p><?php } elseif (isset($status) && $status === 'incomplete') { ?><p>All fields are required.</p><?php } elseif (isset($status) && $status === 'invalid_token') { ?><p>Invalid or used token.</p><?php } ?>
<?php } ?>
</body>
</html>
?>