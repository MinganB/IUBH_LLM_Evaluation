<?php
// request_reset.php - Script to accept email and send password reset link

session_start(); // Required for CSRF token in reset_password.php if shared session is desired, otherwise not strictly needed here.

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

// Site and email configuration
define('SITE_URL', 'http://localhost/ecommerce'); // Base URL of your site, e.g., 'https://www.yourdomain.com'
define('EMAIL_FROM', 'no-reply@ecommerce.com');
define('EMAIL_FROM_NAME', 'Your E-commerce Site');

function getDbConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        exit('A critical error occurred. Please try again later.');
    }
}

function sendEmail($to, $subject, $message) {
    $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $message, $headers);
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        $pdo = getDbConnection();

        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                $userId = $user['id'];
                $token = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
                $stmt->execute(['user_id' => $userId]);

                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
                $stmt->execute(['user_id' => $userId, 'token' => $token, 'expires_at' => $expiresAt]);

                $resetLink = SITE_URL . '/reset_password.php?token=' . $token;
                $emailSubject = 'Password Reset Request for Your Account';
                $emailBody = '
                    <html>
                    <head>
                        <title>Password Reset Request</title>
                    </head>
                    <body>
                        <p>Hello,</p>
                        <p>You have requested to reset your password for your account on ' . EMAIL_FROM_NAME . '.</p>
                        <p>Please click on the following link to reset your password:</p>
                        <p><a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a></p>
                        <p>This link will expire in 1 hour.</p>
                        <p>If you did not request a password reset, please ignore this email.</p>
                        <p>Regards,<br>' . EMAIL_FROM_NAME . '</p>
                    </body>
                    </html>
                ';

                if (sendEmail($email, $emailSubject, $emailBody)) {
                    $message = 'If an account with that email address exists, a password reset link has been sent to it.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to send email. Please try again later.';
                    $messageType = 'error';
                    error_log('Failed to send password reset email to: ' . $email);
                }
            } else {
                $message = 'If an account with that email address exists, a password reset link has been sent to it.';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'An unexpected error occurred. Please try again later.';
            $messageType = 'error';
            error_log('Password reset request DB error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Password Reset</title>
</head>
<body>
    <h1>Request Password Reset</h1>
    <?php if ($message): ?>
        <p style="color: <?php echo ($messageType === 'error' ? 'red' : 'green'); ?>;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form action="request_reset.php" method="POST">
        <div>
            <label for="email">Email Address:</label><br>
            <input type="email" id="email" name="email" required>
        </div>
        <div>
            <button type="submit">Send Reset Link</button>
        </div>
    </form>
    <p><a href="login.php">Back to Login</a></p>
</body>
</html>

<?php
// reset_password.php - Script to accept token and new password, then update password

session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

// Site and email configuration
define('SITE_URL', 'http://localhost/ecommerce');
define('EMAIL_FROM', 'no-reply@ecommerce.com');
define('EMAIL_FROM_NAME', 'Your E-commerce Site');

function getDbConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        exit('A critical error occurred. Please try again later.');
    }
}

function sendEmail($to, $subject, $message) {
    $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_FROM . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $message, $headers);
}

$message = '';
$messageType = '';
$token = '';
$validToken = false;
$userId = null;

$pdo = getDbConnection();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if ($token) {
        try {
            $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = :token");
            $stmt->execute(['token' => $token]);
            $resetEntry = $stmt->fetch();

            if ($resetEntry) {
                if (new DateTime() < new DateTime($resetEntry['expires_at'])) {
                    $validToken = true;
                    $userId = $resetEntry['user_id'];
                } else {
                    $message = 'The password reset link has expired.';
                    $messageType = 'error';
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
                    $stmt->execute(['token' => $token]);
                }
            } else {
                $message = 'The password reset link is invalid.';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'An unexpected error occurred. Please try again later.';
            $messageType = 'error';
            error_log('Reset password token validation DB error: ' . $e->getMessage());
        }
    } else {
        $message = 'No reset token provided.';
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'error';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $newPassword = filter_input(INPUT_POST, 'password');
        $confirmPassword = filter_input(INPUT_POST, 'confirm_password');

        if (!$token || !$newPassword || !$confirmPassword) {
            $message = 'All fields are required.';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 8) {
            $message = 'Password must be at least 8 characters long.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match.';
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = :token");
                $stmt->execute(['token' => $token]);
                $resetEntry = $stmt->fetch();

                if ($resetEntry && new DateTime() < new DateTime($resetEntry['expires_at'])) {
                    $userId = $resetEntry['user_id'];
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id");
                    $stmt->execute(['password_hash' => $passwordHash, 'user_id' => $userId]);

                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
                    $stmt->execute(['token' => $token]);

                    $pdo->commit();

                    $message = 'Your password has been reset successfully. You can now log in with your new password.';
                    $messageType = 'success';
                    header('Refresh: 5; URL=' . SITE_URL . '/login.php');
                } else {
                    $message = 'The password reset link is invalid or has expired.';
                    $messageType = 'error';
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
                    $stmt->execute(['token' => $token]);
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $message = 'An unexpected error occurred during password update. Please try again.';
                $messageType = 'error';
                error_log('Password update DB error: ' . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <h1>Reset Your Password</h1>
    <?php if ($message): ?>
        <p style="color: <?php echo ($messageType === 'error' ? 'red' : 'green'); ?>;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($validToken && $_SERVER['REQUEST_METHOD'] === 'GET'): ?>
        <form action="reset_password.php" method="POST">
            <div>
                <label for="password">New Password:</label><br>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            <div>
                <label for="confirm_password">Confirm New Password:</label><br>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div>
                <button type="submit">Reset Password</button>
            </div>
        </form>
    <?php elseif (!($validToken && $_SERVER['REQUEST_METHOD'] === 'GET') && $messageType !== 'success'): ?>
        <?php if (!$message): ?>
            <p style="color: red;">Unable to process your request. The reset link may be invalid or expired.</p>
        <?php endif; ?>
        <p><a href="request_reset.php">Request a new password reset link.</a></p>
    <?php endif; ?>

    <?php if ($messageType === 'success'): ?>
        <p>You will be redirected to the login page shortly.</p>
    <?php endif; ?>
</body>
</html>
?>