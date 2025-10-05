<?php
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
$baseUrl = 'http://' . $host;
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = 'db_users';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $token = bin2hex(random_bytes(16));
            $createdAt = date('Y-m-d H:i:s');
            $stmt2 = $pdo->prepare('INSERT INTO password_resets (email, token, created_at, used) VALUES (:email, :token, :created_at, 0)');
            $stmt2->execute([':email' => $email, ':token' => $token, ':created_at' => $createdAt]);
            $resetLink = $baseUrl . '/reset_password.php?token=' . $token;
            $subject = 'Password Reset Request';
            $messageBody = 'To reset your password, please click the following link: ' . $resetLink;
            $headers = 'From: no-reply@example.com' . "\r\n" .
                       'Reply-To: no-reply@example.com' . "\r\n" .
                       'Content-Type: text/plain; charset=UTF-8';
            mail($email, $subject, $messageBody, $headers);
            $message = 'If an account with that email exists, a password reset link has been sent.';
        } else {
            $message = 'If an account with that email exists, a password reset link has been sent.';
        }
    }
}
?>
<!doctype html>
<html>
<head><title>Request Password Reset</title></head>
<body>
<?php if ($error) { echo '<p style="color:red;">'.$error.'</p>'; } ?>
<?php if ($message) { echo '<p>'.$message.'</p>'; } ?>
<form method="POST" action="request_reset.php">
  <label for="email">Email</label>
  <input type="email" id="email" name="email" required />
  <button type="submit">Send Reset Link</button>
</form>
</body>
</html>

<?php
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = 'db_users';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}

$tokenValue = '';
$password = '';
$confirm = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tokenValue = $_GET['token'] ?? '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenValue = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($tokenValue)) {
        $error = 'Invalid token';
    }
    if ($password !== $confirm) {
        $error = 'Passwords do not match';
    }
    if ($error === '') {
        $stmt = $pdo->prepare('SELECT email, created_at, used FROM password_resets WHERE token = :token LIMIT 1');
        $stmt->execute([':token' => $tokenValue]);
        $row = $stmt->fetch();
        if ($row && (int)$row['used'] === 0) {
            $createdAt = strtotime($row['created_at']);
            if (time() - $createdAt > 86400) {
                $error = 'Token has expired';
            } else {
                $email = $row['email'];
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt2 = $pdo->prepare('UPDATE users SET password = :pwd WHERE email = :email');
                $stmt2->execute([':pwd' => $hash, ':email' => $email]);
                $stmt3 = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
                $stmt3->execute([':token' => $tokenValue]);
                $success = 'Password has been reset successfully.';
            }
        } else {
            $error = 'Invalid or already used token';
        }
    }
}
?>
<!doctype html>
<html>
<head><title>Reset Password</title></head>
<body>
<?php if ($error) { echo '<p style="color:red;">'.$error.'</p>'; } ?>
<?php if ($success) { echo '<p style="color:green;">'.$success.'</p>'; } ?>
<form method="POST" action="reset_password.php">
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenValue, ENT_QUOTES); ?>">
  <label for="password">New Password</label>
  <input type="password" id="password" name="password" required />
  <label for="confirm_password">Confirm Password</label>
  <input type="password" id="confirm_password" name="confirm_password" required />
  <button type="submit">Reset Password</button>
</form>
</body></html>
?>