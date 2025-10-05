<?php
session_start();

class PasswordReset {
    private $pdo;
    private $smtpHost;
    private $smtpUsername;
    private $smtpPassword;
    private $fromEmail;
    
    public function __construct($database, $smtpConfig) {
        $this->pdo = $database;
        $this->smtpHost = $smtpConfig['host'];
        $this->smtpUsername = $smtpConfig['username'];
        $this->smtpPassword = $smtpConfig['password'];
        $this->fromEmail = $smtpConfig['from_email'];
    }
    
    public function requestReset($email) {
        $email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return true;
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        
        $stmt = $this->pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user['id'], hash('sha256', $token), $expires]);
        
        return $this->sendResetEmail($email, $token);
    }
    
    private function sendResetEmail($email, $token) {
        $resetUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/reset-password.php?token=' . urlencode($token);
        
        $subject = 'Password Reset Request';
        $message = "Hello,\n\nYou requested a password reset. Click the link below to reset your password:\n\n" . $resetUrl . "\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.";
        
        $headers = [
            'From' => $this->fromEmail,
            'Reply-To' => $this->fromEmail,
            'X-Mailer' => 'PHP/' . phpversion(),
            'Content-Type' => 'text/plain; charset=utf-8'
        ];
        
        return mail($email, $subject, $message, $headers);
    }
    
    public function validateToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([hash('sha256', $token)]);
        
        return $stmt->fetch();
    }
    
    public function resetPassword($token, $newPassword) {
        if (strlen($newPassword) < 8) {
            return false;
        }
        
        $reset = $this->validateToken($token);
        if (!$reset) {
            return false;
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);
        
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $reset['user_id']]);
            
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->execute([$reset['user_id']]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollback();
            return false;
        }
    }
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=ecommerce;charset=utf8mb4', 'username', 'password', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die('Database connection failed');
}

$smtpConfig = [
    'host' => 'smtp.example.com',
    'username' => 'noreply@example.com',
    'password' => 'smtp_password',
    'from_email' => 'noreply@example.com'
];

$passwordReset = new PasswordReset($pdo, $smtpConfig);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['email'])) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        $message = 'Invalid request';
    } else {
        if ($passwordReset->requestReset($_POST['email'])) {
            $message = 'If your email exists in our system, you will receive a password reset link shortly.';
        } else {
            $message = 'An error occurred. Please try again.';
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Request</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <h2>Reset Your Password</h2>
    
    <?php if (isset($message)): ?>
        <div><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    
    <form method="post" action="">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        
        <div>
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required maxlength="255">
        </div>
        
        <div>
            <button type="submit">Send Reset Link</button>
        </div>
    </form>
    
    <p><a href="login.php">Back to Login</a></p>
</body>
</html>


<?php
session_start();

try {
    $pdo = new PDO('mysql:host=localhost;dbname=ecommerce;charset=utf8mb4', 'username', 'password', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die('Database connection failed');
}

class PasswordReset {
    private $pdo;
    
    public function __construct($database) {
        $this->pdo = $database;
    }
    
    public function validateToken($token) {
        if (empty($token)) {
            return false;
        }
        
        $stmt = $this->pdo->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW()");
        $stmt->execute([hash('sha256', $token)]);
        
        return $stmt->fetch();
    }
    
    public function resetPassword($token, $newPassword) {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $newPassword)) {
            return ['success' => false, 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character'];
        }
        
        $reset = $this->validateToken($token);
        if (!$reset) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3]);
        
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashedPassword, $reset['user_id']]);
            
            $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
            $stmt->execute([$reset['user_id']]);
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Password reset successfully'];
        } catch (Exception $e) {
            $this->pdo->rollback();
            return ['success' => false, 'message' => 'An error occurred while resetting your password'];
        }
    }
}

$passwordReset = new PasswordReset($pdo);

$token = $_GET['token'] ?? '';
$validToken = false;
$message = '';

if ($token) {
    $validToken = $passwordReset->validateToken($token);
    if (!$validToken) {
        $message = 'Invalid or expired reset link. Please request a new password reset.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        $message = 'Invalid request';
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match';
        } else {
            $result = $passwordReset->resetPassword($token, $newPassword);
            $message = $result['message'];
            if ($result['success']) {
                $validToken = false;
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
    <h2>Reset Password</h2>
    
    <?php if ($message): ?>
        <div><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    
    <?php if ($validToken): ?>
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            
            <div>
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required minlength="8" maxlength="255">
                <small>Password must be at least 8 characters and contain uppercase, lowercase, number, and special character</small>
            </div>
            
            <div>
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" maxlength="255">
            </div>
            
            <div>
                <button type="submit">Reset Password</button>
            </div>
        </form>
    <?php else: ?>
        <p><a href="forgot-password.php">Request a new password reset</a></p>
    <?php endif; ?>
    
    <p><a href="login.php">Back to Login</a></p>
</body>
</html>
?>