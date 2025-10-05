<?php
// /classes/Database.php
class Database {
    private $host = 'localhost';
    private $db_name = 'db_users';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            return null;
        }
        return $this->conn;
    }
}
?>


<?php
// /classes/PasswordReset.php
class PasswordReset {
    private $conn;
    private $users_table = "users";
    private $resets_table = "password_resets";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function requestReset($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return array('success' => false, 'message' => 'Invalid email format');
        }

        $query = "SELECT id FROM " . $this->users_table . " WHERE email = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            return array('success' => false, 'message' => 'Email not found');
        }

        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $created_at = date('Y-m-d H:i:s');

        $query = "INSERT INTO " . $this->resets_table . " (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $email);
        $stmt->bindParam(2, $token);
        $stmt->bindParam(3, $expires_at);
        $stmt->bindParam(4, $created_at);

        if ($stmt->execute()) {
            $this->sendResetEmail($email, $token);
            return array('success' => true, 'message' => 'Password reset email sent');
        }

        return array('success' => false, 'message' => 'Failed to create reset request');
    }

    public function resetPassword($token, $new_password) {
        if (empty($token) || empty($new_password)) {
            return array('success' => false, 'message' => 'Token and password are required');
        }

        if (strlen($new_password) < 8) {
            return array('success' => false, 'message' => 'Password must be at least 8 characters long');
        }

        $query = "SELECT email FROM " . $this->resets_table . " WHERE token = ? AND expires_at > NOW() AND used = 0 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $token);
        $stmt->execute();

        if ($stmt->rowCount() == 0) {
            return array('success' => false, 'message' => 'Invalid or expired token');
        }

        $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $email = $reset_data['email'];

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $this->conn->beginTransaction();

        try {
            $query = "UPDATE " . $this->users_table . " SET password = ? WHERE email = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $hashed_password);
            $stmt->bindParam(2, $email);
            $stmt->execute();

            $query = "UPDATE " . $this->resets_table . " SET used = 1 WHERE token = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(1, $token);
            $stmt->execute();

            $this->conn->commit();
            return array('success' => true, 'message' => 'Password reset successfully');
        } catch (Exception $e) {
            $this->conn->rollback();
            return array('success' => false, 'message' => 'Failed to reset password');
        }
    }

    public function validateToken($token) {
        if (empty($token)) {
            return array('success' => false, 'message' => 'Token is required');
        }

        $query = "SELECT email FROM " . $this->resets_table . " WHERE token = ? AND expires_at > NOW() AND used = 0 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $token);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            return array('success' => true, 'message' => 'Token is valid');
        }

        return array('success' => false, 'message' => 'Invalid or expired token');
    }

    private function sendResetEmail($email, $token) {
        $reset_url = "http://localhost/public/reset-password.php?token=" . $token;
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $reset_url;
        $headers = "From: no-reply@yoursite.com";
        
        mail($email, $subject, $message, $headers);
    }

    public function cleanupExpiredTokens() {
        $query = "DELETE FROM " . $this->resets_table . " WHERE expires_at < NOW() OR used = 1";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute();
    }
}
?>


<?php
// /handlers/request-reset.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit;
}

require_once '../classes/Database.php';
require_once '../classes/PasswordReset.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo json_encode(array('success' => false, 'message' => 'Database connection failed'));
    exit;
}

$password_reset = new PasswordReset($db);

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['email'])) {
    echo json_encode(array('success' => false, 'message' => 'Email is required'));
    exit;
}

$email = trim($input['email']);
$result = $password_reset->requestReset($email);

echo json_encode($result);
?>


<?php
// /handlers/reset-password.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit;
}

require_once '../classes/Database.php';
require_once '../classes/PasswordReset.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo json_encode(array('success' => false, 'message' => 'Database connection failed'));
    exit;
}

$password_reset = new PasswordReset($db);

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['token']) || !isset($input['password'])) {
    echo json_encode(array('success' => false, 'message' => 'Token and password are required'));
    exit;
}

$token = trim($input['token']);
$new_password = $input['password'];

$result = $password_reset->resetPassword($token, $new_password);

echo json_encode($result);
?>


<?php
// /handlers/validate-token.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit;
}

require_once '../classes/Database.php';
require_once '../classes/PasswordReset.php';

$database = new Database();
$db = $database->getConnection();

if ($db === null) {
    echo json_encode(array('success' => false, 'message' => 'Database connection failed'));
    exit;
}

$password_reset = new PasswordReset($db);

if (!isset($_GET['token'])) {
    echo json_encode(array('success' => false, 'message' => 'Token is required'));
    exit;
}

$token = trim($_GET['token']);
$result = $password_reset->validateToken($token);

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
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <button type="submit">Send Reset Link</button>
    </form>
    <div id="message"></div>

    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const messageDiv = document.getElementById('message');
            
            fetch('/handlers/request-reset.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({email: email})
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.innerHTML = data.message;
                if (data.success) {
                    document.getElementById('forgotPasswordForm').reset();
                }
            })
            .catch(error => {
                messageDiv.innerHTML = 'An error occurred. Please try again.';
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
    <form id="resetPasswordForm">
        <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
        <div>
            <label for="password">New Password:</label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div>
            <label for="confirmPassword">Confirm Password:</label>
            <input type="password" id="confirmPassword" name="confirmPassword" required minlength="8">
        </div>
        <button type="submit">Reset Password</button>
    </form>
    <div id="message"></div>

    <script>
        window.onload = function() {
            const token = document.getElementById('token').value;
            if (!token) {
                document.getElementById('message').innerHTML = 'Invalid reset link.';
                return;
            }

            fetch('/handlers/validate-token.php?token=' + encodeURIComponent(token))
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    document.getElementById('message').innerHTML = data.message;
                    document.getElementById('resetPasswordForm').style.display = 'none';
                }
            });
        };

        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const token = document.getElementById('token').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const messageDiv = document.getElementById('message');
            
            if (password !== confirmPassword) {
                messageDiv.innerHTML = 'Passwords do not match.';
                return;
            }
            
            fetch('/handlers/reset-password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({token: token, password: password})
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.innerHTML = data.message;
                if (data.success) {
                    document.getElementById('resetPasswordForm').reset();
                    setTimeout(function() {
                        window.location.href = '/public/login.php';
                    }, 2000);
                }
            })
            .catch(error => {
                messageDiv.innerHTML = 'An error occurred. Please try again.';
            });
        });
    </script>
</body>
</html>
?>