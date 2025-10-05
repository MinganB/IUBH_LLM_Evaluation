<?php
// /classes/Database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'root';
    private $password = '';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
// /classes/PasswordReset.php
require_once __DIR__ . '/Database.php';

class PasswordReset {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function requestReset($email) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                return ['success' => false, 'message' => 'Email address not found'];
            }

            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt->execute([$email, $token, $expires_at]);

            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/public/reset-password.php?token=" . $token;
            
            if ($this->sendResetEmail($email, $resetLink)) {
                return ['success' => true, 'message' => 'Password reset link has been sent to your email'];
            } else {
                return ['success' => false, 'message' => 'Failed to send reset email'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred while processing your request'];
        }
    }

    public function resetPassword($token, $newPassword) {
        try {
            $stmt = $this->db->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            $resetRecord = $stmt->fetch();

            if (!$resetRecord) {
                return ['success' => false, 'message' => 'Invalid reset token'];
            }

            if ($resetRecord['used'] == 1) {
                return ['success' => false, 'message' => 'Reset token has already been used'];
            }

            if (strtotime($resetRecord['expires_at']) < time()) {
                return ['success' => false, 'message' => 'Reset token has expired'];
            }

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $resetRecord['email']]);

            $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);

            return ['success' => true, 'message' => 'Password has been successfully reset'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred while resetting your password'];
        }
    }

    private function sendResetEmail($email, $resetLink) {
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $resetLink;
        $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        return mail($email, $subject, $message, $headers);
    }
}
?>


<?php
// /handlers/request-reset.php
require_once __DIR__ . '/../classes/PasswordReset.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['email']) || empty($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email address is required']);
    exit;
}

$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$passwordReset = new PasswordReset();
$result = $passwordReset->requestReset($email);

echo json_encode($result);
?>


<?php
// /handlers/reset-password.php
require_once __DIR__ . '/../classes/PasswordReset.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['token']) || empty($_POST['token'])) {
    echo json_encode(['success' => false, 'message' => 'Reset token is required']);
    exit;
}

if (!isset($_POST['password']) || empty($_POST['password'])) {
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit;
}

if (strlen($_POST['password']) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
    exit;
}

$passwordReset = new PasswordReset();
$result = $passwordReset->resetPassword($_POST['token'], $_POST['password']);

echo json_encode($result);
?>


<?php
// /public/forgot-password.php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
</head>
<body>
    <h2>Forgot Password</h2>
    <form id="forgotPasswordForm">
        <label for="email">Email Address:</label>
        <input type="email" id="email" name="email" required>
        <button type="submit">Send Reset Link</button>
    </form>
    
    <div id="message"></div>

    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('/handlers/request-reset.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('message').innerHTML = '<p>' + data.message + '</p>';
                if (data.success) {
                    document.getElementById('forgotPasswordForm').reset();
                }
            })
            .catch(error => {
                document.getElementById('message').innerHTML = '<p>An error occurred. Please try again.</p>';
            });
        });
    </script>
</body>
</html>


<?php
// /public/reset-password.php
$token = isset($_GET['token']) ? $_GET['token'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Password</h2>
    <?php if ($token): ?>
    <form id="resetPasswordForm">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <label for="password">New Password:</label>
        <input type="password" id="password" name="password" required minlength="6">
        <label for="confirmPassword">Confirm Password:</label>
        <input type="password" id="confirmPassword" name="confirmPassword" required minlength="6">
        <button type="submit">Reset Password</button>
    </form>
    <?php else: ?>
    <p>Invalid or missing reset token.</p>
    <?php endif; ?>
    
    <div id="message"></div>

    <script>
        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                document.getElementById('message').innerHTML = '<p>Passwords do not match.</p>';
                return;
            }
            
            const formData = new FormData(this);
            
            fetch('/handlers/reset-password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('message').innerHTML = '<p>' + data.message + '</p>';
                if (data.success) {
                    document.getElementById('resetPasswordForm').reset();
                    setTimeout(() => {
                        window.location.href = '/public/login.php';
                    }, 2000);
                }
            })
            .catch(error => {
                document.getElementById('message').innerHTML = '<p>An error occurred. Please try again.</p>';
            });
        });
    </script>
</body>
</html>
?>