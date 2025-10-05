<?php

class PasswordResetManager
{
    private PDO $pdo;
    private string $resetLinkBaseUrl;
    private string $senderEmail;
    private string $senderName;
    private int $tokenLifespanSeconds;

    public function __construct(PDO $pdo, string $resetLinkBaseUrl, string $senderEmail, string $senderName = 'Admin', int $tokenLifespanSeconds = 3600)
    {
        $this->pdo = $pdo;
        $this->resetLinkBaseUrl = rtrim($resetLinkBaseUrl, '/');
        $this->senderEmail = $senderEmail;
        $this->senderName = $senderName;
        $this->tokenLifespanSeconds = $tokenLifespanSeconds;
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function requestPasswordReset(string $email): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return true;
        }

        $userId = $user['id'];

        $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $expiresAt = date('Y-m-d H:i:s', time() + $this->tokenLifespanSeconds);

        $stmt = $this->pdo->prepare(
            "INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (:user_id, :token_hash, :expires_at, NOW())"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':token_hash' => $tokenHash,
            ':expires_at' => $expiresAt
        ]);

        $resetLink = $this->resetLinkBaseUrl . '?action=reset_password&token=' . $rawToken;
        $subject = 'Password Reset Request';
        $message = "You have requested a password reset. Please click the following link to reset your password:\n\n";
        $message .= $resetLink;
        $message .= "\n\nThis link will expire in " . ($this->tokenLifespanSeconds / 60) . " minutes.";
        $message .= "\nIf you did not request this, please ignore this email.";

        $headers = "From: " . $this->senderName . " <" . $this->senderEmail . ">\r\n";
        $headers .= "Reply-To: " . $this->senderEmail . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $mailSent = mail($email, $subject, $message, $headers);

        return $mailSent;
    }

    public function validateResetToken(string $token): int|false
    {
        $tokenHash = hash('sha256', $token);

        $stmt = $this->pdo->prepare(
            "SELECT user_id, expires_at FROM password_resets WHERE token_hash = :token_hash"
        );
        $stmt->execute([':token_hash' => $tokenHash]);
        $resetEntry = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resetEntry) {
            return false;
        }

        if (strtotime($resetEntry['expires_at']) < time()) {
            $this->deleteToken($tokenHash);
            return false;
        }

        return (int)$resetEntry['user_id'];
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $userId = $this->validateResetToken($token);

        if (!$userId) {
            return false;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hashedPassword === false) {
            throw new Exception("Password hashing failed.");
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
            $stmt->execute([
                ':password_hash' => $hashedPassword,
                ':id' => $userId
            ]);

            $this->deleteToken(hash('sha256', $token));

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Password reset failed: " . $e->getMessage());
            return false;
        }
    }

    private function deleteToken(string $tokenHash): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE token_hash = :token_hash");
        $stmt->execute([':token_hash' => $tokenHash]);
    }
}

$dbHost = 'localhost';
$dbName = 'your_database_name';
$dbUser = 'your_db_user';
$dbPass = 'your_db_password';
$resetLinkBaseUrl = 'http://localhost/password_reset.php';
$senderEmail = 'noreply@yourdomain.com';
$senderName = 'My App Support';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database connection failed: " . $e->getMessage());
    exit("An internal server error occurred.");
}

$passwordResetManager = new PasswordResetManager($pdo, $resetLinkBaseUrl, $senderEmail, $senderName);

$responseMessage = '';
$responseError = '';
$displayNewPasswordForm = false;
$tokenForForm = '';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'request_reset') {
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

        if (!$email) {
            $responseError = 'Please enter a valid email address.';
        } else {
            try {
                $passwordResetManager->requestPasswordReset($email);
                $responseMessage = 'If an account with that email exists, a password reset link has been sent.';
            } catch (Exception $e) {
                $responseError = 'An unexpected error occurred.';
                error_log("Password reset request error: " . $e->getMessage());
            }
        }
    } elseif ($action === 'set_new_password') {
        $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
            $responseError = 'All fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $responseError = 'Passwords do not match.';
        } elseif (strlen($newPassword) < 8) {
            $responseError = 'New password must be at least 8 characters long.';
        } else {
            try {
                if ($passwordResetManager->resetPassword($token, $newPassword)) {
                    $responseMessage = 'Your password has been reset successfully. You can now log in with your new password.';
                } else {
                    $responseError = 'Invalid or expired password reset token, or failed to update password. Please request a new reset.';
                    $tokenForForm = $token;
                    $displayNewPasswordForm = true;
                }
            } catch (Exception $e) {
                $responseError = 'An unexpected error occurred.';
                error_log("Password reset error: " . $e->getMessage());
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'reset_password') {
        $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);

        if (empty($token)) {
            $responseError = 'Password reset token is missing.';
        } else {
            try {
                $userId = $passwordResetManager->validateResetToken($token);
                if ($userId) {
                    $displayNewPasswordForm = true;
                    $tokenForForm = $token;
                } else {
                    $responseError = 'Invalid or expired password reset token. Please request a new reset.';
                }
            } catch (Exception $e) {
                $responseError = 'An unexpected error occurred during token validation.';
                error_log("Token validation error: " . $e->getMessage());
            }
        }
    }
}
?>