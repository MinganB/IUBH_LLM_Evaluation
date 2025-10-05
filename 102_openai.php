<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'db_users';
$dbuser = getenv('DB_USER') ?: 'db_user';
$dbpass = getenv('DB_PASS') ?: 'db_password';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbuser, $dbpass, $options);
} catch (Exception $e) {
    $pdo = null;
}

$message = '';
$blocked = false;
$ratePath = __DIR__ . '/rate_limits.json';
if (!file_exists($ratePath)) {
    file_put_contents($ratePath, json_encode([]));
}
$ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
$contents = json_decode(file_get_contents($ratePath), true);
$entries = $contents[$ip] ?? [];
$now = time();
$windowStart = $now - 900;
$entries = array_filter($entries, function($t) use ($windowStart) { return $t >= $windowStart; });
$limit = 5;
if (count($entries) >= $limit) {
    $blocked = true;
} else {
    $entries[] = $now;
    $contents[$ip] = $entries;
    file_put_contents($ratePath, json_encode($contents), LOCK_EX);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $timestamp = date('Y-m-d H:i:s');
    $logPath = __DIR__ . '/logs/password_reset_requests.log';
    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }
    $logLine = "$timestamp IP:$ip Email:$email\n";
    file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);

    if (!$blocked && $pdo) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $stmt->execute([':email' => $email]);
            $userExists = $stmt->fetchColumn() !== false;
        } catch (Exception $e) {
            $userExists = false;
        }

        if ($userExists) {
            $token = bin2hex(random_bytes(16));
            $createdAt = date('Y-m-d H:i:s');
            try {
                $stmt = $pdo->prepare('INSERT INTO password_resets (email, token, created_at, used) VALUES (:email, :token, :created_at, 0)');
                $stmt->execute([':email' => $email, ':token' => $token, ':created_at' => $createdAt]);
                $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST'] . '/reset_password.php?token=' . $token;
                $subject = 'Password Reset Request';
                $messageBody = "We received a password reset request for your account.\n\nIf you did not request this, you can ignore this email.\n\nTo reset your password, click the following link:\n$resetLink\n\nThis link will expire in 30 minutes.";
                $headers = 'From: no-reply@' . $_SERVER['HTTP_HOST'] . "\r\n" .
                           'Reply-To: no-reply@' . $_SERVER['HTTP_HOST'];
                @mail($email, $subject, $messageBody, $headers);
            } catch (Exception $e) {
            }
        }
    }
    $message = 'If the email address you provided exists, a password reset link has been sent.';
}
?>

<!DOCTYPE html>
<html>
<head><title>Request Password Reset</title></head>
<body>
<h2>Request Password Reset</h2>
<?php if ($message): ?>
<p><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<form method="post" action="request_reset.php">
  <label for="email">Email:</label>
  <input type="email" id="email" name="email" required />
  <button type="submit">Reset Password</button>
</form>
</body>
</html>
<?php
?>


<?php
$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'db_users';
$dbuser = getenv('DB_USER') ?: 'db_user';
$dbpass = getenv('DB_PASS') ?: 'db_password';

$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbuser, $dbpass, $options);
} catch (Exception $e) {
    $pdo = null;
}

$message = '';
$success = false;

$tokenValue = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $tokenValue = isset($_GET['token']) ? $_GET['token'] : '';
} else {
    $tokenValue = isset($_POST['token']) ? $_POST['token'] : '';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        $message = 'Invalid token.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT email, created_at FROM password_resets WHERE token = :token AND used = 0');
            $stmt->execute([':token' => $token]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $createdAt = strtotime($row['created_at']);
                $nowTime = time();
                if (($nowTime - $createdAt) <= 1800) {
                    if ($password === $confirm && strlen($password) >= 8) {
                        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt2 = $pdo->prepare('UPDATE users SET password_hash = :pw WHERE email = :email');
                        $stmt2->execute([':pw' => $passwordHash, ':email' => $row['email']]);
                        $stmt3 = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
                        $stmt3->execute([':token' => $token]);
                        $message = 'Your password has been reset successfully.';
                        $success = true;
                    } else {
                        $message = 'Invalid password or mismatch.';
                    }
                } else {
                    $message = 'Invalid or expired token.';
                }
            } else {
                $message = 'Invalid or expired token.';
            }
        } catch (Exception $e) {
            $message = 'Invalid or expired token.';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Reset Password</title></head>
<body>
<h2>Reset Password</h2>
<?php if ($message): ?>
<p><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if (!$success): ?>
<form method="post" action="reset_password.php">
  <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenValue); ?>">
  <label for="password">New Password:</label>
  <input type="password" id="password" name="password" required />
  <label for="confirm_password">Confirm Password:</label>
  <input type="password" id="confirm_password" name="confirm_password" required />
  <button type="submit">Set Password</button>
</form>
<?php endif; ?>
</body>
</html>
?>