<?php

class Database {
    private static ?PDO $instance = null;
    private static string $host = 'localhost';
    private static string $dbName = 'db_users';
    private static string $user = 'db_user';
    private static string $pass = 'db_password';
    private static array $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    private function __construct() {}

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbName . ";charset=utf8mb4";
            try {
                self::$instance = new PDO($dsn, self::$user, self::$pass, self::$options);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                die("Database connection failed. Please try again later.");
            }
        }
        return self::$instance;
    }
}
<?php

class Mailer {
    public function sendPasswordResetEmail(string $recipientEmail, string $token): bool {
        $resetLink = "http://localhost/public/reset-password.php?token=" . urlencode($token);
        $subject = "Password Reset Request";
        $body = "You have requested a password reset. Please click on the following link to reset your password: " . $resetLink . "\n\n";
        $body .= "This link will expire in 1 hour. If you did not request this, please ignore this email.";

        error_log("Password reset email sent to: $recipientEmail with link: $resetLink");
        return true;
    }
}
<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';

class PasswordResetService {
    private PDO $db;
    private Mailer $mailer;
    private int $token_expiry_minutes = 60;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->mailer = new Mailer();
    }

    public function requestPasswordReset(string $email): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format.'];
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if (!$stmt->fetch()) {
            return ['success' => true, 'message' => 'If your email is registered, you will receive a password reset link.'];
        }

        $token = bin2hex(random_bytes(32));

        $expiresAt = (new DateTime())->modify("+{$this->token_expiry_minutes} minutes")->format('Y-m-d H:i:s');

        $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE email = :email AND used = 0 AND expires_at > NOW()");
        $stmt->execute(['email' => $email]);

        $stmt = $this->db->prepare(
            "INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, NOW())"
        );
        $stmt->execute([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);

        if (!$this->mailer->sendPasswordResetEmail($email, $token)) {
            error_log("Failed to send password reset email to $email.");
            return ['success' => true, 'message' => 'If your email is registered, you will receive a password reset link. (Email sending failed internally).'];
        }

        return ['success' => true, 'message' => 'If your email is registered, you will receive a password reset link.'];
    }

    public function validateToken(string $token): array {
        $stmt = $this->db->prepare(
            "SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW() AND used = 0"
        );
        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch();

        if (!$result) {
            return ['success' => false, 'message' => 'Invalid or expired password reset token.'];
        }

        return ['success' => true, 'message' => 'Token is valid.', 'email' => $result['email']];
    }

    public function resetPassword(string $token, string $newPassword, string $confirmPassword): array {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }

        $tokenValidation = $this->validateToken($token);
        if (!$tokenValidation['success']) {
            return $tokenValidation;
        }
        $email = $tokenValidation['email'];

        try {
            $this->db->beginTransaction();

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($hashedPassword === false) {
                throw new Exception("Password hashing failed.");
            }

            $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE email = :email");
            $stmt->execute([
                'password' => $hashedPassword,
                'email' => $email
            ]);
            if ($stmt->rowCount() === 0) {
                 throw new Exception("Failed to update user password. User not found or email changed.");
            }

            $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
            $stmt->execute(['token' => $token]);

            $this->db->commit();
            return ['success' => true, 'message' => 'Your password has been reset successfully. You can now log in with your new password.'];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Password reset failed: " . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred while resetting your password. Please try again.'];
        }
    }
}
<?php

require_once __DIR__ . '/../classes/PasswordResetService.php';

class PasswordResetHandler {
    private PasswordResetService $service;

    public function __construct() {
        $this->service = new PasswordResetService();
    }

    public function handleRequestReset(): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            return;
        }

        $email = $_POST['email'] ?? '';
        $response = $this->service->requestPasswordReset($email);
        echo json_encode($response);
    }

    public function handleResetPassword(): void {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
            return;
        }

        $token = $_POST['token'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        $response = $this->service->resetPassword($token, $newPassword, $confirmPassword);
        echo json_encode($response);
    }
}
<?php
require_once __DIR__ . '/../handlers/PasswordResetHandler.php';

$handler = new PasswordResetHandler();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handler->handleRequestReset();
    exit();
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
    <form id="resetRequestForm" method="POST">
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        <button type="submit">Send Reset Link</button>
    </form>
    <div id="responseMessage"></div>

    <script>
        document.getElementById('resetRequestForm').addEventListener('submit', async function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const responseDiv = document.getElementById('responseMessage');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                responseDiv.textContent = result.message;
                responseDiv.style.color = result.success ? 'green' : 'red';
            } catch (error) {
                responseDiv.textContent = 'An unexpected error occurred. Please try again.';
                responseDiv.style.color = 'red';
                console.error('Error:', error);
            }
        });
    </script>
</body>
</html>
<?php
require_once __DIR__ . '/../handlers/PasswordResetHandler.php';
require_once __DIR__ . '/../classes/PasswordResetService.php';

$handler = new PasswordResetHandler();
$token = $_GET['token'] ?? '';
$isTokenValid = false;
$validationMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handler->handleResetPassword();
    exit();
} else {
    if (!empty($token)) {
        $service = new PasswordResetService();
        $tokenValidationResult = $service->validateToken($token);

        if ($tokenValidationResult['success']) {
            $isTokenValid = true;
        } else {
            $validationMessage = $tokenValidationResult['message'];
        }
    } else {
        $validationMessage = 'No password reset token provided.';
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
    <?php if ($isTokenValid): ?>
        <form id="setPasswordForm" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <label for="new_password">New Password:</label><br>
            <input type="password" id="new_password" name="new_password" required><br><br>
            <label for="confirm_password">Confirm New Password:</label><br>
            <input type="password" id="confirm_password" name="confirm_password" required><br><br>
            <button type="submit">Reset Password</button>
        </form>
    <?php else: ?>
        <p><?php echo htmlspecialchars($validationMessage); ?></p>
    <?php endif; ?>
    <div id="responseMessage"></div>

    <script>
        document.getElementById('setPasswordForm')?.addEventListener('submit', async function(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);
            const responseDiv = document.getElementById('responseMessage');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                responseDiv.textContent = result.message;
                responseDiv.style.color = result.success ? 'green' : 'red';
                if (result.success) {
                    form.reset();
                    form.querySelector('button[type="submit"]').disabled = true;
                }
            } catch (error) {
                responseDiv.textContent = 'An unexpected error occurred. Please try again.';
                responseDiv.style.color = 'red';
                console.error('Error:', error);
            }
        });
    </script>
</body>
</html>
?>