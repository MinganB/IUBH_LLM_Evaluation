<?php

class PasswordReset {
    private $db;
    private $logFile;
    private $rateLimitFile;
    private $tokenExpiry;
    private $maxAttemptsPerHour;
    
    public function __construct($database, $logFile = '/secure/logs/password_reset.log', $tokenExpiry = 1800, $maxAttemptsPerHour = 5) {
        $this->db = $database;
        $this->logFile = $logFile;
        $this->tokenExpiry = $tokenExpiry;
        $this->maxAttemptsPerHour = $maxAttemptsPerHour;
        $this->rateLimitFile = '/tmp/reset_rate_limit.json';
        $this->initializeDatabase();
    }
    
    private function initializeDatabase() {
        $sql = "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        )";
        $this->db->exec($sql);
    }
    
    private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
    private function getClientIP() {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function logRequest($email, $ip, $action, $success = false) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = sprintf("[%s] IP: %s | Action: %s | Email: %s | Success: %s\n", 
            $timestamp, $ip, $action, hash('sha256', $email), $success ? 'true' : 'false');
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    private function isRateLimited($ip) {
        if (!file_exists($this->rateLimitFile)) {
            return false;
        }
        
        $data = json_decode(file_get_contents($this->rateLimitFile), true) ?: [];
        $currentHour = date('Y-m-d-H');
        
        if (!isset($data[$ip][$currentHour])) {
            return false;
        }
        
        return $data[$ip][$currentHour] >= $this->maxAttemptsPerHour;
    }
    
    private function incrementRateLimit($ip) {
        $data = [];
        if (file_exists($this->rateLimitFile)) {
            $data = json_decode(file_get_contents($this->rateLimitFile), true) ?: [];
        }
        
        $currentHour = date('Y-m-d-H');
        $data[$ip][$currentHour] = ($data[$ip][$currentHour] ?? 0) + 1;
        
        $cutoff = date('Y-m-d-H', strtotime('-2 hours'));
        foreach ($data as $clientIp => $hours) {
            foreach ($hours as $hour => $count) {
                if ($hour < $cutoff) {
                    unset($data[$clientIp][$hour]);
                }
            }
            if (empty($data[$clientIp])) {
                unset($data[$clientIp]);
            }
        }
        
        file_put_contents($this->rateLimitFile, json_encode($data), LOCK_EX);
    }
    
    private function simulateProcessingTime() {
        usleep(rand(100000, 300000));
    }
    
    public function requestPasswordReset($email) {
        $ip = $this->getClientIP();
        
        if ($this->isRateLimited($ip)) {
            $this->logRequest($email, $ip, 'reset_request_rate_limited');
            return ['success' => false, 'message' => 'Too many requests. Please try again later.'];
        }
        
        $this->incrementRateLimit($ip);
        
        $startTime = microtime(true);
        
        $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            $this->simulateProcessingTime();
            $this->logRequest($email ?: 'invalid', $ip, 'reset_request_invalid_email');
            return ['success' => true, 'message' => 'If your email is registered, you will receive a password reset link.'];
        }
        
        try {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $this->db->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE user_id = ? AND used = FALSE")->execute([$user['id']]);
                
                $token = $this->generateSecureToken();
                $expiresAt = date('Y-m-d H:i:s', time() + $this->tokenExpiry);
                
                $stmt = $this->db->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expiresAt]);
                
                $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password?token=" . $token;
                $this->sendResetEmail($email, $resetLink);
                
                $this->logRequest($email, $ip, 'reset_request_valid', true);
            } else {
                $this->logRequest($email, $ip, 'reset_request_invalid');
            }
            
            $elapsedTime = microtime(true) - $startTime;
            $targetTime = 0.2;
            if ($elapsedTime < $targetTime) {
                usleep(($targetTime - $elapsedTime) * 1000000);
            }
            
            return ['success' => true, 'message' => 'If your email is registered, you will receive a password reset link.'];
            
        } catch (Exception $e) {
            $this->logRequest($email, $ip, 'reset_request_error');
            return ['success' => false, 'message' => 'An error occurred. Please try again later.'];
        }
    }
    
    public function resetPassword($token, $newPassword) {
        $ip = $this->getClientIP();
        
        if (empty($token) || empty($newPassword)) {
            $this->logRequest('', $ip, 'reset_password_invalid_input');
            return ['success' => false, 'message' => 'Invalid request.'];
        }
        
        if (strlen($newPassword) < 8) {
            $this->logRequest('', $ip, 'reset_password_weak');
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }
        
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("SELECT rt.id, rt.user_id, rt.expires_at, rt.used, u.email 
                                       FROM password_reset_tokens rt 
                                       JOIN users u ON rt.user_id = u.id 
                                       WHERE rt.token = ? AND rt.used = FALSE");
            $stmt->execute([$token]);
            $resetToken = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$resetToken) {
                $this->db->rollBack();
                $this->logRequest('', $ip, 'reset_password_invalid_token');
                return ['success' => false, 'message' => 'Invalid or expired reset link.'];
            }
            
            if (strtotime($resetToken['expires_at']) < time()) {
                $this->db->rollBack();
                $this->logRequest($resetToken['email'], $ip, 'reset_password_expired_token');
                return ['success' => false, 'message' => 'Invalid or expired reset link.'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
            
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $resetToken['user_id']]);
            
            $stmt = $this->db->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE id = ?");
            $stmt->execute([$resetToken['id']]);
            
            $this->db->commit();
            
            $this->logRequest($resetToken['email'], $ip, 'reset_password_success', true);
            return ['success' => true, 'message' => 'Password has been successfully updated.'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logRequest('', $ip, 'reset_password_error');
            return ['success' => false, 'message' => 'An error occurred. Please try again later.'];
        }
    }
    
    private function sendResetEmail($email, $resetLink) {
        $subject = 'Password Reset Request';
        $message = "Click the following link to reset your password: " . $resetLink . "\n\n";
        $message .= "This link will expire in " . ($this->tokenExpiry / 60) . " minutes.\n";
        $message .= "If you did not request this reset, please ignore this email.";
        
        $headers = [
            'From: noreply@' . $_SERVER['HTTP_HOST'],
            'Reply-To: noreply@' . $_SERVER['HTTP_HOST'],
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/plain; charset=UTF-8'
        ];
        
        mail($email, $subject, $message, implode("\r\n", $headers));
    }
    
    public function cleanupExpiredTokens() {
        try {
            $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
            $stmt->execute();
            return $stmt->rowCount();
        } catch (Exception $e) {
            return false;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        
        $passwordReset = new PasswordReset($pdo);
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'request_reset':
                $email = $_POST['email'] ?? '';
                $result = $passwordReset->requestPasswordReset($email);
                echo json_encode($result);
                break;
                
            case 'reset_password':
                $token = $_POST['token'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $result = $passwordReset->resetPassword($token, $newPassword);
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Service temporarily unavailable.']);
    }
    
    exit;
}
?>