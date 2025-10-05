<?php
$pdo = null;
$errors = [];
$success = '';

$dbHost = 'localhost';
$dbName = 'db_users';
$dbUser = 'db_user';
$dbPass = 'db_password';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    $pdo = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        if ($pdo) {
            $stmt = $pdo->prepare('SELECT email FROM users WHERE email = :email');
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $token = bin2hex(random_bytes(16));
                $now = date('Y-m-d H:i:s');
                $ins = $pdo->prepare('INSERT INTO password_resets (email, token, created_at, used) VALUES (:email, :token, :created_at, 0)');
                $ins->execute(['email' => $email, 'token' => $token, 'created_at' => $now]);

                $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = $scheme . '://' . $host;
                $resetLink = $baseUrl . '/reset_password.php?token=' . urlencode($token);

                $subject = 'Password Reset Request';
                $message = "To reset your password, please visit the following link:\n\n$resetLink\n\nIf you did not request this, please ignore this email.";
                $headers = 'From: no-reply@example.com' . "\r\n" .
                           'Reply-To: no-reply@example.com' . "\r\n" .
                           'Content-Type: text/plain; charset=utf-8';
                mail($email, $subject, $message, $headers);
            }
            $success = 'If the email exists, a password reset link has been sent.';
        } else {
            $errors[] = 'Database connection error';
        }
    } else {
        $errors[] = 'Invalid email address';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Request Password Reset</title>
</head>
<body>
<?php
if (!empty($success)) {
    echo '<p>' . htmlspecialchars($success) . '</p>';
}
if (!empty($errors)) {
    foreach ($errors as $err) {
        echo '<p>' . htmlspecialchars($err) . '</p>';
    }
}
?>
<form method="post" action="request_reset.php">
  <label>Email</label>
  <input type="email" name="email" required />
  <button type="submit">Send Reset Link</button>
</form>
</body>
</html>

<?php
$pdo = null;
$errors = [];
$success = '';

$dbHost = 'localhost';
$dbName = 'db_users';
$dbUser = 'db_user';
$dbPass = 'db_password';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    $pdo = null;
}

$tokenForForm = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $tokenForForm = $_POST['token'];
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    if ($password !== $confirm) $errors[] = 'Passwords do not match';

    if (empty($errors) && $pdo) {
        $stmt = $pdo->prepare('SELECT * FROM password_resets WHERE token = :token');
        $stmt->execute(['token' => $tokenForForm]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $errors[] = 'Invalid token';
        } elseif ((int)$reset['used'] === 1) {
            $errors[] = 'Token has already been used';
        } else {
            $createdAt = $reset['created_at'];
            $created = new DateTime($createdAt);
            $now = new DateTime();
            $diff = $now->diff($created);
            $hours = $diff->days * 24 + $diff->h;
            if ($hours >= 24) {
                $errors[] = 'Token has expired';
            } else {
                $email = $reset['email'];
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $upd = $pdo->prepare('UPDATE users SET password = :password WHERE email = :email');
                $upd->execute(['password' => $hash, 'email' => $email]);
                $mark = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
                $mark->execute(['token' => $tokenForForm]);
                $success = 'Password has been reset successfully.';
                $tokenForForm = '';
            }
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $tokenForForm = $_GET['token'];
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Reset Password</title>
</head>
<body>
<?php
if (!empty($errors)) {
    foreach ($errors as $err) {
        echo '<p>' . htmlspecialchars($err) . '</p>';
    }
}
if (!empty($success)) {
    echo '<p>' . htmlspecialchars($success) . '</p>';
}
?>
<form method="post" action="reset_password.php">
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenForForm, ENT_QUOTES); ?>">
  <label>New Password</label>
  <input type="password" name="password" required /><br/>
  <label>Confirm Password</label>
  <input type="password" name="confirm_password" required /><br/>
  <button type="submit">Reset Password</button>
</form>
</body>
</html>
?>