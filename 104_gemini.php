<?php

// request_reset.php

// Database configuration
$db_host = 'localhost';
$db_name = 'db_users';
$db_user = 'root';
$db_pass = '';

// SMTP configuration (for mail() function)
// Make sure your PHP configuration allows sending emails, e.g., sendmail is configured.
// For production, consider using a robust mail library like PHPMailer or a transactional email service.
$reset_link_base = 'http://localhost/reset_password.php'; // Adjust to your actual domain and path

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        // Generic message to prevent email enumeration
        echo 'If an account with that email address exists, a password reset link has been sent.';
    } else {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Check if email exists in users table
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate a unique 32-character token
                $token = bin2hex(random_bytes(16));
                $created_at = date('Y-m-d H:i:s');
                // Token expires in 1 hour
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store token in password_resets table
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, expires_at, used) VALUES (:email, :token, :created_at, :expires_at, 0)");
                $stmt->execute([
                    'email' => $email,
                    'token' => $token,
                    'created_at' => $created_at,
                    'expires_at' => $expires_at
                ]);

                // Send email to user
                $reset_link = $reset_link_base . '?token=' . $token;
                $subject = 'Password Reset Request';
                $message = "Dear User,\n\nYou have requested a password reset. Please click on the following link to reset your password:\n\n" . $reset_link . "\n\nThis link will expire in 1 hour.\n\nIf you did not request this, please ignore this email.\n\n";
                $headers = 'From: no-reply@yourdomain.com' . "\r\n" .
                           'Reply-To: no-reply@yourdomain.com' . "\r\n" .
                           'X-Mailer: PHP/' . phpversion();

                mail($email, $subject, $message, $headers);

                echo 'If an account with that email address exists, a password reset link has been sent.';
            } else {
                // Still give the generic message for security
                echo 'If an account with that email address exists, a password reset link has been sent.';
            }
        } catch (PDOException $e) {
            // Log error, do not expose internal error messages to the user
            error_log('Password reset request error: ' . $e->getMessage());
            echo 'An unexpected error occurred. Please try again later.';
        }
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Password Reset</title>
</head>
<body>
    <h1>Request Password Reset</h1>
    <form action="request_reset.php" method="POST">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <button type="submit">Send Reset Link</button>
    </form>
</body>
</html>
<?php

// reset_password.php

// Database configuration
$db_host = 'localhost';
$db_name = 'db_users';
$db_user = 'root';
$db_pass = '';

$message = '';
$token_from_url = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
    $password = filter_input(INPUT_POST, 'password');
    $confirm_password = filter_input(INPUT_POST, 'confirm_password');

    if (empty($token) || empty($password) || empty($confirm_password)) {
        $message = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) { // Basic password length requirement
        $message = 'Password must be at least 8 characters long.';
    } else {
        try {
            $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Validate token: check if it exists, is not expired, and has not been used
            $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND expires_at > NOW() AND used = 0");
            $stmt->execute(['token' => $token]);
            $reset_record = $stmt->fetch();

            if ($reset_record) {
                $user_email = $reset_record['email'];
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Update user's password
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
                $stmt->execute([
                    'password' => $hashed_password,
                    'email' => $user_email
                ]);

                // Mark the token as used
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
                $stmt->execute(['token' => $token]);

                $pdo->commit();

                $message = 'Your password has been reset successfully. You can now log in.';
                // Optionally redirect to login page
                // header('Location: /login.php');
                // exit;
            } else {
                $message = 'Invalid or expired password reset token.';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Password reset error: ' . $e->getMessage());
            $message = 'An unexpected error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <h1>Reset Your Password</h1>
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if (!empty($token_from_url) && strpos($message, 'success') === false && strpos($message, 'Invalid or expired') === false): ?>
    <form action="reset_password.php" method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_from_url); ?>">

        <label for="password">New Password:</label>
        <input type="password" id="password" name="password" required minlength="8">
        
        <label for="confirm_password">Confirm New Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        
        <button type="submit">Reset Password</button>
    </form>
    <?php elseif (empty($token_from_url) && empty($message)): ?>
        <p>No reset token provided. Please use the link from your email.</p>
    <?php endif; ?>
</body>
</html>
?>