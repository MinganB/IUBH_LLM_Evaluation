<?php

// config.php - Central configuration for the application

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user'); // Replace with your database username
define('DB_PASSWORD', 'your_db_password'); // Replace with your database password

define('PASSWORD_RESET_TOKEN_LIFESPAN_HOURS', 1); // Token valid for 1 hour

define('APP_URL', 'http://localhost/public'); // Base URL for the public facing pages

// Email configuration (for Mailer.php - basic simulation)
define('MAIL_FROM_ADDRESS', 'noreply@yourdomain.com');
define('MAIL_FROM_NAME', 'Your E-commerce Site');

// It is highly recommended to use environment variables for sensitive data in production.

?>

<?php

// classes/Database.php - Handles database connection

class Database
{
    private static $instance = null;
    private $pdo;

    private function __construct()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASSWORD, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection error.');
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->pdo;
    }
}

?>

<?php

// classes/UserManager.php - Manages user data in the database

require_once __DIR__ . '/Database.php';

class UserManager
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db->getConnection();
    }

    public function findUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT id, email, password FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function updateUserPassword(string $email, string $newPassword): bool
    {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE email = :email");
        return $stmt->execute([
            'password' => $hashedPassword,
            'email' => $email
        ]);
    }
}

?>

<?php

// classes/PasswordResetManager.php - Manages password reset tokens

require_once __DIR__ . '/Database.php';

class PasswordResetManager
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db->getConnection();
    }

    public function createToken(string $email): ?string
    {
        $token = bin2hex(random_bytes(32)); // 64-character hex string
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . PASSWORD_RESET_TOKEN_LIFESPAN_HOURS . ' hour'));

        try {
            // Invalidate any existing tokens for this user first
            $stmt = $this->db->prepare("UPDATE password_resets SET used = TRUE WHERE email = :email AND used = FALSE AND expires_at > NOW()");
            $stmt->execute(['email' => $email]);

            $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)");
            $stmt->execute([
                'email' => $email,
                'token' => $token,
                'expires_at' => $expiresAt
            ]);
            return $token;
        } catch (PDOException $e) {
            error_log('Error creating password reset token: ' . $e->getMessage());
            return null;
        }
    }

    public function getTokenDetails(string $token): ?array
    {
        $stmt = $this->db->prepare("SELECT id, email, expires_at, used FROM password_resets WHERE token = :token");
        $stmt->execute(['token' => $token]);
        $details = $stmt->fetch();
        return $details ?: null;
    }

    public function validateToken(string $token, string $email): bool
    {
        $details = $this->getTokenDetails($token);

        if (!$details) {
            return false;
        }

        if ($details['email'] !== $email) {
            return false;
        }

        if ($details['used']) {
            return false;
        }

        $now = new DateTime();
        $expiresAt = new DateTime($details['expires_at']);

        if ($now > $expiresAt) {
            return false;
        }

        return true;
    }

    public function markTokenAsUsed(string $token): bool
    {
        $stmt = $this->db->prepare("UPDATE password_resets SET used = TRUE WHERE token = :token");
        return $stmt->execute(['token' => $token]);
    }
}

?>

<?php

// classes/Mailer.php - Simulates sending emails

class Mailer
{
    public function sendPasswordResetEmail(string $recipientEmail, string $token): bool
    {
        $resetLink = APP_URL . '/set_new_password.php?token=' . urlencode($token);

        $subject = MAIL_FROM_NAME . ' - Password Reset Request';
        $message = "Dear User,\n\nYou have requested to reset your password. Please click on the following link to set a new password:\n\n";
        $message .= $resetLink;
        $message .= "\n\nThis link is valid for " . PASSWORD_RESET_TOKEN_LIFESPAN_HOURS . " hour(s). If you did not request this, please ignore this email.\n\n";
        $message .= "Sincerely,\n" . MAIL_FROM_NAME . " Team";

        $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">\r\n";
        $headers .= "Reply-To: " . MAIL_FROM_ADDRESS . "\r\n";
        $headers .= "Content-type: text/plain; charset=UTF-8\r\n";

        // In a real production environment, you would use a robust mailing library
        // like PHPMailer or a transactional email service (SendGrid, Mailgun, AWS SES).
        // For this exercise, we simulate email sending.
        
        // This 'mail()' function requires a configured mail server (e.g., Sendmail, Postfix)
        // on the server for it to actually send emails.
        // For local development, it might simply write to a log or not send at all.
        $mailSent = mail($recipientEmail, $subject, $message, $headers);

        if (!$mailSent) {
            error_log("Failed to send password reset email to {$recipientEmail}");
        }

        return $mailSent;
    }
}

?>

