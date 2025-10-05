<?php

class PasswordResetModule
{
    private $db;
    private $config;
    private $logger;
    
    public function __construct($database, $configuration)
    {
        $this->db = $database;
        $this->config = $configuration;
        $this->logger = new SecurityLogger();
    }
    
    public function requestPasswordReset($email, $ipAddress)
    {
        $startTime = microtime(true);
        
        if (!$this->checkRateLimit($ipAddress)) {
            $this->logger->logPasswordResetAttempt($email, $ipAddress, 'rate_limited');
            return ['success' => false, 'message' => 'Too many requests. Please try again later.'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->simulateProcessingTime($startTime);
            $this->logger->logPasswordResetAttempt($email, $ipAddress, 'invalid_email');
            return ['success' => true, 'message' => 'If your email address is in our system, you will receive a password reset link.'];
        }
        
        $user = $this->getUserByEmail($email);
        
        if ($user) {
            $token = $this->generateSecureToken();
            $expiryTime = date('Y-m-d H:i:s', time() + ($this->config['reset_token_expiry'] ?? 1800));
            
            $this->storeResetToken($user['id'], $token, $expiryTime);
            $this->sendPasswordResetEmail($email, $token);
            $this->logger->logPasswordResetAttempt($email, $ipAddress, 'success');
        } else {
            $this->logger->logPasswordResetAttempt($email, $ipAddress, 'user_not_found');
        }
        
        $this->simulateProcessingTime($startTime);
        return ['success' => true, 'message' => 'If your email address is in our system, you will receive a password reset link.'];
    }
    
    public function resetPassword($token, $newPassword, $ipAddress)
    {
        if (strlen($newPassword) < 8) {
            $this->logger->logPasswordResetAttempt('', $ipAddress, 'weak_password');
            return ['success' => false, 'message' => 'Password does not meet security requirements.'];
        }
        
        $resetData = $this->getValidResetToken($token);
        
        if (!$resetData) {
            $this->logger->logPasswordResetAttempt('', $ipAddress, 'invalid_token');
            return ['success' => false, 'message' => 'Invalid or expired reset token.'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
        
        $this->updateUserPassword($resetData['user_id'], $hashedPassword);
        $this->invalidateResetToken($resetData['id']);
        $this->logger->logPasswordResetAttempt('', $ipAddress, 'password_updated');
        
        return ['success' => true, 'message' => 'Password has been successfully updated.'];
    }
    
    private function checkRateLimit($ipAddress)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM password_reset_attempts WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([$ipAddress]);
        $attempts = $stmt->fetchColumn();
        
        $maxAttempts = $this->config['max_reset_attempts_per_hour'] ?? 5;
        
        if ($attempts >= $maxAttempts) {
            return false;
        }
        
        $stmt = $this->db->prepare("INSERT INTO password_reset_attempts (ip_address, created_at) VALUES (?, NOW())");
        $stmt->execute([$ipAddress]);
        
        return true;
    }
    
    private function getUserByEmail($email)
    {
        $stmt = $this->db->prepare("SELECT id, email FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function generateSecureToken()
    {
        return bin2hex(random_bytes(32));
    }
    
    private function storeResetToken($userId, $token, $expiryTime)
    {
        $hashedToken = hash('sha256', $token);
        
        $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $stmt = $this->db->prepare("INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$userId, $hashedToken, $expiryTime]);
    }
    
    private function getValidResetToken($token)
    {
        $hashedToken = hash('sha256', $token);
        
        $stmt = $this->db->prepare("SELECT id, user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > NOW() AND used = 0");
        $stmt->execute([$hashedToken]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function updateUserPassword($userId, $hashedPassword)
    {
        $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $userId]);
    }
    
    private function invalidateResetToken($tokenId)
    {
        $stmt = $this->db->prepare("UPDATE password_reset_tokens SET used = 1, used_at = NOW() WHERE id = ?");
        $stmt->execute([$tokenId]);
    }
    
    private function sendPasswordResetEmail($email, $token)
    {
        $resetUrl = $this->config['base_url'] . '/reset-password?token=' . $token;
        $subject = 'Password Reset Request';
        $message = "Click the following link to reset your password: " . $resetUrl . "\n\nThis link will expire in 30 minutes.";
        
        mail($email, $subject, $message, 'From: ' . $this->config['from_email']);
    }
    
    private function simulateProcessingTime($startTime)
    {
        $targetTime = 0.5;
        $elapsedTime = microtime(true) - $startTime;
        
        if ($elapsedTime < $targetTime) {
            usleep(($targetTime - $elapsedTime) * 1000000);
        }
    }
}

class SecurityLogger
{
    private $logFile;
    
    public function __construct()
    {
        $this->logFile = '/var/log/password_reset.log';
    }
    
    public function logPasswordResetAttempt($email, $ipAddress, $status)
    {
        $timestamp = date('Y-m-d H:i:s');
        $emailHash = $email ? hash('sha256', $email) : 'unknown';
        $logEntry = sprintf(
            "[%s] IP: %s | Email Hash: %s | Status: %s\n",
            $timestamp,
            $ipAddress,
            $emailHash,
            $status
        );
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

class PasswordResetHandler
{
    private $passwordResetModule;
    
    public function __construct($database, $config)
    {
        $this->passwordResetModule = new PasswordResetModule($database, $config);
    }
    
    public function handleRequest()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $ipAddress = $this->getClientIP();
        
        header('Content-Type: application/json');
        
        if ($method === 'POST' && $path === '/api/password-reset/request') {
            $this->handlePasswordResetRequest($ipAddress);
        } elseif ($method === 'POST' && $path === '/api/password-reset/confirm') {
            $this->handlePasswordResetConfirm($ipAddress);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Endpoint not found.']);
        }
    }
    
    private function handlePasswordResetRequest($ipAddress)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['email'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email is required.']);
            return;
        }
        
        $result = $this->passwordResetModule->requestPasswordReset($input['email'], $ipAddress);
        
        if (!$result['success']) {
            http_response_code(429);
        }
        
        echo json_encode($result);
    }
    
    private function handlePasswordResetConfirm($ipAddress)
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['token']) || !isset($input['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Token and password are required.']);
            return;
        }
        
        $result = $this->passwordResetModule->resetPassword($input['token'], $input['password'], $ipAddress);
        
        if (!$result['success']) {
            http_response_code(400);
        }
        
        echo json_encode($result);
    }
    
    private function getClientIP()
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

$config = [
    'base_url' => 'https://yoursite.com',
    'from_email' => 'noreply@yoursite.com',
    'reset_token_expiry' => 1800,
    'max_reset_attempts_per_hour' => 5
];

try {
    $pdo = new PDO('mysql:host=localhost;dbname=ecommerce', $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    $handler = new PasswordResetHandler($pdo, $config);
    $handler->handleRequest();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?>