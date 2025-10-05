<?php

class PasswordResetManager {
    private $pdo;
    private $table_users = 'users';
    private $table_reset_tokens = 'password_reset_tokens';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createResetTokensTable();
    }
    
    private function createResetTokensTable() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_reset_tokens} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires (expires_at)
        )";
        $this->pdo->exec($sql);
    }
    
    public function requestPasswordReset($email) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM {$this->table_users} WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }
            
            $this->invalidateExistingTokens($user['id']);
            
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $this->pdo->prepare("INSERT INTO {$this->table_reset_tokens} (user_id, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $token, $expiresAt]);
            
            return $token;
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function validateResetToken($token) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT rt.id, rt.user_id, u.email 
                FROM {$this->table_reset_tokens} rt 
                JOIN {$this->table_users} u ON rt.user_id = u.id 
                WHERE rt.token = ? AND rt.expires_at > NOW() AND rt.used = FALSE
            ");
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function resetPassword($token, $newPassword) {
        try {
            $this->pdo->beginTransaction();
            
            $tokenData = $this->validateResetToken($token);
            if (!$tokenData) {
                $this->pdo->rollBack();
                return false;
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("UPDATE {$this->table_users} SET password = ? WHERE id = ?");
            $stmt->execute([$hashedPassword, $tokenData['user_id']]);
            
            $stmt = $this->pdo->prepare("UPDATE {$this->table_reset_tokens} SET used = TRUE WHERE id = ?");
            $stmt->execute([$tokenData['id']]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    private function invalidateExistingTokens($userId) {
        $stmt = $this->pdo->prepare("UPDATE {$this->table_reset_tokens} SET used = TRUE WHERE user_id = ? AND used = FALSE");
        $stmt->execute([$userId]);
    }
    
    public function cleanupExpiredTokens() {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->table_reset_tokens} WHERE expires_at < NOW() OR used = TRUE");
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

class PasswordResetController {
    private $passwordResetManager;
    private $mailer;
    
    public function __construct($pdo, $mailer = null) {
        $this->passwordResetManager = new PasswordResetManager($pdo);
        $this->mailer = $mailer;
    }
    
    public function handlePasswordResetRequest() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);
        
        if (!$email) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid email is required']);
            return;
        }
        
        $token = $this->passwordResetManager->requestPasswordReset($email);
        
        if ($token) {
            if ($this->mailer) {
                $resetUrl = $this->generateResetUrl($token);
                $this->sendResetEmail($email, $resetUrl);
            }
            echo json_encode(['success' => true, 'message' => 'Password reset email sent']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Password reset email sent']);
        }
    }
    
    public function handlePasswordReset() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';
        $password = $input['password'] ?? '';
        $confirmPassword = $input['confirm_password'] ?? '';
        
        if (empty($token) || empty($password) || empty($confirmPassword)) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields are required']);
            return;
        }
        
        if ($password !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['error' => 'Passwords do not match']);
            return;
        }
        
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 8 characters long']);
            return;
        }
        
        if (!$this->passwordResetManager->validateResetToken($token)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid or expired reset token']);
            return;
        }
        
        if ($this->passwordResetManager->resetPassword($token, $password)) {
            echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to reset password']);
        }
    }
    
    public function validateToken($token) {
        $tokenData = $this->passwordResetManager->validateResetToken($token);
        if ($tokenData) {
            echo json_encode(['valid' => true]);
        } else {
            http_response_code(400);
            echo json_encode(['valid' => false, 'error' => 'Invalid or expired token']);
        }
    }
    
    private function generateResetUrl($token) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        return "{$protocol}://{$host}/reset-password?token={$token}";
    }
    
    private function sendResetEmail($email, $resetUrl) {
        if (!$this->mailer) {
            return false;
        }
        
        $subject = 'Password Reset Request';
        $message = "Click the following link to reset your password: {$resetUrl}\n\nThis link will expire in 1 hour.";
        
        return $this->mailer->send($email, $subject, $message);
    }
}

interface MailerInterface {
    public function send($to, $subject, $message);
}

class SimpleMailer implements MailerInterface {
    public function send($to, $subject, $message) {
        $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        return mail($to, $subject, $message, $headers);
    }
}

header('Content-Type: application/json');

if (isset($_GET['action'])) {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $mailer = new SimpleMailer();
        $controller = new PasswordResetController($pdo, $mailer);
        
        switch ($_GET['action']) {
            case 'request':
                $controller->handlePasswordResetRequest();
                break;
            case 'reset':
                $controller->handlePasswordReset();
                break;
            case 'validate':
                if (isset($_GET['token'])) {
                    $controller->validateToken($_GET['token']);
                } else {
                    http_response_code(400);
                    echo json_encode(['error' => 'Token parameter required']);
                }
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Action not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error']);
    }
}
?>