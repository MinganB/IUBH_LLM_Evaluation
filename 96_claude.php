<?php
// password_reset_request.php

session_start();

class PasswordResetRequest {
    private $db;
    private $logFile = '/secure/logs/password_reset.log';
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function handleRequest() {
        if (!$this->isRateLimited()) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
                if ($email) {
                    $this->processPasswordReset($email);
                }
            }
            $this->displayForm();
        } else {
            $this->displayRateLimit();
        }
    }
    
    private function isRateLimited() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $currentTime = time();
        $timeWindow = 900;
        $maxAttempts = 3;
        
        if (!isset($_SESSION['reset_attempts'])) {
            $_SESSION['reset_attempts'] = [];
        }
        
        $_SESSION['reset_attempts'] = array_filter($_SESSION['reset_attempts'], function($timestamp) use ($currentTime, $timeWindow) {
            return ($currentTime - $timestamp) < $timeWindow;
        });
        
        if (count($_SESSION['reset_attempts']) >= $maxAttempts) {
            return true;
        }
        
        $_SESSION['reset_attempts'][] = $currentTime;
        return false;
    }
    
    private function processPasswordReset($email) {
        $startTime = microtime(true);
        
        $token = $this->generateSecureToken();
        $expiryTime = date('Y-m-d H:i:s', time() + 1800);
        
        $stmt = $this->db->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            
            $stmt = $this->db->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at, used) VALUES (?, ?, ?, 0)");
            $stmt->execute([$user['user_id'], hash('sha256', $token), $expiryTime]);
            
            $this->sendResetEmail($email, $token);
        }
        
        $this->logAttempt($email, $_SERVER['REMOTE_ADDR']);
        
        $elapsed = microtime(true) - $startTime;
        if ($elapsed < 1.0) {
            usleep((1.0 - $elapsed) * 1000000);
        }
        
        echo '<div class="message">If the email address exists in our system, a password reset link has been sent to it.</div>';
    }
    
    private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    private function sendResetEmail($email, $token) {
        $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/password_reset_confirm.php?token=" . urlencode($token);
        
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $resetLink . "\n\nThis link will expire in 30 minutes.";
        $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        mail($email, $subject, $message, $headers);
    }
    
    private function logAttempt($email, $ip) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] Password reset requested for: %s from IP: %s\n", $timestamp, $email, $ip);
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function displayForm() {
        echo '<form method="POST" action="">
                <label for="email">Email Address:</label>
                <input type="email" name="email" id="email" required>
                <button type="submit">Reset Password</button>
              </form>';
    }
    
    private function displayRateLimit() {
        echo '<div class="error">Too many password reset attempts. Please try again later.</div>';
    }
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=ecommerce', $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $resetRequest = new PasswordResetRequest($pdo);
    $resetRequest->handleRequest();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo '<div class="error">An error occurred. Please try again later.</div>';
}
?>


<?php
// password_reset_confirm.php

session_start();

class PasswordResetConfirm {
    private $db;
    private $logFile = '/secure/logs/password_reset.log';
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function handleRequest() {
        $token = $_GET['token'] ?? '';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processPasswordUpdate($token);
        } else {
            $this->displayForm($token);
        }
    }
    
    private function processPasswordUpdate($token) {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || strlen($password) < 8) {
            echo '<div class="error">Password must be at least 8 characters long.</div>';
            $this->displayForm($token);
            return;
        }
        
        if ($password !== $confirmPassword) {
            echo '<div class="error">Passwords do not match.</div>';
            $this->displayForm($token);
            return;
        }
        
        if (!$this->isValidToken($token)) {
            echo '<div class="error">Invalid or expired reset token.</div>';
            return;
        }
        
        $hashedToken = hash('sha256', $token);
        
        $stmt = $this->db->prepare("
            SELECT prt.user_id, u.email 
            FROM password_reset_tokens prt
            JOIN users u ON prt.user_id = u.user_id
            WHERE prt.token = ? AND prt.expires_at > NOW() AND prt.used = 0
        ");
        $stmt->execute([$hashedToken]);
        $resetData = $stmt->fetch();
        
        if (!$resetData) {
            echo '<div class="error">Invalid or expired reset token.</div>';
            return;
        }
        
        $this->db->beginTransaction();
        
        try {
            $hashedPassword = password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
            
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->execute([$hashedPassword, $resetData['user_id']]);
            
            $stmt = $this->db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$hashedToken]);
            
            $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ? AND token != ?");
            $stmt->execute([$resetData['user_id'], $hashedToken]);
            
            $this->db->commit();
            
            $this->logPasswordChange($resetData['email'], $_SERVER['REMOTE_ADDR']);
            
            echo '<div class="message">Your password has been successfully updated. You can now log in with your new password.</div>';
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            echo '<div class="error">An error occurred while updating your password. Please try again.</div>';
        }
    }
    
    private function isValidToken($token) {
        if (empty($token) || !ctype_xdigit($token) || strlen($token) !== 64) {
            return false;
        }
        return true;
    }
    
    private function logPasswordChange($email, $ip) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] Password changed for: %s from IP: %s\n", $timestamp, $email, $ip);
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function displayForm($token) {
        if (!$this->isValidToken($token)) {
            echo '<div class="error">Invalid reset token.</div>';
            return;
        }
        
        $hashedToken = hash('sha256', $token);
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as valid_count 
            FROM password_reset_tokens 
            WHERE token = ? AND expires_at > NOW() AND used = 0
        ");
        $stmt->execute([$hashedToken]);
        $result = $stmt->fetch();
        
        if ($result['valid_count'] == 0) {
            echo '<div class="error">This reset link has expired or has already been used.</div>';
            return;
        }
        
        echo '<form method="POST" action="">
                <input type="hidden" name="token" value="' . htmlspecialchars($token) . '">
                <label for="password">New Password:</label>
                <input type="password" name="password" id="password" required minlength="8">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" name="confirm_password" id="confirm_password" required minlength="8">
                <button type="submit">Update Password</button>
              </form>';
    }
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=ecommerce', $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    $resetConfirm = new PasswordResetConfirm($pdo);
    $resetConfirm->handleRequest();
    
} catch (Exception $e) {
    error_log($e->getMessage());
    echo '<div class="error">An error occurred. Please try again later.</div>';
}
?>


sql
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);
?>