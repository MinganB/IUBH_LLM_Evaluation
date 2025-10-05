<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'shop';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}
$message = '';
$formSent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        if ($row) {
            $userId = $row['id'];
            $token = bin2hex(random_bytes(32));
            $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $stmt2 = $pdo->prepare('INSERT INTO password_resets (token, user_id, expires_at, used) VALUES (:token, :user_id, :expires_at, 0)');
            $stmt2->execute([':token' => $token, ':user_id' => $userId, ':expires_at' => $expiresAt]);
            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/reset_password.php?token='.urlencode($token);
            $subject = 'Password Reset';
            $body = "To reset your password, visit the following link:\n\n$resetLink\n\nThis link will expire at $expiresAt.";
            $headers = "From: no-reply@".$_SERVER['HTTP_HOST']."\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            mail($email, $subject, $body, $headers, '-fno-reply@'.$_SERVER['HTTP_HOST']);
        }
        $message = 'If an account with that email exists, a password reset link has been sent.';
        $formSent = true;
    } else {
        $message = 'Please enter a valid email address.';
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Forgot Password</title></head>
<body>
<?php if ($message): ?><p><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<form method="post" action="">
  <label for="email">Email</label>
  <input type="email" name="email" id="email" required placeholder="you@example.com">
  <button type="submit">Send Reset Link</button>
</form>
</body>
</html>
<?php
?>

<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'shop';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}
$message = '';
$showForm = false;
$token = $_GET['token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($token) {
        $stmt = $pdo->prepare('SELECT pr.user_id, pr.expires_at FROM password_resets pr WHERE pr.token = :token AND pr.used = 0');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $expires = new DateTime($row['expires_at']);
            if ($expires > new DateTime()) {
                $showForm = true;
            } else {
                $message = 'Token has expired.';
            }
        } else {
            $message = 'Invalid token.';
        }
    } else {
        $message = 'Token is required.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['password'] ?? '';
    if ($token && $newPassword) {
        $stmt = $pdo->prepare('SELECT pr.user_id, pr.expires_at FROM password_resets pr WHERE pr.token = :token AND pr.used = 0');
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $expires = new DateTime($row['expires_at']);
            if ($expires > new DateTime()) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $pdo->beginTransaction();
                $stmtUpdate = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
                $stmtUpdate->execute([':hash' => $hash, ':id' => $row['user_id']]);
                $stmtToken = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
                $stmtToken->execute([':token' => $token]);
                $pdo->commit();
                $message = 'Your password has been updated successfully.';
                $showForm = false;
            } else {
                $message = 'Token has expired.';
            }
        } else {
            $message = 'Invalid token.';
        }
    } else {
        $message = 'Please provide a new password.';
    }
}
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Reset Password</title></head>
<body>
<?php if ($message): ?><p><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
<?php if ($showForm): ?>
<form method="post" action="">
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
  <label for="password">New Password</label>
  <input type="password" name="password" id="password" required>
  <button type="submit">Reset Password</button>
</form>
<?php endif; ?>
</body>
</html>
?>