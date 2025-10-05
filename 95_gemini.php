<?php

// --- Configuration (Common to both scripts) ---
// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce_db');

// Token expiration time in seconds (e.g., 3600 seconds = 1 hour)
define('RESET_TOKEN_EXPIRATION_SECONDS', 3600); 

// Base URL of your website, used to construct the reset link
// Make sure this matches your actual setup (e.g., 'http://localhost/my_ecommerce' or 'https://www.yourdomain.com')
define('WEBSITE_BASE_URL', 'http://localhost/password_reset_module'); 

// Email sender details
define('SENDER_EMAIL', 'no-reply@yourdomain.com');
define('SENDER_NAME', 'Your E-commerce');

// --- Database Connection (Common to both scripts) ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // In a production environment, log the detailed error and show a generic message to the user.
    die("Database connection failed. Please try again later.");
}

// --- Session Start (Common to both scripts for CSRF protection and messages) ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Helper Functions (Common to both scripts) ---

/**
 * Generates a CSRF token and stores it in the session if one doesn't exist.
 *
 * @return string The CSRF token.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifies a submitted CSRF token against the one stored in the session.
 *
 * @param string $token The token submitted by the form.
 * @return bool True if the token is valid, false otherwise.
 */
function verify_csrf_token($token) {
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

/**
 * Sends an email using PHP's built-in mail() function.
 * For production, consider using a robust library like PHPMailer or Symfony Mailer
 * for better reliability, SMTP support, and error handling.
 *
 * @param string $to The recipient's email address.
 * @param string $subject The subject of the email.
 * @param string $message The HTML content of the email.
 * @return bool True on success, false on failure.
 */
function send_email($to, $subject, $message) {
    $headers = "From: " . SENDER_NAME . " <" . SENDER_EMAIL . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // mail() function might not be configured on all servers or might fail silently.
    // Ensure your server's mail configuration is correct for this to work.
    return mail($to, $subject, $message, $headers);
}

?>
<?php
// --- Script 1: request_reset.php ---

// Initialize message variable for user feedback
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF token for security
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '<p style="color:red;">Invalid request. Please try again.</p>';
    } else {
        // 2. Sanitize and validate email input
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<p style="color:red;">Please enter a valid email address.</p>';
        } else {
            // 3. Check if the email exists in the database
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                $userId = $user['id'];
                
                // 4. Generate a unique, cryptographically secure token
                $token = bin2hex(random_bytes(32)); 
                $tokenHash = hash('sha256', $token); // Hash the token for secure storage
                $expiresAt = date('Y-m-d H:i:s', time() + RESET_TOKEN_EXPIRATION_SECONDS);

                // 5. Delete any existing password reset tokens for this user
                //    This ensures only one valid token exists at a time, preventing token re-use issues.
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);

                // 6. Store the hashed token and its expiration time in the database
                $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, created_at) VALUES (:user_id, :token_hash, :expires_at, NOW())");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':token_hash' => $tokenHash,
                    ':expires_at' => $expiresAt
                ]);

                // 7. Construct the password reset link
                $resetLink = WEBSITE_BASE_URL . '/reset_password.php?token=' . urlencode($token);
                
                // 8. Prepare and send the email
                $subject = "Password Reset Request for " . SENDER_NAME;
                $emailMessage = "<p>Dear user,</p>";
                $emailMessage .= "<p>You have requested to reset your password for your account with " . SENDER_NAME . ".</p>";
                $emailMessage .= "<p>Please click the following link to reset your password: <a href=\"{$resetLink}\">{$resetLink}</a></p>";
                $emailMessage .= "<p>This link will expire in " . (RESET_TOKEN_EXPIRATION_SECONDS / 60) . " minutes.</p>";
                $emailMessage .= "<p>If you did not request a password reset, please ignore this email.</p>";
                $emailMessage .= "<p>Regards,<br>" . SENDER_NAME . " Team</p>";

                if (send_email($email, $subject, $emailMessage)) {
                    // Provide a generic message to prevent email enumeration attacks
                    $message = '<p style="color:green;">If an account with that email address exists, a password reset link has been sent.</p>';
                } else {
                    $message = '<p style="color:red;">Error sending email. Please try again later.</p>';
                }
            } else {
                // 9. If email not found, still provide a generic message for security
                $message = '<p style="color:green;">If an account with that email address exists, a password reset link has been sent.</p>';
            }
        }
    }
}

