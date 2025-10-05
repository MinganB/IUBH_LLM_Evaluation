<?php

// request_reset.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Email configuration (replace with your actual SMTP details or ensure mail() is configured)
// Using PHPMailer or a similar library is recommended for production,
// but for simplicity and adherence to basic PHP mail, mail() is used here.
define('MAIL_FROM_EMAIL', 'no-reply@yourdomain.com');
define('MAIL_FROM_NAME', 'Password Reset Service');
define('RESET_LINK_BASE', 'http://localhost/reset_password.php'); // Adjust if your script is in a subdirectory

$message = '';
$error = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database connection failed: " . $e->getMessage();
    // In a production environment, log this error and show a generic message to the user.
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($pdo)) {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address format.';
    } else {
        try {
            // Check if email exists in users table
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate a unique token
                $token = bin2hex(random_bytes(16)); // 32-character token

                // Store token in password_resets table
                // Delete any old tokens for this email first (optional, but good for cleanup)
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email AND used = 0");
                $stmt->execute(['email' => $email]);

                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, used) VALUES (:email, :token, NOW(), 0)");
                $stmt->execute([
                    'email' => $email,
                    'token' => $token
                ]);

                // Send email
                $resetLink = RESET_LINK_BASE . '?token=' . $token;
                $subject = "Password Reset Request";
                $emailBody = "Hello,\n\nYou have requested to reset your password. Please click on the link below to reset it:\n\n" . $resetLink . "\n\nThis link will expire in 1 hour.\n\nIf you did not request a password reset, please ignore this email.\n\nThank you,\n" . MAIL_FROM_NAME;
                $headers = 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . ">\r\n" .
                           'Reply-To: ' . MAIL_FROM_EMAIL . "\r\n" .
                           'X-Mailer: PHP/' . phpversion();

                // It's recommended to use a robust mail library like PHPMailer for production
                // if you're not relying on a configured sendmail/postfix.
                if (mail($email, $subject, $emailBody, $headers)) {
                    $message = 'If an account with that email address exists, a password reset link has been sent.';
                } else {
                    $error = 'Failed to send the password reset email. Please try again later.';
                    // Log the mail error for debugging
                }
            } else {
                // To prevent email enumeration, always show the same message
                $message = 'If an account with that email address exists, a password reset link has been sent.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred during the password reset request. Please try again later.';
            // Log the error for debugging
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
    <h1>Request Password Reset</h1>

    <?php if ($message): ?>
        <p style="color: green;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="request_reset.php" method="POST">
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

<?php

// reset_password.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

$message = '';
$error = '';
$token = '';
$showForm = false;
$tokenValid = false;
$userEmail = '';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database connection failed: " . $e->getMessage();
}

if (isset($pdo)) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
        $token = trim($_GET['token']);
        
        if (empty($token)) {
            $error = 'Invalid reset token provided.';
        } else {
            try {
                // Check if token is valid, not used, and not expired (e.g., 1 hour expiration)
                $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND used = 0 AND created_at > (NOW() - INTERVAL 1 HOUR)");
                $stmt->execute(['token' => $token]);
                $resetRequest = $stmt->fetch();

                if ($resetRequest) {
                    $tokenValid = true;
                    $showForm = true;
                    $userEmail = $resetRequest['email'];
                } else {
                    $error = 'Invalid or expired password reset token.';
                }
            } catch (PDOException $e) {
                $error = 'An error occurred while validating the token. Please try again.';
            }
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($token)) {
            $error = 'Reset token is missing.';
        } elseif (empty($password) || empty($confirmPassword)) {
            $error = 'Both password fields are required.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 8) { // Example password policy
            $error = 'Password must be at least 8 characters long.';
        } else {
            try {
                // Re-validate token on POST to prevent race conditions or double submission
                $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND used = 0 AND created_at > (NOW() - INTERVAL 1 HOUR)");
                $stmt->execute(['token' => $token]);
                $resetRequest = $stmt->fetch();

                if ($resetRequest) {
                    $userEmail = $resetRequest['email'];
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Start transaction for atomicity
                    $pdo->beginTransaction();

                    // Update user's password
                    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
                    $stmt->execute([
                        'password' => $hashedPassword,
                        'email' => $userEmail
                    ]);

                    // Mark token as used
                    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
                    $stmt->execute(['token' => $token]);

                    $pdo->commit();

                    $message = 'Your password has been reset successfully. You can now log in with your new password.';
                    $showForm = false; // Hide form after successful reset
                } else {
                    $error = 'Invalid or expired password reset token.';
                }
            } catch (PDOException $e) {
                $pdo->rollBack(); // Rollback on error
                $error = 'An error occurred during password reset. Please try again.';
                // Log the error for debugging
            }
        }
        // If there was an error after POST, still show the form with the token if it was initially valid
        if ($error && !empty($token)) {
            $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = :token AND used = 0 AND created_at > (NOW() - INTERVAL 1 HOUR)");
            $stmt->execute(['token' => $token]);
            if ($stmt->fetch()) {
                $showForm = true;
                $tokenValid = true; // Retain form display if token still valid
            }
        }
    } else {
        $error = 'No reset token provided or invalid request.';
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
        <p style="color: green;"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <?php if ($showForm && $tokenValid): ?>
        <form action="reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div>
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div>
                <button type="submit">Reset Password</button>
            </div>
        </form>
    <?php endif; ?>
</body>
</html>
?>