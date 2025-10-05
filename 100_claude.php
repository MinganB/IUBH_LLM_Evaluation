<?php
// request_reset.php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    
    try {
        $pdo = new PDO("mysql:host=localhost;dbname=db_users", DB_USERNAME, DB_PASSWORD);
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
            
            echo "<p>Password reset link has been sent to your email.</p>";
        } else {
            echo "<p>Email not found.</p>";
        }
    } catch (PDOException $e) {
        echo "<p>Database error occurred.</p>";
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
    <form method="POST" action="request_reset.php">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <input type="submit" value="Send Reset Link">
    </form>
</body>
</html>


<?php
// reset_password.php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    if ($password !== $confirm_password) {
        echo "<p>Passwords do not match.</p>";
    } else {
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=db_users", DB_USERNAME, DB_PASSWORD);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND used = 0");
            $stmt->execute([$token]);
            $reset_request = $stmt->fetch();
            
            if ($reset_request) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmt->execute([$hashed_password, $reset_request['email']]);
                
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $stmt->execute([$token]);
                
                echo "<p>Password has been reset successfully.</p>";
            } else {
                echo "<p>Invalid or expired token.</p>";
            }
        } catch (PDOException $e) {
            echo "<p>Database error occurred.</p>";
        }
    }
}

$token = isset($_GET['token']) ? $_GET['token'] : '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <h2>Reset Password</h2>
    <form method="POST" action="reset_password.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <label for="password">New Password:</label>
        <input type="password" id="password" name="password" required>
        <br><br>
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <br><br>
        <input type="submit" value="Reset Password">
    </form>
</body>
</html>


<?php
// config.php
define('DB_USERNAME', 'your_username');
define('DB_PASSWORD', 'your_password');
?>