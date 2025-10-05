<?php
// request_reset.php

$dbHost = 'localhost';
$dbName = 'db_users';
$dbUser = 'your_db_user';
$dbPass = 'your_db_password';
$emailSender = 'no-reply@yourdomain.com'; // Replace with your sender email
$resetLinkBase = 'http://localhost/reset_password.php'; // Replace with your actual domain

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $message = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);

            if ($stmt->rowCount() > 0) {
                $token = bin2hex(random_bytes(16)); // 32-character hex token

                // Store token in password_resets table
                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, is_used) VALUES (:email, :token, NOW(), 0)");
                $stmt->execute([
                    ':email' => $email,
                    ':token' => $token
                ]);

                // Send email
                $resetLink = $resetLinkBase . '?token=' . $token;
                $subject = 'Password Reset Request';
                $emailBody = "You have requested a password reset. Please click on the following link to reset your password:\n\n";
                $emailBody .= $resetLink . "\n\n";
                $emailBody .= "This link will expire in 1 hour. If you did not request a password reset, please ignore this email.";

                $headers = "From: " . $emailSender . "\r\n";
                $headers .= "Reply-To: " . $emailSender . "\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (mail($email, $subject, $emailBody, $headers)) {
                    $message = 'If an account with that email exists, a password reset link has been sent.';
                } else {
                    $message = 'Error sending email. Please try again later.';
                }
            } else {
                // For security, always show a generic message whether email exists or not
                $message = 'If an account with that email exists, a password reset link has been sent.';
            }
        } catch (PDOException $e) {
            $message = 'Database error. Please try again later.';
            // In a production environment, you would log $e->getMessage()
        }
    }
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
    <h2>Request Password Reset</h2>
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form action="request_reset.php" method="POST">
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        <button type="submit">Send Reset Link</button>
    </form>
</body>
</html>

<?php
// reset_password.php

$dbHost = 'localhost';
$dbName = 'db_users';
$dbUser = 'your_db_user';
$dbPass = 'your_db_password';

$message = '';
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$displayForm = false;

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    if (empty($token)) {
        $message = 'Invalid or missing password reset token.';
    } else {
        // Check if token is valid, not used, and not expired (e.g., within 1 hour)
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND is_used = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute([':token' => $token]);
        $resetRequest = $stmt->fetch();

        if (!$resetRequest) {
            $message = 'The password reset token is invalid, expired, or has already been used.';
        } else {
            $displayForm = true; // Token is valid, show the form

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $password = $_POST['password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (empty($password) || empty($confirmPassword)) {
                    $message = 'Password and confirm password fields cannot be empty.';
                } elseif ($password !== $confirmPassword) {
                    $message = 'Passwords do not match.';
                } elseif (strlen($password) < 8) { // Minimum password length
                    $message = 'Password must be at least 8 characters long.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $userEmail = $resetRequest['email'];

                    // Update user's password
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
                    $stmt->execute([
                        ':password' => $hashedPassword,
                        ':email' => $userEmail
                    ]);

                    // Mark token as used
                    $stmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE token = :token");
                    $stmt->execute([':token' => $token]);

                    $pdo->commit();

                    $message = 'Your password has been reset successfully. You can now log in with your new password.';
                    $displayForm = false; // Hide form after successful reset
                }
            }
        }
    }
} catch (PDOException $e) {
    $message = 'Database error. Please try again later.';
    // In a production environment, you would log $e->getMessage()
    $displayForm = false; // Do not display form on DB error
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
    <h2>Reset Password</h2>
    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($displayForm): ?>
        <form action="reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <label for="password">New Password:</label><br>
            <input type="password" id="password" name="password" required><br><br>
            <label for="confirm_password">Confirm New Password:</label><br>
            <input type="password" id="confirm_password" name="confirm_password" required><br><br>
            <button type="submit">Reset Password</button>
        </form>
    <?php endif; ?>
</body>
</html>
?>