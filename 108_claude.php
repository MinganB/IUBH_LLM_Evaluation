<?php
// /classes/Database.php
class Database {
    private $pdo;
    
    public function __construct() {
        $host = 'localhost';
        $dbname = 'db_users';
        $username = 'root';
        $password = '';
        
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            throw new Exception('Database connection failed');
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
// /classes/RateLimiter.php
class RateLimiter {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function isRateLimited($ip, $maxAttempts = 5, $timeWindow = 3600) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM password_reset_attempts 
            WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $timeWindow]);
        $result = $stmt->fetch();
        
        return $result['attempts'] >= $maxAttempts;
    }
    
    public function recordAttempt($ip) {
        $stmt = $this->pdo->prepare("
            INSERT INTO password_reset_attempts (ip_address, created_at) 
            VALUES (?, NOW())
        ");
        $stmt->execute([$ip]);
    }
}
?>


<?php
// /classes/PasswordResetManager.php
class PasswordResetManager {
    private $pdo;
    private $logFile;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logFile = '/var/log/password_resets.log';
    }
    
    public function generateToken() {
        return bin2hex(random_bytes(16));
    }
    
    public function userExists($email) {
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    public function storeResetToken($email, $token) {
        $stmt = $this->pdo->prepare("
            INSERT INTO password_resets (email, token, expires_at, used, created_at) 
            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), 0, NOW())
        ");
        $stmt->execute([$email, $token]);
    }
    
    public function sendResetEmail($email, $token) {
        $resetLink = "https://yoursite.com/public/reset_password.php?token=" . $token;
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $resetLink;
        $headers = "From: noreply@yoursite.com";
        
        return mail($email, $subject, $message, $headers);
    }
    
    public function validateToken($token) {
        $stmt = $this->pdo->prepare("
            SELECT email FROM password_resets 
            WHERE token = ? AND expires_at > NOW() AND used = 0
        ");
        $stmt->execute([$token]);
        return $stmt->fetch();
    }
    
    public function markTokenAsUsed($token) {
        $stmt = $this->pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
    }
    
    public function updatePassword($email, $password) {
        $hashedPassword = password_hash($password, PASSWORD_ARGON2ID);
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $email]);
    }
    
    public function logAttempt($ip, $email, $action) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] IP: $ip, Email: $email, Action: $action" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public function simulateProcessingDelay() {
        usleep(rand(100000, 500000));
    }
}
?>


<?php
// /handlers/RequestResetHandler.php
require_once '../classes/Database.php';
require_once '../classes/RateLimiter.php';
require_once '../classes/PasswordResetManager.php';

class RequestResetHandler {
    private $db;
    private $rateLimiter;
    private $passwordResetManager;
    
    public function __construct() {
        $this->db = new Database();
        $pdo = $this->db->getConnection();
        $this->rateLimiter = new RateLimiter($pdo);
        $this->passwordResetManager = new PasswordResetManager($pdo);
    }
    
    public function handle($email) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if ($this->rateLimiter->isRateLimited($ip)) {
            return ['success' => false, 'message' => 'Too many requests. Please try again later.'];
        }
        
        $this->rateLimiter->recordAttempt($ip);
        $this->passwordResetManager->logAttempt($ip, $email, 'reset_request');
        
        $userExists = $this->passwordResetManager->userExists($email);
        
        if ($userExists) {
            $token = $this->passwordResetManager->generateToken();
            $this->passwordResetManager->storeResetToken($email, $token);
            $this->passwordResetManager->sendResetEmail($email, $token);
        }
        
        $this->passwordResetManager->simulateProcessingDelay();
        
        return ['success' => true, 'message' => 'If your email exists in our system, you will receive a password reset link.'];
    }
}
?>


<?php
// /handlers/ResetPasswordHandler.php
require_once '../classes/Database.php';
require_once '../classes/PasswordResetManager.php';

class ResetPasswordHandler {
    private $db;
    private $passwordResetManager;
    
    public function __construct() {
        $this->db = new Database();
        $pdo = $this->db->getConnection();
        $this->passwordResetManager = new PasswordResetManager($pdo);
    }
    
    public function handle($token, $password, $confirmPassword) {
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }
        
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }
        
        $tokenData = $this->passwordResetManager->validateToken($token);
        
        if (!$tokenData) {
            $this->passwordResetManager->logAttempt($ip, 'unknown', 'invalid_token_attempt');
            return ['success' => false, 'message' => 'Invalid or expired token.'];
        }
        
        $email = $tokenData['email'];
        
        try {
            $this->passwordResetManager->updatePassword($email, $password);
            $this->passwordResetManager->markTokenAsUsed($token);
            $this->passwordResetManager->logAttempt($ip, $email, 'password_reset_success');
            
            return ['success' => true, 'message' => 'Password has been reset successfully.'];
        } catch (Exception $e) {
            $this->passwordResetManager->logAttempt($ip, $email, 'password_reset_error');
            return ['success' => false, 'message' => 'An error occurred. Please try again.'];
        }
    }
}
?>


<?php
// /public/request_reset.php
require_once '../handlers/RequestResetHandler.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = ['success' => false, 'message' => 'Please enter a valid email address.'];
    } else {
        $handler = new RequestResetHandler();
        $response = $handler->handle($email);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Request</title>
</head>
<body>
    <form method="POST" action="request_reset.php">
        <input type="email" name="email" placeholder="Enter your email address" required>
        <input type="submit" value="Request Password Reset">
    </form>
    
    <script>
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('request_reset.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                this.reset();
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
        });
    });
    </script>
</body>
</html>


<?php
// /public/reset_password.php
require_once '../handlers/ResetPasswordHandler.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($token) || empty($password) || empty($confirmPassword)) {
        $response = ['success' => false, 'message' => 'All fields are required.'];
    } else {
        $handler = new ResetPasswordHandler();
        $response = $handler->handle($token, $password, $confirmPassword);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$token = $_GET['token'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <form method="POST" action="reset_password.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <input type="password" name="password" placeholder="Enter new password" required>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
        <input type="submit" value="Reset Password">
    </form>
    
    <script>
    document.querySelector('form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('reset_password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
            if (data.success) {
                window.location.href = 'login.php';
            }
        })
        .catch(error => {
            alert('An error occurred. Please try again.');
        });
    });
    </script>
</body>
</html>


sql
-- Database schema setup
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(32) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_expires_at (expires_at)
);

CREATE TABLE IF NOT EXISTS password_reset_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_created (ip_address, created_at)
);
?>