<?php

// handlers/password_reset_request.php - Handles requests to initiate a password reset

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/UserManager.php';
require_once __DIR__ . '/../classes/PasswordResetManager.php';
require_once __DIR__ . '/../classes/Mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit;
}

try {
    $db = Database::getInstance();
    $userManager = new UserManager($db);
    $resetManager = new PasswordResetManager($db);
    $mailer = new Mailer();

    $user = $userManager->findUserByEmail($email);

    if (!$user) {
        // Return a generic success message to prevent email enumeration
        echo json_encode(['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.']);
        exit;
    }

    $token = $resetManager->createToken($user['email']);

    if (!$token) {
        echo json_encode(['success' => false, 'message' => 'Failed to generate password reset token. Please try again.']);
        exit;
    }

    if ($mailer->sendPasswordResetEmail($user['email'], $token)) {
        echo json_encode(['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send password reset email. Please try again later.']);
    }

} catch (Exception $e) {
    error_log('Password reset request error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}

?>

<?php

// handlers/password_reset_set.php - Handles setting a new password using a token

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/UserManager.php';
require_once __DIR__ . '/../classes/PasswordResetManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$token = filter_input(INPUT_POST, 'token', FILTER_UNSAFE_RAW);
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';

if (empty($token) || empty($password) || empty($passwordConfirm)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if ($password !== $passwordConfirm) {
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

if (strlen($password) < 8) { // Basic password strength check
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit;
}

try {
    $db = Database::getInstance();
    $resetManager = new PasswordResetManager($db);
    $userManager = new UserManager($db);

    $tokenDetails = $resetManager->getTokenDetails($token);

    if (!$tokenDetails) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
        exit;
    }

    $email = $tokenDetails['email'];

    if (!$resetManager->validateToken($token, $email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token. Please request a new password reset.']);
        exit;
    }

    // Update the user's password
    if ($userManager->updateUserPassword($email, $password)) {
        // Mark the token as used
        $resetManager->markTokenAsUsed($token);
        echo json_encode(['success' => true, 'message' => 'Your password has been successfully reset. You can now log in with your new password.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
    }

} catch (Exception $e) {
    error_log('Password reset set error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An unexpected error occurred. Please try again.']);
}

?>

<?php

// public/request_password_reset.php - User-facing form to request a password reset

require_once __DIR__ . '/../config.php'; // Ensure APP_URL is defined

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
    <form id="passwordResetRequestForm">
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        <button type="submit">Send Reset Link</button>
    </form>
    <p id="responseMessage"></p>

    <script>
        document.getElementById('passwordResetRequestForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const responseMessage = document.getElementById('responseMessage');

            responseMessage.textContent = 'Sending request...';
            responseMessage.style.color = 'black';

            fetch('/handlers/password_reset_request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                responseMessage.textContent = data.message;
                responseMessage.style.color = data.success ? 'green' : 'red';
                if (data.success) {
                    form.reset();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                responseMessage.textContent = 'An error occurred. Please try again.';
                responseMessage.style.color = 'red';
            });
        });
    </script>
</body>
</html>

<?php

// public/set_new_password.php - User-facing form to set a new password

require_once __DIR__ . '/../config.php'; // Ensure APP_URL is defined

$token = $_GET['token'] ?? '';

// Basic validation for token presence
if (empty($token)) {
    echo "<p>No password reset token provided. Please use the link from your email.</p>";
    echo "<p><a href=\"" . APP_URL . "/request_password_reset.php\">Request a new password reset.</a></p>";
    exit;
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
    <form id="setNewPasswordForm">
        <input type="hidden" id="token" name="token" value="<?php echo htmlspecialchars($token); ?>">

        <label for="password">New Password:</label><br>
        <input type="password" id="password" name="password" required minlength="8"><br><br>

        <label for="password_confirm">Confirm New Password:</label><br>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="8"><br><br>

        <button type="submit">Set Password</button>
    </form>
    <p id="responseMessage"></p>

    <script>
        document.getElementById('setNewPasswordForm').addEventListener('submit', function(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const responseMessage = document.getElementById('responseMessage');

            responseMessage.textContent = 'Setting password...';
            responseMessage.style.color = 'black';

            fetch('/handlers/password_reset_set.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                responseMessage.textContent = data.message;
                responseMessage.style.color = data.success ? 'green' : 'red';
                if (data.success) {
                    form.reset();
                    // Optionally redirect to login page after successful reset
                    // setTimeout(() => { window.location.href = '/login.php'; }, 3000);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                responseMessage.textContent = 'An error occurred. Please try again.';
                responseMessage.style.color = 'red';
            });
        });
    </script>
</body>
</html>
?>