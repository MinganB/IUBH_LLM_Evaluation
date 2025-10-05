<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'your_username';
    private $password = 'your_password';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            throw new Exception("Database connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
class RateLimiter {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->createRateLimitTable();
    }
    
    private function createRateLimitTable() {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            action VARCHAR(50) NOT NULL,
            attempts INT DEFAULT 1,
            last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_action (ip_address, action)
        )";
        $this->db->getConnection()->exec($sql);
    }
    
    public function isRateLimited($ipAddress, $action, $maxAttempts = 5, $timeWindow = 300) {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT attempts, last_attempt FROM rate_limits 
             WHERE ip_address = ? AND action = ? 
             AND last_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$ipAddress, $action, $timeWindow]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            $this->updateRateLimit($ipAddress, $action, 1);
            return false;
        }
        
        if ($result['attempts'] >= $maxAttempts) {
            return true;
        }
        
        $this->updateRateLimit($ipAddress, $action, $result['attempts'] + 1);
        return false;
    }
    
    private function updateRateLimit($ipAddress, $action, $attempts) {
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO rate_limits (ip_address, action, attempts, last_attempt) 
             VALUES (?, ?, ?, NOW()) 
             ON DUPLICATE KEY UPDATE attempts = ?, last_attempt = NOW()"
        );
        $stmt->execute([$ipAddress, $action, $attempts, $attempts]);
    }
}
?>


<?php
class Logger {
    private $logFile;
    
    public function __construct($logFile = '/var/log/password_reset.log') {
        $this->logFile = $logFile;
    }
    
    public function log($message, $ipAddress = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $ipAddress ?: $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry = "[{$timestamp}] IP: {$ip} - {$message}" . PHP_EOL;
        
        error_log($logEntry, 3, $this->logFile);
    }
}
?>


<?php
class PasswordResetService {
    private $db;
    private $logger;
    private $rateLimiter;
    
    public function __construct($database, $logger, $rateLimiter) {
        $this->db = $database;
        $this->logger = $logger;
        $this->rateLimiter = $rateLimiter;
        $this->createPasswordResetTable();
    }
    
