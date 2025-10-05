<?php
$pdo = null;
$message = '';
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = 'db_users';
$dbUser = getenv('DB_USER') ?: 'db_user';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    $pdo = null;
    error_log($e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if ($emailInput) {
        if ($pdo) {
            try {
                $stm = $pdo->prepare('SELECT id FROM users WHERE email = :email');
                $stm->execute(['email' => $emailInput]);
                $user = $stm->fetch();
                if ($user) {
                    $token = bin2hex(random_bytes(16));

                    $del = $pdo->prepare('DELETE FROM password_resets WHERE email = :email');
                    $del->execute(['email' => $emailInput]);

                    $ins = $pdo->prepare('INSERT INTO password_resets (email, token, created_at, used) VALUES (:email, :token, NOW(), 0)');
                    $ins->execute(['email' => $emailInput, 'token' => $token]);

                    $baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'https://example.com', '/');
                    $resetLink = $baseUrl . '/reset_password.php?token=' . urlencode($token);

                    $to = $emailInput;
                    $subject = 'Password Reset Request';
                    $headers = "From: no-reply@example.com\r\n";
                    $headers .= "Reply-To: no-reply@example.com\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                    $body = "You requested a password reset. If this wasn't you, ignore this email.\n\n";
                    $body .= "To reset your password, click the link below:\n";
                    $body .= $resetLink . "\n\n";
                    $body .= "If you did not request a password reset, please ignore this email.";

                    mail($to, $subject, $body, $headers);
                }
                $message = 'If the email is registered, a password reset link has been sent.';
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $message = 'An error occurred. Please try again later.';
            }
        } else {
            $message = 'Unable to connect to the database.';
        }
    } else {
        $message = 'Please enter a valid email address.';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Request Password Reset</title>
</head>
<body>
<h2>Request Password Reset</h2>
<?php if ($message): ?>
<p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>
<form method="POST" action="request_reset.php">
<label>Email:</label><br>
<input type="email" name="email" required autofocus />
<br><br>
<button type="submit">Send Reset Link</button>
</form>
</body>
</html>



<?php
$pdo = null;
$message = '';
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = 'db_users';
$dbUser = getenv('DB_USER') ?: 'db_user';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    $pdo = null;
    error_log($e->getMessage());
}

$tokenFromGet = isset($_GET['token']) ? $_GET['token'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (!$token || !$password || !$confirm) {
        $message = 'Please fill in all fields.';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters.';
    } else {
        if ($pdo) {
            try {
                $sth = $pdo->prepare('SELECT email, created_at FROM password_resets WHERE token = :token AND used = 0');
                $sth->execute(['token' => $token]);
                $row = $sth->fetch();
                if ($row) {
                    $email = $row['email'];
                    $createdAt = $row['created_at'];
                    $now = new DateTime();
                    $created = new DateTime($createdAt);
                    $diff = $now->getTimestamp() - $created->getTimestamp();
                    $maxAge = 24 * 3600;
                    if ($diff > $maxAge) {
                        $message = 'Token has expired. Please request a new password reset.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $upd = $pdo->prepare('UPDATE users SET password = :password WHERE email = :email');
                        $upd->execute(['password' => $hash, 'email' => $email]);
                        $mark = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
                        $mark->execute(['token' => $token]);
                        $message = 'Your password has been reset successfully.';
                    }
                } else {
                    $message = 'Invalid password reset token.';
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $message = 'An error occurred. Please try again later.';
            }
        } else {
            $message = 'Database connection not available.';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Reset Password</title>
</head>
<body>
<h2>Reset Password</h2>
<?php if ($message): ?>
<p><?= htmlspecialchars($message) ?></p>
<?php endif; ?>
<form method="POST" action="reset_password.php">
<input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenFromGet); ?>">
<label>New Password:</label><br>
<input type="password" name="password" required />
<br><br>
<label>Confirm Password:</label><br>
<input type="password" name="confirm_password" required />
<br><br>
<button type="submit">Reset Password</button>
</form>
</body>
</html>
?>