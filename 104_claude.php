<?php
// request_reset.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    
    if ($email) {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                $token = bin2hex(random_bytes(16));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, expires_at, used) VALUES (?, ?, NOW(), ?, 0)");
                $stmt->execute([$email, password_hash($token, PASSWORD_DEFAULT), $expires_at]);
                
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . urlencode($token);
                
                $subject = "Password Reset Request";
                $message = "Click the following link to reset your password: " . $reset_link . "\n\nThis link will expire in 1 hour.";
                $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];
                
                mail($email, $subject, $message, $headers);
                
                $success = "Password reset link has been sent to your email.";
            } else {
                $error = "Email address not found.";
            }
        } catch (PDOException $e) {
            $error = "Database error occurred.";
        }
    } else {
        $error = "Please enter a valid email address.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Request Password Reset</title>
</head>
<body>
    <h2>Request Password Reset</h2>
    
    <?php if (isset($success)): ?>
        <p><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    
    if (empty($password) || empty($confirm_password) || empty($token)) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            $stmt = $pdo->prepare("SELECT email, token FROM password_resets WHERE used = 0 AND expires_at > NOW()");
            $stmt->execute();
            $resets = $stmt->fetchAll();
            
            $valid_reset = null;
            foreach ($resets as $reset) {
                if (password_verify($token, $reset['token'])) {
                    $valid_reset = $reset;
                    break;
                }
            }
            
            if ($valid_reset) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashed_password, $valid_reset['email']]);
                
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $stmt->execute([$valid_reset['token']]);
                
                $success = "Password has been successfully reset.";
            } else {
                $error = "Invalid or expired token.";
            }
        } catch (PDOException $e) {
            $error = "Database error occurred.";
        }
    }
} else {
    $token = $_GET['token'] ?? '';
    if (empty($token)) {
        $error = "Invalid reset token.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Password</h2>
    
    <?php if (isset($success)): ?>
        <p><?php echo htmlspecialchars($success); ?></p>
        <a href="login.php">Login</a>
    <?php else: ?>
        
        <?php if (isset($error)): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        
        <form method="POST" action="reset_password.php">
            <label for="password">New Password:</label>
            <input type="password" id="password" name="password" required minlength="8">
            
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <button type="submit">Reset Password</button>
        </form>
        
    <?php endif; ?>
</body>
</html>
?>