    private function createPasswordResetTable() {
        $sql = "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_email (email)
        )";
        $this->db->getConnection()->exec($sql);
    }
    
    public function requestPasswordReset($email, $ipAddress) {
        $startTime = microtime(true);
        
        if ($this->rateLimiter->isRateLimited($ipAddress, 'password_reset')) {
            $this->logger->log("Rate limited password reset attempt for email: {$email}", $ipAddress);
            return [
                'success' => false,
                'message' => 'Too many requests. Please try again later.'
            ];
        }
        
        $this->logger->log("Password reset requested for email: {$email}", $ipAddress);
        
        $userExists = $this->checkUserExists($email);
        $token = null;
        
        if ($userExists) {
            $this->cleanupExpiredTokens($email);
            $token = $this->generateSecureToken();
            $this->storeResetToken($email, $token);
            $this->sendResetEmail($email, $token);
        }
        
        $this->maintainConsistentTiming($startTime);
        
        return [
            'success' => true,
            'message' => 'If the email address exists in our system, you will receive password reset instructions.'
        ];
    }
    
    public function resetPassword($token, $newPassword, $ipAddress) {
        $this->logger->log("Password reset attempt with token", $ipAddress);
        
        if (strlen($newPassword) < 8) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters long.'
            ];
        }
        
        $resetData = $this->validateResetToken($token);
        
        if (!$resetData) {
            return [
                'success' => false,
                'message' => 'Invalid or expired reset token.'
            ];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        
        if ($this->updateUserPassword($resetData['email'], $hashedPassword)) {
            $this->markTokenAsUsed($token);
            $this->logger->log("Password successfully reset for email: {$resetData['email']}", $ipAddress);
            
            return [
                'success' => true,
                'message' => 'Password has been successfully reset.'
            ];
        }
        
        return [
            'success' => false,
            'message' => 'An error occurred while resetting your password. Please try again.'
        ];
    }
    
    private function checkUserExists($email) {
        $stmt = $this->db->getConnection()->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    private function storeResetToken($email, $token) {
        $expiresAt = date('Y-m-d H:i:s', time() + 1800);
        $stmt = $this->db->getConnection()->prepare(
            "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
        );
        $stmt->execute([$email, $token, $expiresAt]);
    }
    
    private function validateResetToken($token) {
        $stmt = $this->db->getConnection()->prepare(
            "SELECT email FROM password_resets 
             WHERE token = ? AND expires_at > NOW() AND used = 0"
        );
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateUserPassword($email, $hashedPassword) {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE users SET password = ? WHERE email = ?"
        );
        return $stmt->execute([$hashedPassword, $email]);
    }
    
    private function markTokenAsUsed($token) {
        $stmt = $this->db->getConnection()->prepare(
            "UPDATE password_resets SET used = 1 WHERE token = ?"
        );
        $stmt->execute([$token]);
    }
    
    private function cleanupExpiredTokens($email) {
        $stmt = $this->db->getConnection()->prepare(
            "DELETE FROM password_resets WHERE email = ? AND (expires_at < NOW() OR used = 1)"
        );
        $stmt->execute([$email]);
    }
    
    private function maintainConsistentTiming($startTime, $targetTime = 0.5) {
        $elapsed = microtime(true) - $startTime;
        if ($elapsed < $targetTime) {
            usleep(($targetTime - $elapsed) * 1000000);
        }
    }
    
    private function sendResetEmail($email, $token) {
        $resetUrl = "https://yoursite.com/public/reset_password.php?token=" . $token;
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $resetUrl;
        $headers = "From: noreply@yoursite.com";
        
        mail($email, $subject, $message, $headers);
    }
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/RateLimiter.php';
require_once '../classes/Logger.php';
require_once '../classes/PasswordResetService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database();
    $logger = new Logger();
    $rateLimiter = new RateLimiter($database);
    $passwordResetService = new PasswordResetService($database, $logger, $rateLimiter);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Valid email address required']);
        exit;
    }
    
    $result = $passwordResetService->requestPasswordReset($email, $ipAddress);
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/RateLimiter.php';
require_once '../classes/Logger.php';
require_once '../classes/PasswordResetService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $database = new Database();
    $logger = new Logger();
    $rateLimiter = new RateLimiter($database);
    $passwordResetService = new PasswordResetService($database, $logger, $rateLimiter);
    
    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    $newPassword = $input['password'] ?? '';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (empty($token) || empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Token and password are required']);
        exit;
    }
    
    $result = $passwordResetService->resetPassword($token, $newPassword, $ipAddress);
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?>


<?php
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h1>Reset Password</h1>
    <?php if ($token): ?>
        <form id="resetForm">
            <input type="hidden" id="token" value="<?php echo htmlspecialchars($token); ?>">
            <div>
                <label>New Password:</label>
                <input type="password" id="password" required minlength="8">
            </div>
            <div>
                <label>Confirm Password:</label>
                <input type="password" id="confirmPassword" required minlength="8">
            </div>
            <button type="submit">Reset Password</button>
        </form>
        <div id="message"></div>
    <?php else: ?>
        <p>Invalid reset link.</p>
    <?php endif; ?>

    <script>
    document.getElementById('resetForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirmPassword').value;
        const token = document.getElementById('token').value;
        
        if (password !== confirmPassword) {
            document.getElementById('message').innerHTML = 'Passwords do not match';
            return;
        }
        
        fetch('../handlers/reset_password_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                token: token,
                password: password
            })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('message').innerHTML = data.message;
            if (data.success) {
                document.getElementById('resetForm').style.display = 'none';
            }
        })
        .catch(error => {
            document.getElementById('message').innerHTML = 'An error occurred. Please try again.';
        });
    });
    </script>
</body>
</html>


<?php
?>
<!DOCTYPE html>
<html>
<head>
    <title>Request Password Reset</title>
</head>
<body>
    <h1>Request Password Reset</h1>
    <form id="requestForm">
        <div>
            <label>Email Address:</label>
            <input type="email" id="email" required>
        </div>
        <button type="submit">Send Reset Link</button>
    </form>
    <div id="message"></div>

    <script>
    document.getElementById('requestForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value;
        
        fetch('../handlers/request_password_reset_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                email: email
            })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('message').innerHTML = data.message;
            if (data.success) {
                document.getElementById('requestForm').reset();
            }
        })
        .catch(error => {
            document.getElementById('message').innerHTML = 'An error occurred. Please try again.';
        });
?>