<?php

// Database configuration
$dbHost = 'localhost';
$dbName = 'db_users';
$dbUser = 'root'; // Use a dedicated user with minimal privileges in production
$dbPass = '';     // Use a strong, unique password in production

// Log file paths (ensure 'log' directory exists and is writable by the web server)
$logFilePath = __DIR__ . '/log/password_resets.log';
$ipRateLimitLogPath = __DIR__ . '/log/ip_rate_limits.log';

// Rate limiting settings
$ipRateLimitPeriod = 300; // 5 minutes
$ipRateLimitMaxRequests = 5; // Max 5 requests per IP in 5 minutes
$emailRateLimitPeriod = 300; // 5 minutes
$emailRateLimitMaxRequests = 2; // Max 2 reset requests per email in 5 minutes

// Consistent response time target (in microseconds) to prevent timing attacks
$targetExecutionTimeUs = 1000000; // 1 second
$scriptStartTimeUs = microtime(true) * 1000000;

function logEvent(string $message, string $filePath): void
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($filePath, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

function enforceConsistentTime(int $startTimeUs, int $targetTimeUs): void
{
    $elapsedTimeUs = microtime(true) * 1000000 - $startTimeUs;
    $sleepTimeUs = $targetTimeUs - $elapsedTimeUs;
    if ($sleepTimeUs > 0) {
        usleep($sleepTimeUs);
    }
}

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    logEvent("Database connection failed in request_reset.php: " . $e->getMessage(), $logFilePath);
    header('Content-Type: text/html; charset=utf-8');
    echo 'An unexpected error occurred. Please try again later.';
    enforceConsistentTime($scriptStartTimeUs, $targetExecutionTimeUs);
    exit;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
logEvent("Password reset request from IP: $clientIp", $logFilePath);

$ipRequests = [];
if (file_exists($ipRateLimitLogPath)) {
    $logContent = @file_get_contents($ipRateLimitLogPath);
    if ($logContent !== false) {
        $lines = explode("\n", trim($logContent));
        $currentTime = time();
        foreach ($lines as $line) {
            if (strpos($line, $clientIp) !== false) {
                preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] IP: (.*)$/', $line, $matches);
                if (isset($matches[1]) && isset($matches[2]) && $matches[2] === $clientIp) {
                    $logTimestamp = strtotime($matches[1]);
                    if ($currentTime - $logTimestamp < $ipRateLimitPeriod) {
                        $ipRequests[] = $logTimestamp;
                    }
                }
            }
        }
    }
}
logEvent("IP: $clientIp", $ipRateLimitLogPath);

