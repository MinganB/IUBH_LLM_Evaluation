<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'mydb';
$dbuser = getenv('DB_USER') ?: 'dbuser';
$dbpass = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = null;
try {
  $pdo = new PDO($dsn, $dbuser, $dbpass, $options);
} catch (PDOException $e) {
  http_response_code(500);
  echo 'Internal server error';
  exit;
}

$message = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
  $email = trim($_POST['email']);
  if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
     $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
     $stmt->execute(['email' => $email]);
     $user = $stmt->fetch();
     if ($user) {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $stmt2 = $pdo->prepare('UPDATE users SET reset_token_hash = :hash, reset_token_expires_at = :expires WHERE id = :id');
        $stmt2->execute(['hash' => $tokenHash, 'expires' => $expires, 'id' => $user['id']]);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $resetLink = $scheme . '://' . $host . $path . '/reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email);

        $to = $email;
        $subject = 'Password reset request';
        $messageBody = "We received a request to reset your password. To reset your password, visit the following link:\n\n" . $resetLink . "\n\nIf you did not request this, you can ignore this email.";
        $headers = "From: no-reply@" . $host . "\r\n" .
                   "Reply-To: no-reply@" . $host . "\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n";

        mail($to, $subject, $messageBody, $headers);
        $message = 'If the email is registered, a password reset link has been sent.';
     } else {
        $message = 'If the email is registered, a password reset link has been sent.';
     }
  } else {
     $message = 'Please enter a valid email address.';
  }
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Request Password Reset</title></head><body>';
echo '<h2>Request Password Reset</h2>';
if ($message) {
  echo '<p>' . htmlspecialchars($message) . '</p>';
}
echo '<form method="post" action="">';
echo '<label for="email">Email:</label><br>';
echo '<input type="email" id="email" name="email" required>';
echo '<br><button type="submit">Send reset link</button>';
echo '</form>';
echo '</body></html>';
?>


<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'mydb';
$dbuser = getenv('DB_USER') ?: 'dbuser';
$dbpass = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

$pdo = null;
try {
  $pdo = new PDO($dsn, $dbuser, $dbpass, $options);
} catch (PDOException $e) {
  http_response_code(500);
  echo 'Internal server error';
  exit;
}

$infoMessage = '';
$errorMessage = '';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$email = isset($_GET['email']) ? $_GET['email'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $emailPost = $_POST['email'] ?? '';
  $tokenPost = $_POST['token'] ?? '';
  $password = $_POST['password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  if ($emailPost && $tokenPost && $password && $confirm) {
    if ($password !== $confirm) {
      $errorMessage = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
      $errorMessage = 'Password must be at least 8 characters.';
    } else {
      $stmt = $pdo->prepare('SELECT id, reset_token_hash, reset_token_expires_at FROM users WHERE email = :email LIMIT 1');
      $stmt->execute(['email' => $emailPost]);
      $row = $stmt->fetch();
      if ($row && $row['reset_token_hash']) {
        $providedHash = hash('sha256', $tokenPost);
        if (hash_equals($row['reset_token_hash'], $providedHash)) {
          $expiresAt = strtotime($row['reset_token_expires_at']);
          if ($expiresAt === false || time() > $expiresAt) {
            $errorMessage = 'This reset link has expired.';
          } else {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = $pdo->prepare('UPDATE users SET password_hash = :pwd, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = :id');
            $stmt2->execute(['pwd' => $newHash, 'id' => $row['id']]);
            $infoMessage = 'Your password has been reset successfully.';
          }
        } else {
          $errorMessage = 'Invalid reset token.';
        }
      } else {
        $errorMessage = 'Invalid request.';
      }
    }
  } else {
    $errorMessage = 'All fields are required.';
  }
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reset Password</title></head><body>';
if ($infoMessage) {
  echo '<p>' . htmlspecialchars($infoMessage) . '</p>';
}
if ($errorMessage) {
  echo '<p style="color:red;">' . htmlspecialchars($errorMessage) . '</p>';
}

$renderForm = false;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (!empty($token) && !empty($email)) {
    $renderForm = true;
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_POST['email']) && !empty($_POST['token'])) {
    $renderForm = true;
  }
}

if ($renderForm) {
  $emailValue = htmlspecialchars($email ?? $_POST['email'] ?? '');
  $tokenValue = htmlspecialchars($token ?? $_POST['token'] ?? '');
  echo '<form method="post" action="">';
  echo '<input type="hidden" name="email" value="' . $emailValue . '">';
  echo '<input type="hidden" name="token" value="' . $tokenValue . '">';
  echo '<label for="password">New Password:</label><br>';
  echo '<input type="password" id="password" name="password" required><br>';
  echo '<label for="confirm_password">Confirm Password:</label><br>';
  echo '<input type="password" id="confirm_password" name="confirm_password" required><br>';
  echo '<button type="submit">Reset Password</button>';
  echo '</form>';
} else {
  if (!$infoMessage) {
    echo '<p>Invalid password reset request. Please initiate a new password reset from the login page.</p>';
  }
}
echo '</body></html>';
?>