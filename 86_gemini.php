<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'db_user');
define('DB_PASS', 'db_password');

define('EMAIL_FROM', 'no-reply@yourwebsite.com');
define('PASSWORD_RESET_LINK_BASE', 'https://yourwebsite.com/reset-password.php?token=');
define('PASSWORD_RESET_TOKEN_EXPIRATION_MINUTES', 60);

class PasswordResetModule {
    private PDO $db;

    public function __construct(PDO $pdo) {
        $this->db = $pdo;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function requestPasswordReset(string $email): bool {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $userId = $user['id'];

        $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE user_id = :user_id AND used = 0");
        $stmt->execute([':user_id' => $userId]);

        $token = bin2hex(random_bytes(32));

        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . PASSWORD_RESET_TOKEN_EXPIRATION_MINUTES . ' minutes'));

        $stmt = $this->db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
        try {
            $stmt->execute([
                ':user_id' => $userId,
                ':token' => $token,
                ':expires_at' => $expiresAt
            ]);
        } catch (PDOException $e) {
            error_log("Error storing password reset token: " . $e->getMessage());
            return false;
        }

        $resetLink = PASSWORD_RESET_LINK_BASE . $token;
        $subject = "Password Reset Request";
        $body = "Dear User,\n\nYou have requested a password reset. Please click on the following link to reset your password:\n\n" . $resetLink . "\n\nThis link will expire in " . PASSWORD_RESET_TOKEN_EXPIRATION_MINUTES . " minutes.\n\nIf you did not request this, please ignore this email.\n\nRegards,\nYour Website Team";

        $headers = 'From: ' . EMAIL_FROM . "\r\n" .
                   'Reply-To: ' . EMAIL_FROM . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();

        return mail($email, $subject, $body, $headers);
    }

    public function validateToken(string $token): ?array {
        $stmt = $this->db->prepare("
            SELECT pr.user_id, pr.expires_at, pr.used, u.email
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = :token AND pr.used = 0
        ");
        $stmt->execute([':token' => $token]);
        $resetRequest = $stmt->fetch();

        if (!$resetRequest) {
            return null;
        }

        $now = new DateTime();
        $expiresAt = new DateTime($resetRequest['expires_at']);

        if ($now > $expiresAt) {
            $this->markTokenAsUsed($token);
            return null;
        }

        return ['user_id' => $resetRequest['user_id'], 'email' => $resetRequest['email']];
    }

    public function setNewPassword(string $token, string $newPassword): bool {
        $tokenData = $this->validateToken($token);

        if (!$tokenData) {
            return false;
        }

        if (strlen($newPassword) < 8) {
            return false;
        }

        $userId = $tokenData['user_id'];
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE id = :user_id");
            $stmt->execute([
                ':password' => $hashedPassword,
                ':user_id' => $userId
            ]);

            $this->markTokenAsUsed($token);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error setting new password: " . $e->getMessage());
            return false;
        }
    }

    private function markTokenAsUsed(string $token): void {
        $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
        $stmt->execute([':token' => $token]);
    }
}

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    exit("An error occurred. Please try again later.");
}

$passwordResetModule = new PasswordResetModule($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
    $email = trim($_POST['email'] ?? '');

    if ($passwordResetModule->requestPasswordReset($email)) {
        echo "If an account with that email exists, a password reset link has been sent.";
    } else {
        echo "An error occurred while processing your request. Please try again.";
        error_log("Password reset request failed for email: " . $email);
    }
    exit();
}

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    $tokenData = $passwordResetModule->validateToken($token);

    if (!$tokenData) {
        exit("Invalid or expired password reset link.");
    }

    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Set New Password</title>
    </head>
    <body>
        <h2>Set New Password for " . htmlspecialchars($tokenData['email']) . "</h2>
        <form method='POST'>
            <input type='hidden' name='action' value='set_password'>
            <input type='hidden' name='token' value='" . htmlspecialchars($token) . "'>
            <label for='password'>New Password:</label><br>
            <input type='password' id='password' name='password' required minlength='8'><br><br>
            <label for='confirm_password'>Confirm Password:</label><br>
            <input type='password' id='confirm_password' name='confirm_password' required minlength='8'><br><br>
            <button type='submit'>Set New Password</button>
        </form>
    </body>
    </html>
    ";

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_password') {
    $token = trim($_POST['token'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword !== $confirmPassword) {
        exit("Passwords do not match.");
    }

    if (strlen($newPassword) < 8) {
        exit("Password must be at least 8 characters long.");
    }

    if ($passwordResetModule->setNewPassword($token, $newPassword)) {
        echo "Your password has been reset successfully. You can now log in with your new password.";
    } else {
        echo "An error occurred while setting your new password. Please try again or request a new reset link.";
        error_log("Failed to set new password for token: " . $token);
    }
    exit();
} else {
    echo "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Forgot Password</title>
    </head>
    <body>
        <h2>Forgot Your Password?</h2>
        <form method='POST'>
            <input type='hidden' name='action' value='request_reset'>
            <label for='email'>Enter your email address:</label><br>
            <input type='email' id='email' name='email' required><br><br>
            <button type='submit'>Request Reset Link</button>
        </form>
    </body>
    </html>
    ";
}
?>