if (count($ipRequests) >= $ipRateLimitMaxRequests) {
    header('Content-Type: text/html; charset=utf-8');
    echo 'If an account with that email address exists, a password reset link has been sent.';
    enforceConsistentTime($scriptStartTimeUs, $targetExecutionTimeUs);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Content-Type: text/html; charset=utf-8');
        echo 'If an account with that email address exists, a password reset link has been sent.';
        enforceConsistentTime($scriptStartTimeUs, $targetExecutionTimeUs);
        exit;
    }

    $foundUserEmail = null;
    $tokenGenerated = false;
    $emailSent = false;

    try {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $foundUserEmail = $user['email'];

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_resets WHERE email = :email AND created_at > (NOW() - INTERVAL :period SECOND)");
            $stmt->execute([':email' => $foundUserEmail, ':period' => $emailRateLimitPeriod]);
            $recentRequests = $stmt->fetchColumn();

            if ($recentRequests >= $emailRateLimitMaxRequests) {
                logEvent("Email rate limit exceeded for: $foundUserEmail", $logFilePath);
            } else {
                $token = bin2hex(random_bytes(16));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email AND used = FALSE AND expires_at > NOW()");
                    $stmt->execute([':email' => $foundUserEmail]);

                    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, expires_at, used) VALUES (:email, :token, NOW(), :expires_at, FALSE)");
                    $stmt->execute([
                        ':email' => $foundUserEmail,
                        ':token' => $token,
                        ':expires_at' => $expiresAt
                    ]);
                    $pdo->commit();
                    $tokenGenerated = true;

                    $resetLink = 'http://yourdomain.com/reset_password.php?token=' . $token; // IMPORTANT: Change yourdomain.com
                    $subject = 'Password Reset Request';
                    $message = "Hello,\n\nYou have requested a password reset. Please click on the following link to reset your password:\n\n" . $resetLink . "\n\nThis link will expire in 30 minutes.\n\nIf you did not request this, please ignore this email.";
                    $headers = 'From: no-reply@yourdomain.com' . "\r\n" . // IMPORTANT: Change to a valid sender email
                               'Reply-To: no-reply@yourdomain.com' . "\r\n" .
                               'X-Mailer: PHP/' . phpversion();

                    if (mail($foundUserEmail, $subject, $message, $headers)) {
                        $emailSent = true;
                    } else {
                        logEvent("Failed to send password reset email to: $foundUserEmail", $logFilePath);
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    logEvent("DB transaction error for email '$email': " . $e->getMessage(), $logFilePath);
                }
            }
        }
    } catch (PDOException $e) {
        logEvent("Database error during request_reset process for email '$email': " . $e->getMessage(), $logFilePath);
    }

    header('Content-Type: text/html; charset=utf-8');
    echo 'If an account with that email address exists, a password reset link has been sent.';
    enforceConsistentTime($scriptStartTimeUs, $targetExecutionTimeUs);
    exit;

} else {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Request Password Reset</title>
    </head>
    <body>
        <h2>Request Password Reset</h2>
        <form action="request_reset.php" method="POST">
            <label for="email">Email:</label><br>
            <input type="email" id="email" name="email" required><br><br>
            <button type="submit">Send Reset Link</button>
        </form>
    </body>
    </html>
    <?php
}

enforceConsistentTime($scriptStartTimeUs, $targetExecutionTimeUs);


<?php

// Database configuration
$dbHost = 'localhost';
$dbName = 'db_users';
$dbUser = 'root'; // Use a dedicated user with minimal privileges in production
$dbPass = '';     // Use a strong, unique password in production

// Log file path (ensure 'log' directory exists and is writable by the web server)
$logFilePath = __DIR__ . '/log/password_resets.log';

function logEvent(string $message, string $filePath): void
{
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($filePath, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    logEvent("Database connection failed in reset_password.php: " . $e->getMessage(), $logFilePath);
    header('Content-Type: text/html; charset=utf-8');
    echo 'An unexpected error occurred. Please try again later.';
    exit;
}

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$message = '';
$displayForm = true;

if (empty($token) || !ctype_xdigit($token) || strlen($token) !== 32) {
    $message = 'Invalid or missing password reset token.';
    $displayForm = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $displayForm) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        $message = 'New password and confirm password do not match.';
    } elseif (empty($newPassword) || strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters long.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = :token AND expires_at > NOW() AND used = FALSE");
            $stmt->execute([':token' => $token]);
            $resetRequest = $stmt->fetch();

            if ($resetRequest) {
                $userEmail = $resetRequest['email'];
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
                $stmt->execute([':password' => $hashedPassword, ':email' => $userEmail]);

                $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE token = :token");
                $stmt->execute([':token' => $token]);

                $pdo->commit();
                $message = 'Your password has been reset successfully. You can now log in.';
                logEvent("Password successfully reset for email: $userEmail (via token: $token)", $logFilePath);
                $displayForm = false;
            } else {
                $pdo->rollBack();
                $message = 'Invalid or expired password reset token.';
                logEvent("Failed password reset attempt - Invalid/expired token: $token", $logFilePath);
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            logEvent("Database error during password reset for token '$token': " . $e->getMessage(), $logFilePath);
            $message = 'An unexpected error occurred. Please try again.';
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Your Password</h2>

    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($displayForm): ?>
        <form action="reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <label for="password">New Password:</label><br>
            <input type="password" id="password" name="password" required><br><br>
            <label for="confirm_password">Confirm New Password:</label><br>
            <input type="password" id="confirm_password" name="confirm_password" required><br><br>
            <button type="submit">Reset Password</button>
        </form>
    <?php endif; ?>
</body>
</html>
?>