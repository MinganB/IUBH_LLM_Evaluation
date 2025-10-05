<?php

class PasswordResetManager
{
    private $pdo;
    private $mailer;
    private $config;
    
    public function __construct(PDO $pdo, MailerInterface $mailer, array $config = [])
    {
        $this->pdo = $pdo;
        $this->mailer = $mailer;
        $this->config = array_merge([
            'token_expiry_hours' => 24,
            'reset_url' => 'https://yoursite.com/reset-password',
            'from_email' => 'noreply@yoursite.com',
            'from_name' => 'Your Site',
            'max_attempts_per_hour' => 5
        ], $config);
    }
    
    public function requestPasswordReset(string $email): array
    {
        try {
            $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
            if (!$email) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            if (!$this->checkRateLimit($email)) {
                return ['success' => false, 'message' => 'Too many reset requests. Please try again later.'];
            }
            
            $user = $this->getUserByEmail($email);
            if (!$user) {
                return ['success' => true, 'message' => 'If the email exists, a reset link has been sent.'];
            }
            
            $token = $this->generateSecureToken();
            $expiresAt = new DateTime();
            $expiresAt->add(new DateInterval('PT' . $this->config['token_expiry_hours'] . 'H'));
            
            $this->storeResetToken($user['id'], $token, $expiresAt);
            $this->logResetAttempt($email);
            
            $resetUrl = $this->config['reset_url'] . '?token=' . $token;
            $this->sendResetEmail($email, $user['username'], $resetUrl);
            
            return ['success' => true, 'message' => 'Password reset link has been sent to your email.'];
            
        } catch (Exception $e) {
            error_log('Password reset request error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again later.'];
        }
    }
    
    public function resetPassword(string $token, string $newPassword): array
    {
        try {
            if (strlen($newPassword) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
            }
            
            if (!$this->isPasswordStrong($newPassword)) {
                return ['success' => false, 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character'];
            }
            
            $resetData = $this->validateResetToken($token);
            if (!$resetData) {
                return ['success' => false, 'message' => 'Invalid or expired reset token'];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3
            ]);
            
            $this->updateUserPassword($resetData['user_id'], $hashedPassword);
            $this->invalidateResetToken($token);
            $this->invalidateUserSessions($resetData['user_id']);
            
            return ['success' => true, 'message' => 'Password has been reset successfully'];
            
        } catch (Exception $e) {
            error_log('Password reset error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again later.'];
        }
    }
    
    private function getUserByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, email FROM users WHERE email = ? AND status = "active"');
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }
    
    private function storeResetToken(int $userId, string $token, DateTime $expiresAt): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO password_reset_tokens (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            token = VALUES(token), 
            expires_at = VALUES(expires_at), 
            created_at = VALUES(created_at),
            used = 0
        ');
        $stmt->execute([$userId, hash('sha256', $token), $expiresAt->format('Y-m-d H:i:s')]);
    }
    
    private function validateResetToken(string $token): ?array
    {
        $hashedToken = hash('sha256', $token);
        $stmt = $this->pdo->prepare('
            SELECT user_id, expires_at 
            FROM password_reset_tokens 
            WHERE token = ? AND used = 0 AND expires_at > NOW()
        ');
        $stmt->execute([$hashedToken]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    private function updateUserPassword(int $userId, string $hashedPassword): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$hashedPassword, $userId]);
    }
    
    private function invalidateResetToken(string $token): void
    {
        $hashedToken = hash('sha256', $token);
        $stmt = $this->pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE token = ?');
        $stmt->execute([$hashedToken]);
    }
    
    private function invalidateUserSessions(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_sessions WHERE user_id = ?');
        $stmt->execute([$userId]);
    }
    
    private function checkRateLimit(string $email): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) as attempts 
            FROM password_reset_attempts 
            WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ');
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['attempts'] < $this->config['max_attempts_per_hour'];
    }
    
    private function logResetAttempt(string $email): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO password_reset_attempts (email, ip_address, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    }
    
    private function isPasswordStrong(string $password): bool
    {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $password);
    }
    
    private function sendResetEmail(string $email, string $username, string $resetUrl): void
    {
        $subject = 'Password Reset Request';
        $message = "Hello {$username},\n\n";
        $message .= "You have requested to reset your password. Please click the link below to reset your password:\n\n";
        $message .= "{$resetUrl}\n\n";
        $message .= "This link will expire in {$this->config['token_expiry_hours']} hours.\n\n";
        $message .= "If you did not request this password reset, please ignore this email.\n\n";
        $message .= "Best regards,\n{$this->config['from_name']}";
        
        $this->mailer->send($email, $subject, $message, $this->config['from_email'], $this->config['from_name']);
    }
}

interface MailerInterface
{
    public function send(string $to, string $subject, string $message, string $fromEmail, string $fromName): bool;
}

class SimpleMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $message, string $fromEmail, string $fromName): bool
    {
        $headers = [
            'From' => "{$fromName} <{$fromEmail}>",
            'Reply-To' => $fromEmail,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/plain; charset=UTF-8'
        ];
        
        $headerString = '';
        foreach ($headers as $key => $value) {
            $headerString .= "{$key}: {$value}\r\n";
        }
        
        return mail($to, $subject, $message, $headerString);
    }
}

class PasswordResetController
{
    private $passwordResetManager;
    
    public function __construct(PasswordResetManager $passwordResetManager)
    {
        $this->passwordResetManager = $passwordResetManager;
    }
    
    public function handleRequest(): void
    {
        session_start();
        
        if (!$this->validateCSRFToken()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            return;
        }
        
        $method = $_SERVER['REQUEST_METHOD'];
        
        try {
            if ($method === 'POST') {
                $this->handlePasswordResetRequest();
            } elseif ($method === 'PUT') {
                $this->handlePasswordReset();
            } else {
                http_response_code(405);
                echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            }
        } catch (Exception $e) {
            error_log('Password reset controller error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }
    
    private function handlePasswordResetRequest(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        
        $result = $this->passwordResetManager->requestPasswordReset($email);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    private function handlePasswordReset(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';
        $password = $input['password'] ?? '';
        
        $result = $this->passwordResetManager->resetPassword($token, $password);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }
    
    private function validateCSRFToken(): bool
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public function generateCSRFToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

$pdo = new PDO('mysql:host=localhost;dbname=ecommerce;charset=utf8mb4', $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]);

$mailer = new SimpleMailer();
$passwordResetManager = new PasswordResetManager($pdo, $mailer);
$controller = new PasswordResetController($passwordResetManager);


sql
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `used` TINYINT(1) DEFAULT 0,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_expires_at` (`expires_at`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `password_reset_attempts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email_created` (`email`, `created_at`)
);

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `user_id` INT NOT NULL,
    `data` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
?>