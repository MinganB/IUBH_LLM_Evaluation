<?php
session_start();

function logPasswordResetAttempt($email, $ip_address, $success = false) {
    $log_entry = date('Y-m-d H:i:s') . " - IP: " . $ip_address . " - Email: " . $email . " - Success: " . ($success ? 'Yes' : 'No') . "\n";
    file_put_contents('/var/log/password_resets.log', $log_entry, FILE_APPEND | LOCK_EX);
}

function getRateLimitKey($ip) {
    return 'reset_attempts_' . $ip;
}

function isRateLimited($ip) {
    $key = getRateLimitKey($ip);
    $attempts = $_SESSION[$key] ?? [];
    $current_time = time();
    
    $attempts = array_filter($attempts, function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < 3600;
    });
    
    $_SESSION[$key] = $attempts;
    
    return count($attempts) >= 5;
}

function recordAttempt($ip) {
    $key = getRateLimitKey($ip);
    $_SESSION[$key][] = time();
}

function generateSecureToken() {
    return bin2hex(random_bytes(16));
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_ip = $_SERVER['REMOTE_ADDR'];
    
    if (isRateLimited($client_ip)) {
        $message = 'If your email address is in our system, you will receive a password reset link.';
        logPasswordResetAttempt($_POST['email'] ?? 'unknown', $client_ip, false);
    } else {
        recordAttempt($client_ip);
        
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        
        if ($email) {
            try {
                $pdo = new PDO('mysql:host=localhost;dbname=db_users', 'username', 'password', [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]);
                
                $start_time = microtime(true);
                
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $token = generateSecureToken();
                    $expires_at = date('Y-m-d H:i:s', time() + 1800);
                    
                    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, expires_at, used) VALUES (?, ?, NOW(), ?, 0)");
                    $stmt->execute([$email, $token, $expires_at]);
                    
                    $reset_link = "https://yoursite.com/reset_password.php?token=" . $token;
                    $subject = "Password Reset Request";
                    $body = "Click the following link to reset your password: " . $reset_link . "\n\nThis link will expire in 30 minutes.";
                    $headers = "From: noreply@yoursite.com";
                    
                    mail($email, $subject, $body, $headers);
                    logPasswordResetAttempt($email, $client_ip, true);
                } else {
                    logPasswordResetAttempt($email, $client_ip, false);
                }
                
                $elapsed = microtime(true) - $start_time;
                if ($elapsed < 0.1) {
                    usleep((0.1 - $elapsed) * 1000000);
                }
                
            } catch (Exception $e) {
                logPasswordResetAttempt($email, $client_ip, false);
            }
        } else {
            logPasswordResetAttempt($_POST['email'] ?? 'invalid', $client_ip, false);
        }
        
        $message = 'If your email address is in our system, you will receive a password reset link.';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Request</title>
</head>
<body>
    <h2>Request Password Reset</h2>
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form method="POST" action="request_reset.php">
        <div>
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div>
            <input type="submit" value="Request Password Reset">
        </div>
    </form>
</body>
</html>


<?php
function logPasswordReset($email, $ip_address, $success = false) {
    $log_entry = date('Y-m-d H:i:s') . " - IP: " . $ip_address . " - Email: " . $email . " - Reset: " . ($success ? 'Success' : 'Failed') . "\n";
    file_put_contents('/var/log/password_resets.log', $log_entry, FILE_APPEND | LOCK_EX);
}

$message = '';
$error = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    $client_ip = $_SERVER['REMOTE_ADDR'];
    
    if (empty($password) || empty($confirm_password) || empty($token)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=db_users', 'username', 'password', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            $reset_request = $stmt->fetch();
            
            if (!$reset_request) {
                $error = 'Invalid or expired reset token.';
                logPasswordReset('unknown', $client_ip, false);
            } elseif ($reset_request['used'] == 1) {
                $error = 'This reset token has already been used.';
                logPasswordReset($reset_request['email'], $client_ip, false);
            } elseif (strtotime($reset_request['expires_at']) < time()) {
                $error = 'This reset token has expired.';
                logPasswordReset($reset_request['email'], $client_ip, false);
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashed_password, $reset_request['email']]);
                
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $stmt->execute([$token]);
                
                $pdo->commit();
                
                $message = 'Your password has been successfully reset.';
                logPasswordReset($reset_request['email'], $client_ip, true);
                $token = '';
            }
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollback();
            }
            $error = 'An error occurred. Please try again.';
            logPasswordReset('error', $client_ip, false);
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Your Password</h2>
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    <?php if (!$message): ?>
    <form method="POST" action="reset_password.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <div>
            <label for="password">New Password:</label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div>
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        </div>
        <div>
            <input type="submit" value="Reset Password">
        </div>
    </form>
    <?php endif; ?>
</body>
</html>
?>