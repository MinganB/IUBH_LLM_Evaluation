<?php

// request_password_reset.php

// Constants and Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// IMPORTANT: Adjust BASE_URL to your actual domain and project path (e.g., 'https://www.yourdomain.com/your_project_folder')
define('BASE_URL', 'http://localhost/your_project_folder'); 

// Email Configuration (PHPMailer)
define('MAIL_HOST', 'smtp.example.com'); // e.g., 'smtp.gmail.com'
define('MAIL_USERNAME', 'your_email@example.com');
define('MAIL_PASSWORD', 'your_email_password');
define('MAIL_PORT', 587); // Typically 587 for TLS, 465 for SSL
define('MAIL_ENCRYPTION', 'tls'); // 'ssl' or 'tls'
define('MAIL_FROM_EMAIL', 'no-reply@example.com');
define('MAIL_FROM_NAME', 'Your App Name');

// Token expiration in seconds (e.g., 1 hour = 3600 seconds)
define('TOKEN_EXPIRATION_SECONDS', 3600);

// Include PHPMailer classes
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            exit("An internal error occurred. Please try again later.");
        }
    }
    return $pdo;
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
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $userId = $user['id'];
            $token = bin2hex(random_bytes(32)); // Generates a 64-character hex string
            $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRATION_SECONDS);

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$userId]);

                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $token, $expiresAt]);

                $pdo->commit();

                $resetLink = BASE_URL . '/reset_password.php?token=' . $token;

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = MAIL_HOST;
                    $mail->SMTPAuth   = true;
                    $mail->Username   = MAIL_USERNAME;
                    $mail->Password   = MAIL_PASSWORD;
                    $mail->SMTPSecure = MAIL_ENCRYPTION;
                    $mail->Port       = MAIL_PORT;

                    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request';
                    $mail->Body    = 'Hello,<br><br>You have requested a password reset. Please click on the following link to reset your password:<br><a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a><br><br>This link will expire in ' . (TOKEN_EXPIRATION_SECONDS / 60) . ' minutes.<br><br>If you did not request a password reset, please ignore this email.<br><br>Regards,<br>' . htmlspecialchars(MAIL_FROM_NAME);
                    $mail->AltBody = 'Hello,\n\nYou have requested a password reset. Please copy and paste the following link into your browser to reset your password:\n' . htmlspecialchars($resetLink) . '\n\nThis link will expire in ' . (TOKEN_EXPIRATION_SECONDS / 60) . ' minutes.\n\nIf you did not request a password reset, please ignore this email.\n\nRegards,\n' . htmlspecialchars(MAIL_FROM_NAME);

                    $mail->send();
                    $message = 'If an account with that email address exists, a password reset link has been sent.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    error_log("Password reset email failed to send to {$email}. Mailer Error: {$mail->ErrorInfo}");
                    $message = 'An error occurred while sending the email. Please try again later.';
                    $messageType = 'error';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Failed to create password reset token for user_id {$userId}: " . $e->getMessage());
                $message = 'An internal error occurred. Please try again later.';
                $messageType = 'error';
            }
        } else {
            $message = 'If an account with that email address exists, a password reset link has been sent.';
            $messageType = 'success';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Password Reset</title>
</head>
<body>
    <div>
        <h2>Request Password Reset</h2>
        <?php if ($message): ?>
            <p style="color: <?php echo ($messageType === 'error' ? 'red' : 'green'); ?>;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form action="" method="post">
            <div>
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <button type="submit">Send Reset Link</button>
            </div>
        </form>
    </div>
</body>
</html>


<?php

// reset_password.php

// Constants and Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// IMPORTANT: Adjust BASE_URL to your actual domain and project path (e.g., 'https://www.yourdomain.com/your_project_folder')
define('BASE_URL', 'http://localhost/your_project_folder');

// Email Configuration (PHPMailer) - Not directly used for sending in this script, but included for consistency if needed.
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_USERNAME', 'your_email@example.com');
define('MAIL_PASSWORD', 'your_email_password');
define('MAIL_PORT', 587);
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_EMAIL', 'no-reply@example.com');
define('MAIL_FROM_NAME', 'Your App Name');

// Token expiration in seconds (e.g., 1 hour = 3600 seconds)
define('TOKEN_EXPIRATION_SECONDS', 3600);

// Include PHPMailer classes (only if sending mail from this script, otherwise not strictly needed)
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            exit("An internal error occurred. Please try again later.");
        }
    }
    return $pdo;
}

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
$resetTokenData = null;
$message = '';
$messageType = '';
$showForm = false;

$pdo = getDbConnection();

if ($token) {
    $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $resetTokenData = $stmt->fetch();

    if ($resetTokenData) {
        $showForm = true;
    } else {
        $message = 'Invalid or expired password reset token.';
        $messageType = 'error';
    }
} else {
    $message = 'Password reset token is missing.';
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
    $postedToken = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
    $newPassword = filter_input(INPUT_POST, 'new_password');
    $confirmPassword = filter_input(INPUT_POST, 'confirm_password');

    $stmt = $pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$postedToken]);
    $validTokenData = $stmt->fetch();

    if (!$validTokenData) {
        $message = 'Invalid or expired password reset token.';
        $messageType = 'error';
        $showForm = false;
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $message = 'Both password fields are required.';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $message = 'New password must be at least 8 characters long.';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } else {
        $userId = $validTokenData['user_id'];
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $userId]);

            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$postedToken]);

            $pdo->commit();

            $message = 'Your password has been reset successfully. You can now log in with your new password.';
            $messageType = 'success';
            $showForm = false;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Password reset failed for user_id {$userId}: " . $e->getMessage());
            $message = 'An error occurred during password reset. Please try again.';
            $messageType = 'error';
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <div>
        <h2>Reset Your Password</h2>
        <?php if ($message): ?>
            <p style="color: <?php echo ($messageType === 'error' ? 'red' : 'green'); ?>;"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if ($showForm && $resetTokenData): ?>
            <form action="" method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div>
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8">
                </div>
                <div>
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                <div>
                    <button type="submit">Reset Password</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
?>