<?php
// reset_request.php
session_start();

class PasswordResetRequest {
    private $db;
    private $logFile;
    private $rateLimitFile;
    private $maxAttempts = 5;
    private $timeWindow = 300;
    
    public function __construct() {
        $this->db = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $this->logFile = '/var/log/password_reset.log';
        $this->rateLimitFile = '/tmp/reset_rate_limit.json';
    }
    
    private function getRateLimitData() {
        if (!file_exists($this->rateLimitFile)) {
            return [];
        }
        return json_decode(file_get_contents($this->rateLimitFile), true) ?: [];
    }
    
    private function saveRateLimitData($data) {
        file_put_contents($this->rateLimitFile, json_encode($data), LOCK_EX);
    }
    
    private function isRateLimited($ip) {
        $data = $this->getRateLimitData();
        $now = time();
        
        if (!isset($data[$ip])) {
            $data[$ip] = [];
        }
        
        $data[$ip] = array_filter($data[$ip], function($timestamp) use ($now) {
            return ($now - $timestamp) < $this->timeWindow;
        });
        
        if (count($data[$ip]) >= $this->maxAttempts) {
            return true;
        }
        
        $data[$ip][] = $now;
        $this->saveRateLimitData($data);
        return false;
    }
    
    private function logAttempt($email, $ip, $success) {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $logEntry = "[{$timestamp}] Password reset request - Email: {$email}, IP: {$ip}, Status: {$status}\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function generateToken() {
        return bin2hex(random_bytes(32));
    }
    
    private function userExists($email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() !== false;
    }
    
    private function storeResetToken($email, $token) {
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $stmt = $this->db->prepare("
            INSERT INTO password_reset_tokens (email, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE 
            token = VALUES(token), 
            expires_at = VALUES(expires_at), 
            created_at = VALUES(created_at),
            used = 0
        ");
        $stmt->execute([$email, hash('sha256', $token), $expires]);
    }
    
    private function sendResetEmail($email, $token) {
        $resetLink = "https://yoursite.com/reset_password.php?token=" . urlencode($token);
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $resetLink . "\n\nThis link will expire in 30 minutes.";
        $headers = "From: noreply@yoursite.com\r\n" .
                  "Reply-To: noreply@yoursite.com\r\n" .
                  "X-Mailer: PHP/" . phpversion();
        
        return mail($email, $subject, $message, $headers);
    }
    
    public function processRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showForm();
            return;
        }
        
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if ($this->isRateLimited($ip)) {
            echo "<p>Too many requests. Please try again later.</p>";
            return;
        }
        
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        
        if (!$email) {
            echo "<p>If your email address is in our system, you will receive a password reset link shortly.</p>";
            $this->logAttempt('invalid_email', $ip, false);
            return;
        }
        
        $startTime = microtime(true);
        
        $userExists = $this->userExists($email);
        
        if ($userExists) {
            $token = $this->generateToken();
            $this->storeResetToken($email, $token);
            $this->sendResetEmail($email, $token);
            $this->logAttempt($email, $ip, true);
        } else {
            $this->logAttempt($email, $ip, false);
        }
        
        $elapsedTime = microtime(true) - $startTime;
        $minTime = 0.5;
        if ($elapsedTime < $minTime) {
            usleep(($minTime - $elapsedTime) * 1000000);
        }
        
        echo "<p>If your email address is in our system, you will receive a password reset link shortly.</p>";
    }
    
    private function showForm() {
        echo '
        <form method="post" action="">
            <div>
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <button type="submit">Reset Password</button>
            </div>
        </form>';
    }
}

$resetRequest = new PasswordResetRequest();
$resetRequest->processRequest();
?>


<?php
// reset_password.php
session_start();

class PasswordReset {
    private $db;
    private $logFile;
    
    public function __construct() {
        $this->db = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $this->logFile = '/var/log/password_reset.log';
    }
    
    private function logAttempt($token, $ip, $success, $reason = '') {
        $timestamp = date('Y-m-d H:i:s');
        $status = $success ? 'SUCCESS' : 'FAILED';
        $logEntry = "[{$timestamp}] Password reset completion - Token: " . substr($token, 0, 8) . "..., IP: {$ip}, Status: {$status}";
        if ($reason) {
            $logEntry .= ", Reason: {$reason}";
        }
        $logEntry .= "\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function validateToken($token) {
        $hashedToken = hash('sha256', $token);
        $stmt = $this->db->prepare("
            SELECT email FROM password_reset_tokens 
            WHERE token = ? AND expires_at > NOW() AND used = 0
        ");
        $stmt->execute([$hashedToken]);
        return $stmt->fetch();
    }
    
    private function markTokenUsed($token) {
        $hashedToken = hash('sha256', $token);
        $stmt = $this->db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
        $stmt->execute([$hashedToken]);
    }
    
    private function updatePassword($email, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
        return $stmt->execute([$hashedPassword, $email]);
    }
    
    private function validatePassword($password) {
        if (strlen($password) < 8) {
            return false;
        }
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            return false;
        }
        return true;
    }
    
    public function processReset() {
        $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (!$token) {
            echo "<p>Invalid reset link.</p>";
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $tokenData = $this->validateToken($token);
            if ($tokenData) {
                $this->showForm($token);
            } else {
                echo "<p>Invalid or expired reset link.</p>";
                $this->logAttempt($token, $ip, false, 'Invalid token on form display');
            }
            return;
        }
        
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!$this->validatePassword($password)) {
            echo "<p>Password must be at least 8 characters long and contain uppercase, lowercase, numeric, and special characters.</p>";
            $this->showForm($token);
            return;
        }
        
        if ($password !== $confirmPassword) {
            echo "<p>Passwords do not match.</p>";
            $this->showForm($token);
            return;
        }
        
        $tokenData = $this->validateToken($token);
        
        if (!$tokenData) {
            echo "<p>Invalid or expired reset link.</p>";
            $this->logAttempt($token, $ip, false, 'Invalid token on reset');
            return;
        }
        
        if ($this->updatePassword($tokenData['email'], $password)) {
            $this->markTokenUsed($token);
            echo "<p>Your password has been successfully reset. You can now log in with your new password.</p>";
            $this->logAttempt($token, $ip, true);
        } else {
            echo "<p>An error occurred. Please try again.</p>";
            $this->logAttempt($token, $ip, false, 'Database update failed');
        }
    }
    
    private function showForm($token) {
        echo '
        <form method="post" action="">
            <input type="hidden" name="token" value="' . htmlspecialchars($token) . '">
            <div>
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div>
                <button type="submit">Reset Password</button>
            </div>
        </form>';
    }
}

$passwordReset = new PasswordReset();
$passwordReset->processReset();
?>


sql
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_email (email),
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);
?>