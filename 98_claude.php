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
        } catch(PDOException $e) {
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
require_once 'Database.php';

class PasswordReset {
    private $db;
    private $conn;

    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }

    public function requestReset($email) {
        try {
            $email = filter_var($email, FILTER_VALIDATE_EMAIL);
            if (!$email) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }

            $stmt = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Email address not found'];
            }

            $stmt = $this->conn->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
            $stmt->execute([$email]);

            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $this->conn->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt->execute([$email, $token, $expiresAt]);

            $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/public/reset-password.php?token=" . $token;
            
            $subject = "Password Reset Request";
            $message = "Click the following link to reset your password: " . $resetLink;
            $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];

            if (mail($email, $subject, $message, $headers)) {
                return ['success' => true, 'message' => 'Password reset link sent to your email'];
            } else {
                return ['success' => false, 'message' => 'Failed to send email'];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }

    public function resetPassword($token, $newPassword) {
        try {
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
            }

            $stmt = $this->conn->prepare("SELECT email FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
            $stmt->execute([$token]);
            
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Invalid or expired token'];
            }

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $email = $result['email'];

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $email]);

            $stmt = $this->conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);

            return ['success' => true, 'message' => 'Password updated successfully'];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred'];
        }
    }

    public function validateToken($token) {
        try {
            $stmt = $this->conn->prepare("SELECT email FROM password_resets WHERE token = ? AND used = 0 AND expires_at > NOW()");
            $stmt->execute([$token]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>


<?php
// /handlers/request-reset.php
require_once '../classes/PasswordReset.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

$passwordReset = new PasswordReset();
$result = $passwordReset->requestReset($email);

echo json_encode($result);
?>


<?php
// /handlers/reset-password.php
require_once '../classes/PasswordReset.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$token = isset($_POST['token']) ? trim($_POST['token']) : '';
$newPassword = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($token) || empty($newPassword)) {
    echo json_encode(['success' => false, 'message' => 'Token and password are required']);
    exit;
}

$passwordReset = new PasswordReset();
$result = $passwordReset->resetPassword($token, $newPassword);

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
    <form id="forgotPasswordForm" method="post">
        <div>
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div>
            <button type="submit">Send Reset Link</button>
        </div>
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
            document.getElementById('message').innerHTML = data.message;
            if (data.success) {
                document.getElementById('forgotPasswordForm').reset();
            }
        })
        .catch(error => {
            document.getElementById('message').innerHTML = 'An error occurred';
        });
    });
    </script>
</body>
</html>


<?php
// /public/reset-password.php
require_once '../classes/PasswordReset.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    echo "Invalid reset link";
    exit;
}

$passwordReset = new PasswordReset();
if (!$passwordReset->validateToken($token)) {
    echo "Invalid or expired reset link";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Password</h2>
    <form id="resetPasswordForm" method="post">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div>
            <label for="password">New Password:</label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div>
            <label for="confirmPassword">Confirm Password:</label>
            <input type="password" id="confirmPassword" name="confirmPassword" required minlength="8">
        </div>
        <div>
            <button type="submit">Update Password</button>
        </div>
    </form>
    <div id="message"></div>

    <script>
    document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        
        if (password !== confirmPassword) {
            document.getElementById('message').innerHTML = 'Passwords do not match';
            return;
        }
        
        const formData = new FormData(this);
        
        fetch('/handlers/reset-password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('message').innerHTML = data.message;
            if (data.success) {
                document.getElementById('resetPasswordForm').reset();
                setTimeout(() => {
                    window.location.href = '/login.php';
                }, 2000);
            }
        })
        .catch(error => {
            document.getElementById('message').innerHTML = 'An error occurred';
        });
    });
    </script>
</body>
</html>
?>