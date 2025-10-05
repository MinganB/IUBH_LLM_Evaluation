<?php
session_start();
require_once 'config.php';

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generateResetToken() {
    return bin2hex(random_bytes(32));
}

function sendResetEmail($email, $token) {
    $resetLink = RESET_BASE_URL . '/reset_password.php?token=' . urlencode($token);
    $subject = 'Password Reset Request';
    $message = "Click the following link to reset your password:\n\n" . $resetLink . "\n\nThis link will expire in 1 hour.";
    $headers = 'From: ' . FROM_EMAIL . "\r\n" .
               'Reply-To: ' . FROM_EMAIL . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    
    return mail($email, $subject, $message, $headers);
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request';
        $messageType = 'error';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');
        
        if (!validateEmail($email)) {
            $message = 'Please enter a valid email address';
            $messageType = 'error';
        } else {
            try {
                $pdo = new PDO(DSN, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND active = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $token = generateResetToken();
                    $expiresAt = date('Y-m-d H:i:s', time() + 3600);
                    
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    
                    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$user['id'], hash('sha256', $token), $expiresAt]);
                    
                    if (sendResetEmail($email, $token)) {
                        $message = 'Password reset instructions have been sent to your email';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to send email. Please try again later';
                        $messageType = 'error';
                    }
                } else {
                    sleep(2);
                    $message = 'If an account with that email exists, password reset instructions have been sent';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                error_log('Database error in forgot_password.php: ' . $e->getMessage());
                $message = 'An error occurred. Please try again later';
                $messageType = 'error';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
</head>
<body>
    <div class="container">
        <h1>Forgot Password</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input type="email" id="email" name="email" required maxlength="255" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <button type="submit">Send Reset Link</button>
        </form>
        
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>


<?php
session_start();
require_once 'config.php';

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function validatePassword($password) {
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password) && 
           preg_match('/[^A-Za-z0-9]/', $password);
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

$token = $_GET['token'] ?? '';
$message = '';
$messageType = '';
$validToken = false;
$userId = null;

if (empty($token)) {
    $message = 'Invalid reset link';
    $messageType = 'error';
} else {
    try {
        $pdo = new PDO(DSN, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        $hashedToken = hash('sha256', $token);
        $stmt = $pdo->prepare("SELECT pr.user_id, pr.expires_at, u.email 
                               FROM password_resets pr 
                               JOIN users u ON pr.user_id = u.id 
                               WHERE pr.token = ? AND pr.expires_at > NOW() AND u.active = 1");
        $stmt->execute([$hashedToken]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            $validToken = true;
            $userId = $reset['user_id'];
        } else {
            $message = 'Invalid or expired reset link';
            $messageType = 'error';
        }
    } catch (PDOException $e) {
        error_log('Database error in reset_password.php: ' . $e->getMessage());
        $message = 'An error occurred. Please try again later';
        $messageType = 'error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request';
        $messageType = 'error';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($password) || empty($confirmPassword)) {
            $message = 'Please fill in all fields';
            $messageType = 'error';
        } elseif ($password !== $confirmPassword) {
            $message = 'Passwords do not match';
            $messageType = 'error';
        } elseif (!validatePassword($password)) {
            $message = 'Password must be at least 8 characters and contain uppercase, lowercase, number, and special character';
            $messageType = 'error';
        } else {
            try {
                $pdo->beginTransaction();
                
                $hashedPassword = hashPassword($password);
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                $pdo->commit();
                
                $message = 'Password has been successfully reset. You can now log in with your new password';
                $messageType = 'success';
                $validToken = false;
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Database error in reset_password.php: ' . $e->getMessage());
                $message = 'An error occurred. Please try again later';
                $messageType = 'error';
            }
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo htmlspecialchars($messageType); ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($validToken): ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                
                <div class="form-group">
                    <label for="password">New Password:</label>
                    <input type="password" id="password" name="password" required minlength="8" maxlength="255">
                    <small>Must be at least 8 characters with uppercase, lowercase, number, and special character</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" maxlength="255">
                </div>
                
                <button type="submit">Reset Password</button>
            </form>
        <?php endif; ?>
        
        <p><a href="login.php">Back to Login</a></p>
    </div>
</body>
</html>


<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DSN', 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4');

define('RESET_BASE_URL', 'https://yoursite.com');
define('FROM_EMAIL', 'noreply@yoursite.com');
?>