// Generate a new CSRF token for the form (or reuse existing one)
$csrfToken = generate_csrf_token();
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
    <?php echo $message; ?>
    <form action="request_reset.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <label for="email">Email Address:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        <button type="submit">Send Reset Link</button>
    </form>
</body>
</html>
<?php
// --- Script 2: reset_password.php ---

// Initialize message variables
$message = '';
$token = $_GET['token'] ?? ''; // Get token from URL query parameter
$isValidToken = false; // Flag to control form display
$userId = null; // To store the user ID associated with the token

// 1. Validate the presence of a token
if (empty($token)) {
    $message = '<p style="color:red;">No password reset token provided.</p>';
} else {
    // 2. Hash the token received from the URL for comparison with the stored hash
    $tokenHashFromUrl = hash('sha256', $token);

    // 3. Look up the token in the database
    $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_resets WHERE token_hash = :token_hash");
    $stmt->execute([':token_hash' => $tokenHashFromUrl]);
    $resetRequest = $stmt->fetch();

    if (!$resetRequest) {
        $message = '<p style="color:red;">Invalid or already used password reset token.</p>';
    } else {
        // 4. Check if the token has expired
        $expiresAt = strtotime($resetRequest['expires_at']);
        if (time() > $expiresAt) {
            $message = '<p style="color:red;">Your password reset token has expired. Please request a new one.</p>';
            // Optionally, delete the expired token from the database
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token_hash = :token_hash");
            $stmt->execute([':token_hash' => $tokenHashFromUrl]);
        } else {
            // Token is valid and not expired
            $isValidToken = true;
            $userId = $resetRequest['user_id'];
        }
    }
}

// Handle form submission for new password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidToken) {
    // 1. Verify CSRF token for security
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = '<p style="color:red;">Invalid request. Please try again.</p>';
        $isValidToken = false; // If CSRF fails, treat token as invalid for safety
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // 2. Validate new password
        if (empty($newPassword) || empty($confirmPassword)) {
            $message = '<p style="color:red;">Both password fields are required.</p>';
        } elseif (strlen($newPassword) < 8) {
            $message = '<p style="color:red;">New password must be at least 8 characters long.</p>';
        } elseif ($newPassword !== $confirmPassword) {
            $message = '<p style="color:red;">Passwords do not match.</p>';
        } else {
            // 3. Hash the new password securely
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                // 4. Update the user's password in the database
                $stmt = $pdo->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
                $stmt->execute([':password' => $hashedPassword, ':id' => $userId]);

                // 5. Invalidate the password reset token by deleting it from the database
                //    This prevents the token from being used again.
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);

                $pdo->commit();
                
                // Set a session message for the redirect target
                $_SESSION['reset_success'] = '<p style="color:green;">Your password has been reset successfully. You can now log in with your new password.</p>';
                
                // Redirect to the login page or a success page
                header('Location: ' . WEBSITE_BASE_URL . '/login.php'); // Replace '/login.php' with your actual login page path
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                // In a production environment, log the detailed error.
                $message = '<p style="color:red;">An error occurred while updating your password. Please try again.</p>';
            }
        }
    }
}

// Generate a new CSRF token for the form (or reuse existing one)
$csrfToken = generate_csrf_token();
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
    <?php 
    // Display success message from session if redirected here
    if (isset($_SESSION['reset_success'])) {
        echo $_SESSION['reset_success'];
        unset($_SESSION['reset_success']); // Clear the message after displaying
    }
    echo $message; 
    ?>

    <?php if ($isValidToken): ?>
    <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <label for="new_password">New Password:</label><br>
        <input type="password" id="new_password" name="new_password" required minlength="8"><br><br>
        <label for="confirm_password">Confirm New Password:</label><br>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8"><br><br>
        <button type="submit">Set New Password</button>
    </form>
    <?php else: ?>
    <p>Please return to the <a href="request_reset.php">password reset request page</a> to request a new link.</p>
    <?php endif; ?>
</body>
</html>
?>