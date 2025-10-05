<?php
// request_reset.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                $token = bin2hex(random_bytes(16));
                $timestamp = date('Y-m-d H:i:s');
                
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, used) VALUES (?, ?, ?, 0)");
                $stmt->execute([$email, $token, $timestamp]);
                
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
                
                $subject = "Password Reset Request";
                $message = "Click the following link to reset your password: " . $reset_link;
                $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];
                
                mail($email, $subject, $message, $headers);
                
                $success = 'Password reset link has been sent to your email.';
            } else {
                $error = 'Email address not found.';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred. Please try again.';
        }
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
    
    <?php if (isset($success)): ?>
        <p><?php echo htmlspecialchars($success); ?></p>
    <?php elseif (isset($error)): ?>
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
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT email, created_at, used FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            $reset_request = $stmt->fetch();
            
            if (!$reset_request) {
                $error = 'Invalid or expired reset token.';
            } elseif ($reset_request['used']) {
                $error = 'This reset token has already been used.';
            } elseif (strtotime($reset_request['created_at']) < strtotime('-1 hour')) {
                $error = 'Reset token has expired.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashed_password, $reset_request['email']]);
                
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $stmt->execute([$token]);
                
                $pdo->commit();
                
                $success = 'Your password has been successfully reset.';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Database error occurred. Please try again.';
        }
    }
} else {
    $token = $_GET['token'] ?? '';
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Set New Password</h2>
    
    <?php if (isset($success)): ?>
        <p><?php echo htmlspecialchars($success); ?></p>
        <a href="login.php">Login with your new password</a>
    <?php elseif (isset($error)): ?>
        <p><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>
    
    <?php if (!isset($success)): ?>
        <form method="POST" action="reset_password.php">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <label for="password">New Password:</label>
            <input type="password" id="password" name="password" required minlength="8">
            
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            
            <button type="submit">Reset Password</button>
        </form>
    <?php endif; ?>
</body>
</html>
?>