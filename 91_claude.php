**reset_request.php**

<?php
session_start();

class PasswordResetRequest {
    private $pdo;
    
    public function __construct() {
        $this->pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function generateToken() {
        return bin2hex(random_bytes(32));
    }
    
    public function findUserByEmail($email) {
        $stmt = $this->pdo->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function saveResetToken($userId, $token) {
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $stmt = $this->pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
        return $stmt->execute([$userId, $token, $expiry]);
    }
    
    public function sendResetEmail($email, $token) {
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
        
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password:\n\n" . $resetLink . "\n\nThis link will expire in 1 hour.";
        $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Reply-To: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        return mail($email, $subject, $message, $headers);
    }
    
    public function processRequest($email) {
        $user = $this->findUserByEmail($email);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email address not found'];
        }
        
        $token = $this->generateToken();
        
        if ($this->saveResetToken($user['id'], $token)) {
            if ($this->sendResetEmail($email, $token)) {
                return ['success' => true, 'message' => 'Password reset link has been sent to your email'];
            } else {
                return ['success' => false, 'message' => 'Failed to send email'];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to generate reset token'];
        }
    }
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $resetHandler = new PasswordResetRequest();
        $result = $resetHandler->processRequest($email);
        $message = $result['message'];
    } else {
        $message = 'Please enter a valid email address';
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Request</title>
</head>
<body>
    <h2>Reset Your Password</h2>
    
    <?php if ($message): ?>
        <div><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
        <div>
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div>
            <button type="submit">Send Reset Link</button>
        </div>
    </form>
</body>
</html>


**reset_password.php**

<?php
session_start();

class PasswordReset {
    private $pdo;
    
    public function __construct() {
        $this->pdo = new PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function validateToken($token) {
        $stmt = $this->pdo->prepare("
            SELECT pr.user_id, pr.expires_at, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $userId]);
    }
    
    public function deleteResetToken($userId) {
        $stmt = $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
        return $stmt->execute([$userId]);
    }
    
    public function processReset($token, $newPassword, $confirmPassword) {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        if ($newPassword !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        
        $tokenData = $this->validateToken($token);
        
        if (!$tokenData) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        if ($this->updatePassword($tokenData['user_id'], $newPassword)) {
            $this->deleteResetToken($tokenData['user_id']);
            return ['success' => true, 'message' => 'Password has been successfully updated'];
        } else {
            return ['success' => false, 'message' => 'Failed to update password'];
        }
    }
}

$message = '';
$token = $_GET['token'] ?? '';
$showForm = false;

if ($token) {
    $resetHandler = new PasswordReset();
    $tokenData = $resetHandler->validateToken($token);
    
    if ($tokenData) {
        $showForm = true;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            $result = $resetHandler->processReset($token, $newPassword, $confirmPassword);
            $message = $result['message'];
            
            if ($result['success']) {
                $showForm = false;
            }
        }
    } else {
        $message = 'Invalid or expired reset token';
    }
} else {
    $message = 'No reset token provided';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Password</h2>
    
    <?php if ($message): ?>
        <div><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($showForm): ?>
        <form method="POST" action="">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div>
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
            </div>
            
            <div>
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            
            <div>
                <button type="submit">Update Password</button>
            </div>
        </form>
    <?php endif; ?>
    
    <?php if (!$showForm && !empty($message)): ?>
        <div>
            <a href="login.php">Back to Login</a>
        </div>
    <?php endif; ?>
</body>
</html>


**Database Tables SQL:**

sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (token),
    INDEX (expires_at)
);