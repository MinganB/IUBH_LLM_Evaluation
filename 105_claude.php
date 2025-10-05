<?php
// request_reset.php
session_start();

function connectDB() {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
}

function isRateLimited($ip) {
    $lockFile = sys_get_temp_dir() . '/reset_rate_' . md5($ip) . '.lock';
    $maxAttempts = 3;
    $timeWindow = 900;
    
    if (file_exists($lockFile)) {
        $data = json_decode(file_get_contents($lockFile), true);
        if ($data && isset($data['attempts']) && isset($data['timestamp'])) {
            if (time() - $data['timestamp'] < $timeWindow) {
                if ($data['attempts'] >= $maxAttempts) {
                    return true;
                }
                $data['attempts']++;
            } else {
                $data = ['attempts' => 1, 'timestamp' => time()];
            }
        } else {
            $data = ['attempts' => 1, 'timestamp' => time()];
        }
    } else {
        $data = ['attempts' => 1, 'timestamp' => time()];
    }
    
    file_put_contents($lockFile, json_encode($data));
    return false;
}

function generateToken() {
    return bin2hex(random_bytes(16));
}

function logResetRequest($email, $ip) {
    $logEntry = date('Y-m-d H:i:s') . " - Password reset requested for: " . $email . " from IP: " . $ip . PHP_EOL;
    file_put_contents('/var/log/password_resets.log', $logEntry, FILE_APPEND | LOCK_EX);
}

function sendResetEmail($email, $token) {
    $resetLink = "https://yoursite.com/reset_password.php?token=" . urlencode($token);
    $subject = "Password Reset Request";
    $message = "Click the following link to reset your password: " . $resetLink . "\n\nThis link will expire in 30 minutes.";
    $headers = "From: noreply@yoursite.com\r\nReply-To: noreply@yoursite.com\r\n";
    
    return mail($email, $subject, $message, $headers);
}

$message = '';
$userIP = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isRateLimited($userIP)) {
        $message = 'Too many requests. Please try again later.';
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pdo = connectDB();
            
            if ($pdo) {
                $startTime = microtime(true);
                
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $token = generateToken();
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    
                    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, used) VALUES (?, ?, ?, 0)");
                    $stmt->execute([$email, $token, $expiresAt]);
                    
                    sendResetEmail($email, $token);
                }
                
                $elapsedTime = microtime(true) - $startTime;
                if ($elapsedTime < 0.5) {
                    usleep((0.5 - $elapsedTime) * 1000000);
                }
                
                logResetRequest($email, $userIP);
                $message = 'If the email address exists in our system, you will receive a password reset link shortly.';
            } else {
                $message = 'Service temporarily unavailable. Please try again later.';
            }
        } else {
            $message = 'Please enter a valid email address.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
</head>
<body>
    <h1>Reset Password</h1>
    
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    
    <form method="POST" action="request_reset.php">
        <label for="email">Email Address:</label>
        <input type="email" id="email" name="email" required>
        <button type="submit">Send Reset Link</button>
    </form>
</body>
</html>


<?php
// reset_password.php
session_start();

function connectDB() {
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
}

function validateToken($token) {
    if (empty($token) || strlen($token) !== 32 || !ctype_xdigit($token)) {
        return false;
    }
    
    $pdo = connectDB();
    if (!$pdo) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset || $reset['used'] || strtotime($reset['expires_at']) < time()) {
        return false;
    }
    
    return $reset;
}

function updatePassword($email, $token, $password) {
    $pdo = connectDB();
    if (!$pdo) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $result = $stmt->execute([$hashedPassword, $email]);
        
        if ($result) {
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
        }
        
        $pdo->commit();
        return $result;
    } catch (Exception $e) {
        $pdo->rollback();
        error_log('Password update failed: ' . $e->getMessage());
        return false;
    }
}

$message = '';
$error = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $postToken = $_POST['token'] ?? '';
    
    if (empty($password) || empty($confirmPassword) || empty($postToken)) {
        $error = 'All fields are required.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $resetData = validateToken($postToken);
        
        if ($resetData) {
            if (updatePassword($resetData['email'], $postToken, $password)) {
                $message = 'Password has been successfully reset. You can now log in with your new password.';
                $token = '';
            } else {
                $error = 'Failed to update password. Please try again.';
            }
        } else {
            $error = 'Invalid or expired reset token.';
        }
    }
}

$tokenValid = !empty($token) && validateToken($token);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <h1>Reset Password</h1>
    
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
        <a href="login.php">Return to Login</a>
    <?php elseif ($error): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <?php if ($tokenValid && empty($message)): ?>
        <form method="POST" action="reset_password.php">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <label for="password">New Password:</label>
            <input type="password" id="password" name="password" required minlength="8">
            
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            
            <button type="submit">Reset Password</button>
        </form>
    <?php elseif (empty($token) && empty($message)): ?>
        <p>Invalid reset link. Please request a new password reset.</p>
        <a href="request_reset.php">Request New Reset</a>
    <?php endif; ?>
</body>
</html>
?>