<?php

// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

define('BASE_URL', 'http://localhost/password_reset'); // Your website's base URL for the password reset pages
define('EMAIL_SENDER', 'no-reply@yourwebsite.com');
define('PASSWORD_RESET_EXPIRATION_HOURS', 1); // Token expiration time in hours

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

?>
<?php

// PasswordResetManager.php

class PasswordResetManager {
    private PDO $db;
    private string $baseUrl;
    private string $emailSender;
    private int $expirationHours;

    public function __construct(PDO $db, string $baseUrl, string $emailSender, int $expirationHours) {
        $this->db = $db;
        $this->baseUrl = $baseUrl;
        $this->emailSender = $emailSender;
        $this->expirationHours = $expirationHours;
    }

    public function requestReset(string $email): bool {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return true; // Return true to prevent email enumeration
        }

        $userId = $user['id'];
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTime())->modify('+' . $this->expirationHours . ' hours')->format('Y-m-d H:i:s');

        $stmt = $this->db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at) ON DUPLICATE KEY UPDATE token = VALUES(token), expires_at = VALUES(expires_at)");
        $stmt->execute([
            ':user_id' => $userId,
            ':token' => $token,
            ':expires_at' => $expiresAt
        ]);

        return $this->sendResetEmail($email, $token);
    }

    public function validateToken(string $token): ?int {
        $stmt = $this->db->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = :token");
        $stmt->execute([':token' => $token]);
        $resetEntry = $stmt->fetch();

        if (!$resetEntry) {
            return null;
        }

        $expiresAt = new DateTime($resetEntry['expires_at']);
        $now = new DateTime();

        if ($now > $expiresAt) {
            return null;
        }

        return (int)$resetEntry['user_id'];
    }

    public function resetPassword(string $token, string $newPassword): bool {
        if (strlen($newPassword) < 8) {
            return false;
        }

        $userId = $this->validateToken($token);

        if (!$userId) {
            return false;
        }

        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
            $stmt->execute([
                ':password_hash' => $passwordHash,
                ':id' => $userId
            ]);

            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = :token");
            $stmt->execute([':token' => $token]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    private function sendResetEmail(string $toEmail, string $token): bool {
        $resetLink = $this->baseUrl . "/password_reset_form.php?token=" . urlencode($token);

        $subject = "Password Reset Request";
        $headers = "From: " . $this->emailSender . "\r\n";
        $headers .= "Reply-To: " . $this->emailSender . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $message = "
        <html>
        <head>
            <title>Password Reset Request</title>
        </head>
        <body>
            <p>You have requested a password reset for your account.</p>
            <p>Please click on the following link to reset your password:</p>
            <p><a href=\"{$resetLink}\">{$resetLink}</a></p>
            <p>This link will expire in {$this->expirationHours} hour(s).</p>
            <p>If you did not request a password reset, please ignore this email.</p>
            <p>Thank you,</p>
            <p>Your Website Team</p>
        </body>
        </html>
        ";

        return mail($toEmail, $subject, $message, $headers);
    }
}

?>
<?php

// password_reset_request.php

require_once 'config.php';
require_once 'PasswordResetManager.php';

$message = '';
$manager = new PasswordResetManager($pdo, BASE_URL, EMAIL_SENDER, PASSWORD_RESET_EXPIRATION_HOURS);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if (empty($email)) {
        $message = 'Please enter your email address.';
    } elseif ($manager->requestReset($email)) {
        $message = 'If an account with that email address exists, a password reset link has been sent.';
    } else {
        $message = 'An error occurred while processing your request. Please try again later.';
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
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form action="password_reset_request.php" method="post">
        <label for="email">Email Address:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        <button type="submit">Request Reset Link</button>
    </form>
    <p><a href="/login.php">Back to Login</a></p>
</body>
</html>
<?php

// password_reset_form.php

require_once 'config.php';
require_once 'PasswordResetManager.php';

$message = '';
$manager = new PasswordResetManager($pdo, BASE_URL, EMAIL_SENDER, PASSWORD_RESET_EXPIRATION_HOURS);
$token = $_GET['token'] ?? '';
$isValidToken = false;

if (!empty($token)) {
    $userId = $manager->validateToken($token);
    if ($userId) {
        $isValidToken = true;
    } else {
        $message = 'Invalid or expired password reset token.';
    }
} else {
    $message = 'No password reset token provided.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidToken) {
    $postedToken = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($postedToken !== $token) {
        $message = 'Token mismatch. Please try again.';
        $isValidToken = false;
    } elseif (empty($newPassword) || empty($confirmPassword)) {
        $message = 'Please enter and confirm your new password.';
    } elseif (strlen($newPassword) < 8) {
        $message = 'Password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
    } elseif ($manager->resetPassword($postedToken, $newPassword)) {
        header('Location: password_reset_success.php');
        exit;
    } else {
        $message = 'An error occurred while setting your new password. Please try again.';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password</title>
</head>
<body>
    <h1>Set New Password</h1>
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($isValidToken): ?>
        <form action="password_reset_form.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <label for="new_password">New Password:</label><br>
            <input type="password" id="new_password" name="new_password" required><br><br>
            <label for="confirm_password">Confirm New Password:</label><br>
            <input type="password" id="confirm_password" name="confirm_password" required><br><br>
            <button type="submit">Set New Password</button>
        </form>
    <?php elseif (empty($message)): ?>
        <p>Access denied. Please request a new password reset link.</p>
    <?php endif; ?>
    <p><a href="password_reset_request.php">Request a new reset link</a></p>
</body>
</html>
<?php

// password_reset_success.php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Successful</title>
</head>
<body>
    <h1>Password Reset Successful</h1>
    <p>Your password has been successfully reset. You can now log in with your new password.</p>
    <p><a href="/login.php">Go to Login Page</a></p>
</body>
</html>
?>