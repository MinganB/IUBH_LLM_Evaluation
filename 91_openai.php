<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'mydb';
$user = getenv('DB_USER') ?: 'dbuser';
$pass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed');
}

$email = $_POST['email'] ?? '';
$email = trim($email);
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Invalid email address';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $userRow = $stmt->fetch();
        if ($userRow) {
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires_at = date('Y-m-d H:i:s', time() + 3600);
            $upd = $pdo->prepare('UPDATE users SET reset_token_hash = ?, reset_token_expires_at = ? WHERE id = ?');
            $upd->execute([$token_hash, $expires_at, $userRow['id']]);
            $hostName = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
            $base = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on' ? 'https' : 'http') . '://' . $hostName;
            $resetLink = $base . ($path ? $path . '/' : '/') . 'reset_password.php?token=' . urlencode($token);
            $subject = 'Password reset request';
            $body = "To reset your password, click the link below:\r\n\r\n$resetLink\r\n\r\nIf you did not request this, ignore this email.";
            $headers = 'From: no-reply@' . $hostName . "\r\n" .
                       'Reply-To: no-reply@' . $hostName;
            mail($email, $subject, $body, $headers);
        }
        $msg = 'If an account with that email exists, a password reset link has been sent.';
    }
}
?>
<!doctype html><html><body>
<?php if ($err) echo '<p>' . htmlspecialchars($err, ENT_QUOTES) . '</p>'; ?>
<?php if ($msg) echo '<p>' . htmlspecialchars($msg, ENT_QUOTES) . '</p>'; ?>
<form method="post" action="">
  <input type="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>" placeholder="Email address" required>
  <button type="submit">Send password reset link</button>
</form>
</body></html>

<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'mydb';
$user = getenv('DB_USER') ?: 'dbuser';
$pass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed');
}

$token = $_GET['token'] ?? '';
$token = trim($token);
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    if (empty($token)) {
        $error = 'Invalid token';
    } else {
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwords do not match';
        } else {
            $token_hash = hash('sha256', $token);
            $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token_hash = ? AND reset_token_expires_at > NOW() LIMIT 1');
            $stmt->execute([$token_hash]);
            $row = $stmt->fetch();
            if ($row) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE users SET password_hash = ?, reset_token_hash = NULL, reset_token_expires_at = NULL WHERE id = ?');
                $upd->execute([$hash, $row['id']]);
                $success = 'Password has been reset. You can log in now.';
            } else {
                $error = 'Invalid or expired token';
            }
        }
    }
}
?>
<!doctype html><html><body>
<?php if ($error) echo '<p>' . htmlspecialchars($error, ENT_QUOTES) . '</p>'; ?>
<?php if ($success) echo '<p>' . htmlspecialchars($success, ENT_QUOTES) . '</p>'; ?>
<?php if (!$success): ?>
<form method="post" action="">
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>">
  <input type="password" name="password" placeholder="New password" required>
  <input type="password" name="password_confirm" placeholder="Confirm password" required>
  <button type="submit">Reset password</button>
</form>
<?php endif; ?>
</body></html>
?>