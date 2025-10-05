<?php

class PasswordResetManager
{
    private $db;
    private $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'token_expiry' => 3600,
            'max_attempts' => 5,
            'attempt_window' => 900,
            'from_email' => 'noreply@example.com',
            'subject' => 'Password Reset Request'
        ], $config);
    }

    public function requestPasswordReset(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address'];
        }

        if ($this->hasExceededAttempts($email)) {
            return ['success' => false, 'message' => 'Too many attempts. Please try again later.'];
        }

        $user = $this->getUserByEmail($email);
        if (!$user) {
            $this->logAttempt($email);
            return ['success' => true, 'message' => 'If the email exists, a reset link will be sent.'];
        }

        $token = $this->generateToken();
        $hashedToken = password_hash($token, PASSWORD_ARGON2ID);
        $expires = date('Y-m-d H:i:s', time() + $this->config['token_expiry']);

        $this->invalidateExistingTokens($user['id']);
        
        $stmt = $this->db->prepare("
            INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        
        if ($stmt->execute([$user['id'], $hashedToken, $expires])) {
            $this->sendResetEmail($email, $token, $user['name'] ?? '');
            return ['success' => true, 'message' => 'Password reset email sent'];
        }

        return ['success' => false, 'message' => 'Failed to process request'];
    }

    public function resetPassword(string $token, string $newPassword): array
    {
        if (!$this->validatePassword($newPassword)) {
            return ['success' => false, 'message' => 'Password does not meet requirements'];
        }

        $resetData = $this->validateResetToken($token);
        if (!$resetData) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $resetData['user_id']]);

            $this->invalidateAllUserTokens($resetData['user_id']);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Password updated successfully'];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to update password'];
        }
    }

    public function cleanupExpiredTokens(): int
    {
        $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    private function getUserByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT id, email, name FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function validateResetToken(string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT user_id, token_hash 
            FROM password_reset_tokens 
            WHERE expires_at > NOW() AND used_at IS NULL
        ");
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($token, $row['token_hash'])) {
                $this->markTokenAsUsed($row['user_id'], $row['token_hash']);
                return $row;
            }
        }
        
        return null;
    }

    private function markTokenAsUsed(int $userId, string $tokenHash): void
    {
        $stmt = $this->db->prepare("
            UPDATE password_reset_tokens 
            SET used_at = NOW() 
            WHERE user_id = ? AND token_hash = ?
        ");
        $stmt->execute([$userId, $tokenHash]);
    }

    private function invalidateExistingTokens(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE password_reset_tokens 
            SET used_at = NOW() 
            WHERE user_id = ? AND used_at IS NULL
        ");
        $stmt->execute([$userId]);
    }

    private function invalidateAllUserTokens(int $userId): void
    {
        $stmt = $this->db->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    private function validatePassword(string $password): bool
    {
        return strlen($password) >= 8 && 
               preg_match('/[A-Z]/', $password) &&
               preg_match('/[a-z]/', $password) &&
               preg_match('/[0-9]/', $password) &&
               preg_match('/[^A-Za-z0-9]/', $password);
    }

    private function hasExceededAttempts(string $email): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM password_reset_attempts 
            WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$email, $this->config['attempt_window']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['count'] >= $this->config['max_attempts'];
    }

    private function logAttempt(string $email): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO password_reset_attempts (email, ip_address, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
    }

    private function sendResetEmail(string $email, string $token, string $name): void
    {
        $resetUrl = $this->config['reset_url'] ?? 'https://example.com/reset-password';
        $resetLink = $resetUrl . '?token=' . urlencode($token);
        
        $subject = $this->config['subject'];
        $message = $this->buildEmailMessage($resetLink, $name);
        $headers = $this->buildEmailHeaders();
        
        mail($email, $subject, $message, $headers);
    }

    private function buildEmailMessage(string $resetLink, string $name): string
    {
        $greeting = $name ? "Hello {$name}," : "Hello,";
        
        return "{$greeting}\n\n" .
               "You have requested a password reset. Click the link below to reset your password:\n\n" .
               "{$resetLink}\n\n" .
               "This link will expire in " . ($this->config['token_expiry'] / 60) . " minutes.\n\n" .
               "If you did not request this reset, please ignore this email.\n\n" .
               "Best regards";
    }

    private function buildEmailHeaders(): string
    {
        return "From: " . $this->config['from_email'] . "\r\n" .
               "Reply-To: " . $this->config['from_email'] . "\r\n" .
               "Content-Type: text/plain; charset=UTF-8\r\n" .
               "X-Mailer: PHP/" . phpversion();
    }
}

class PasswordResetController
{
    private $resetManager;

    public function __construct(PasswordResetManager $resetManager)
    {
        $this->resetManager = $resetManager;
    }

    public function handleRequest(): void
    {
        session_start();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            return;
        }

        if (!$this->validateCSRFToken()) {
            $this->jsonResponse(['success' => false, 'message' => 'Invalid CSRF token'], 403);
            return;
        }

        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'request_reset':
                $this->handleRequestReset();
                break;
            case 'reset_password':
                $this->handleResetPassword();
                break;
            default:
                $this->jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
        }
    }

    private function handleRequestReset(): void
    {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        
        if (!$email) {
            $this->jsonResponse(['success' => false, 'message' => 'Email is required']);
            return;
        }

        $result = $this->resetManager->requestPasswordReset($email);
        $this->jsonResponse($result);
    }

    private function handleResetPassword(): void
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!$token || !$password || !$confirmPassword) {
            $this->jsonResponse(['success' => false, 'message' => 'All fields are required']);
            return;
        }

        if ($password !== $confirmPassword) {
            $this->jsonResponse(['success' => false, 'message' => 'Passwords do not match']);
            return;
        }

        $result = $this->resetManager->resetPassword($token, $password);
        $this->jsonResponse($result);
    }

    private function validateCSRFToken(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function generateCSRFToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

function createDatabaseTables(PDO $db): void
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_expires (expires_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        
        "CREATE TABLE IF NOT EXISTS password_reset_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_email_created (email, created_at)
        )"
    ];

    foreach ($queries as $query) {
        $db->exec($query);
    }
}

function setupPasswordResetCronJob(PDO $db, array $config = []): void
{
    $resetManager = new PasswordResetManager($db, $config);
    $cleanedTokens = $resetManager->cleanupExpiredTokens();
    
    $stmt = $db->prepare("DELETE FROM password_reset_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $stmt->execute();
    $cleanedAttempts = $stmt->rowCount();
    
    error_log("Cleaned up {$cleanedTokens} expired tokens and {$cleanedAttempts} old attempts");
}
?>