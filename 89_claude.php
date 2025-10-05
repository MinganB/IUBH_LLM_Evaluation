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
            throw new Exception("Database connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
// /classes/PasswordReset.php
require_once 'Database.php';

class PasswordReset {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function requestReset($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return json_encode(['success' => false, 'message' => 'Invalid email address']);
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if (!$stmt->fetch()) {
            return json_encode(['success' => false, 'message' => 'Email address not found']);
        }

        $this->db->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")->execute([$email]);

        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', time() + 3600);
        $created_at = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, ?)");
        
        if ($stmt->execute([$email, $token, $expires_at, $created_at])) {
            $this->sendResetEmail($email, $token);
            return json_encode(['success' => true, 'message' => 'Password reset email sent']);
        }

        return json_encode(['success' => false, 'message' => 'Failed to generate reset token']);
    }

    public function resetPassword($token, $newPassword) {
        if (strlen($newPassword) < 8) {
            return json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long']);
        }

        $stmt = $this->db->prepare("SELECT email FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            return json_encode(['success' => false, 'message' => 'Invalid or expired reset token']);
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $reset['email']]);

            $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);

            $this->db->commit();
            return json_encode(['success' => true, 'message' => 'Password updated successfully']);
        } catch (Exception $e) {
            $this->db->rollBack();
            return json_encode(['success' => false, 'message' => 'Failed to update password']);
        }
    }

    public function validateToken($token) {
        $stmt = $this->db->prepare("SELECT id FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
        $stmt->execute([$token]);
        
        if ($stmt->fetch()) {
            return json_encode(['success' => true, 'message' => 'Token is valid']);
        }
        
        return json_encode(['success' => false, 'message' => 'Invalid or expired token']);
    }

    private function sendResetEmail($email, $token) {
        $resetLink = "https://yoursite.com/public/reset-password.php?token=" . $token;
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $resetLink . "\n\nThis link will expire in 1 hour.";
        $headers = "From: noreply@yoursite.com";
        
        mail($email, $subject, $message, $headers);
    }
}
?>


<?php
// /handlers/password_reset_request.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../classes/PasswordReset.php';

if (!isset($_POST['email']) || empty($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);

try {
    $passwordReset = new PasswordReset();
    echo $passwordReset->requestReset($email);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>


<?php
// /handlers/password_reset.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../classes/PasswordReset.php';

if (!isset($_POST['token']) || empty($_POST['token']) || !isset($_POST['password']) || empty($_POST['password'])) {
    echo json_encode(['success' => false, 'message' => 'Token and password are required']);
    exit;
}

$token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$password = $_POST['password'];

try {
    $passwordReset = new PasswordReset();
    echo $passwordReset->resetPassword($token, $password);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>


<?php
// /handlers/validate_reset_token.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../classes/PasswordReset.php';

if (!isset($_GET['token']) || empty($_GET['token'])) {
    echo json_encode(['success' => false, 'message' => 'Token is required']);
    exit;
}

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

try {
    $passwordReset = new PasswordReset();
    echo $passwordReset->validateToken($token);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
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
    <h2>Reset Your Password</h2>
    <form id="forgotPasswordForm">
        <input type="email" id="email" name="email" placeholder="Enter your email" required>
        <button type="submit">Send Reset Link</button>
    </form>
    <div id="message"></div>

    <script>
    document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('../handlers/password_reset_request.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('message').textContent = data.message;
        })
        .catch(error => {
            document.getElementById('message').textContent = 'An error occurred';
        });
    });
    </script>
</body>
</html>


<?php
// /public/reset-password.php
$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Set New Password</h2>
    <form id="resetPasswordForm">
        <input type="hidden" id="token" name="token" value="<?php echo $token; ?>">
        <input type="password" id="password" name="password" placeholder="Enter new password" required minlength="8">
        <input type="password" id="confirmPassword" placeholder="Confirm new password" required minlength="8">
        <button type="submit">Update Password</button>
    </form>
    <div id="message"></div>

    <script>
    window.onload = function() {
        const token = document.getElementById('token').value;
        if (!token) {
            document.getElementById('message').textContent = 'Invalid reset link';
            return;
        }

        fetch('../handlers/validate_reset_token.php?token=' + encodeURIComponent(token))
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('message').textContent = data.message;
                document.getElementById('resetPasswordForm').style.display = 'none';
            }
        });
    };

    document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (password !== confirmPassword) {
            document.getElementById('message').textContent = 'Passwords do not match';
            return;
        }
        
        const formData = new FormData(this);
        
        fetch('../handlers/password_reset.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('message').textContent = data.message;
            if (data.success) {
                document.getElementById('resetPasswordForm').style.display = 'none';
            }
        })
        .catch(error => {
            document.getElementById('message').textContent = 'An error occurred';
        });
    });
    </script>
</body>
</html>
?>