<?php

class PasswordResetManager
{
    private $db;
    private $config;
    
    public function __construct($database, $config = [])
    {
        $this->db = $database;
        $this->config = array_merge([
            'token_expiry_hours' => 24,
            'max_reset_attempts' => 3,
            'from_email' => 'noreply@example.com',
            'from_name' => 'E-commerce Site',
            'reset_url_base' => 'https://example.com/reset-password'
        ], $config);
    }
    
    public function requestPasswordReset($email)
    {
        try {
            if (!$this->isValidEmail($email)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            $user = $this->getUserByEmail($email);
            if (!$user) {
                return ['success' => false, 'message' => 'No account found with this email address'];
            }
            
            if ($this->hasExceededResetAttempts($user['id'])) {
                return ['success' => false, 'message' => 'Maximum reset attempts exceeded. Please try again later'];
            }
            
            $token = $this->generateResetToken();
            $expiryTime = date('Y-m-d H:i:s', strtotime('+' . $this->config['token_expiry_hours'] . ' hours'));
            
            $this->storeResetToken($user['id'], $token, $expiryTime);
            
            $emailSent = $this->sendResetEmail($user['email'], $user['first_name'], $token);
            
            if ($emailSent) {
                return ['success' => true, 'message' => 'Password reset instructions have been sent to your email'];
            } else {
                return ['success' => false, 'message' => 'Failed to send reset email. Please try again'];
            }
            
        } catch (Exception $e) {
            error_log('Password reset request error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An error occurred. Please try again'];
        }
    }
    
    public function validateResetToken($token)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT pr.user_id, pr.expires_at, u.email, u.first_name 
                FROM password_resets pr 
                JOIN users u ON pr.user_id = u.id 
                WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return ['valid' => true, 'user_id' => $result['user_id'], 'email' => $result['email']];
            }
            
            return ['valid' => false, 'message' => 'Invalid or expired reset token'];
            
        } catch (Exception $e) {
            error_log('Token validation error: ' . $e->getMessage());
            return ['valid' => false, 'message' => 'Token validation failed'];
        }
    }
    
    public function resetPassword($token, $newPassword, $confirmPassword)
    {
        try {
            if ($newPassword !== $confirmPassword) {
                return ['success' => false, 'message' => 'Passwords do not match'];
            }
            
            if (!$this->isValidPassword($newPassword)) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number'];
            }
            
            $tokenValidation = $this->validateResetToken($token);
            if (!$tokenValidation['valid']) {
                return ['success' => false, 'message' => $tokenValidation['message']];
            }
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $tokenValidation['user_id']]);
            
            $stmt = $this->db->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE token = ?");
            $stmt->execute([$token]);
            
            $this->db->commit();
            
            return ['success' => true, 'message' => 'Password has been successfully reset'];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log('Password reset error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to reset password. Please try again'];
        }
    }
    
    private function getUserByEmail($email)
    {
        $stmt = $this->db->prepare("SELECT id, email, first_name FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function hasExceededResetAttempts($userId)
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempt_count 
            FROM password_resets 
            WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['attempt_count'] >= $this->config['max_reset_attempts'];
    }
    
    private function generateResetToken()
    {
        return bin2hex(random_bytes(32));
    }
    
    private function storeResetToken($userId, $token, $expiryTime)
    {
        $stmt = $this->db->prepare("
            INSERT INTO password_resets (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $token, $expiryTime]);
    }
    
    private function sendResetEmail($email, $firstName, $token)
    {
        $resetUrl = $this->config['reset_url_base'] . '?token=' . $token;
        
        $subject = 'Password Reset Request';
        $message = "
            <html>
            <body>
                <h2>Password Reset Request</h2>
                <p>Hello {$firstName},</p>
                <p>You have requested to reset your password. Please click the link below to set a new password:</p>
                <p><a href='{$resetUrl}'>Reset Password</a></p>
                <p>This link will expire in {$this->config['token_expiry_hours']} hours.</p>
                <p>If you did not request this reset, please ignore this email.</p>
                <p>Best regards,<br>{$this->config['from_name']}</p>
            </body>
            </html>
        ";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: {$this->config['from_name']} <{$this->config['from_email']}>" . "\r\n";
        
        return mail($email, $subject, $message, $headers);
    }
    
    private function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    private function isValidPassword($password)
    {
        return strlen($password) >= 8 && 
               preg_match('/[A-Z]/', $password) && 
               preg_match('/[a-z]/', $password) && 
               preg_match('/[0-9]/', $password);
    }
    
    public function cleanupExpiredTokens()
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM password_resets WHERE expires_at < NOW()");
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            error_log('Cleanup error: ' . $e->getMessage());
            return false;
        }
    }
}

class PasswordResetController
{
    private $passwordResetManager;
    
    public function __construct($database, $config = [])
    {
        $this->passwordResetManager = new PasswordResetManager($database, $config);
    }
    
    public function handleRequest()
    {
        session_start();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'request_reset':
                        return $this->handleResetRequest();
                    case 'reset_password':
                        return $this->handlePasswordReset();
                    default:
                        return $this->showError('Invalid action');
                }
            }
        }
        
        if (isset($_GET['token'])) {
            return $this->showResetForm($_GET['token']);
        }
        
        return $this->showRequestForm();
    }
    
    private function handleResetRequest()
    {
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email)) {
            return $this->showRequestForm('Email address is required');
        }
        
        $result = $this->passwordResetManager->requestPasswordReset($email);
        
        if ($result['success']) {
            return $this->showMessage($result['message'], 'success');
        } else {
            return $this->showRequestForm($result['message']);
        }
    }
    
    private function handlePasswordReset()
    {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirmPassword)) {
            return $this->showResetForm($token, 'All fields are required');
        }
        
        $result = $this->passwordResetManager->resetPassword($token, $password, $confirmPassword);
        
        if ($result['success']) {
            return $this->showMessage($result['message'], 'success');
        } else {
            return $this->showResetForm($token, $result['message']);
        }
    }
    
    private function showRequestForm($error = '')
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Reset Password</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <div>
                <h2>Reset Password</h2>
                ' . ($error ? '<div class="error">' . htmlspecialchars($error) . '</div>' : '') . '
                <form method="POST" action="">
                    <input type="hidden" name="action" value="request_reset">
                    <div>
                        <label for="email">Email Address:</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div>
                        <button type="submit">Send Reset Instructions</button>
                    </div>
                </form>
                <div>
                    <a href="/login">Back to Login</a>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function showResetForm($token, $error = '')
    {
        $validation = $this->passwordResetManager->validateResetToken($token);
        
        if (!$validation['valid']) {
            return $this->showMessage($validation['message'], 'error');
        }
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Set New Password</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <div>
                <h2>Set New Password</h2>
                <p>Enter your new password for: ' . htmlspecialchars($validation['email']) . '</p>
                ' . ($error ? '<div class="error">' . htmlspecialchars($error) . '</div>' : '') . '
                <form method="POST" action="">
                    <input type="hidden" name="action" value="reset_password">
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
                        <small>Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.</small>
                    </div>
                    <div>
                        <button type="submit">Reset Password</button>
                    </div>
                </form>
            </div>
        </body>
        </html>';
    }
    
    private function showMessage($message, $type = 'info')
    {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Password Reset</title>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body>
            <div>
                <h2>Password Reset</h2>
                <div class="' . $type . '">' . htmlspecialchars($message) . '</div>
                <div>
                    <a href="/login">Go to Login</a>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private function showError($message)
    {
        return $this->showMessage($message, 'error');
    }
}

?>