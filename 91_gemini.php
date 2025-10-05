<?php

// request_reset.php

// Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('SITE_URL', 'http://localhost/your_project_folder'); // Change to your actual site URL and project folder
define('EMAIL_FROM', 'no-reply@yourdomain.com'); // Email sender address
define('TOKEN_EXPIRATION_SECONDS', 3600); // 1 hour token expiration

// Database connection using PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        // Find the user by email
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        // Security measure: provide generic message to prevent email enumeration
        $message = "If an account with that email address exists, a password reset link has been sent.";

        if ($user) {
            $userId = $user['id'];
            $token = bin2hex(random_bytes(32)); // Generate a secure, unique token
            $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRATION_SECONDS);

            // Invalidate any existing unused tokens for this user
            $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL");
            $stmt->execute(['user_id' => $userId]);

            // Store the new token in the database
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
            $stmt->execute(['user_id' => $userId, 'token' => $token, 'expires_at' => $expiresAt]);

            $resetLink = SITE_URL . '/reset_password.php?token=' . $token;
            $subject = 'Password Reset Request';
            $body = "Hello,\n\nYou have requested to reset your password. Please click on the following link to reset your password:\n\n" . $resetLink . "\n\nThis link will expire in " . (TOKEN_EXPIRATION_SECONDS / 60) . " minutes.\n\nIf you did not request a password reset, please ignore this email.\n\nThanks,\nYour Application Team";
            $headers = "From: " . EMAIL_FROM . "\r\n" .
                       "Reply-To: " . EMAIL_FROM . "\r\n" .
                       "X-Mailer: PHP/" . phpversion();

            // Send email
            // In a production environment, consider using a dedicated email library (e.g., PHPMailer)
            // and an SMTP server for better reliability and delivery rates.
            if (!mail($user['email'], $subject, $body, $headers)) {
                // Log email sending error in a real production environment
                // For demonstration, we still show the generic message
            }
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
    <form action="request_reset.php" method="post">
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

// Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('SITE_URL', 'http://localhost/your_project_folder'); // Change to your actual site URL and project folder
define('PASSWORD_MIN_LENGTH', 8);

// Database connection using PDO
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

session_start(); // Start session for CSRF token

$message = '';
$token = $_GET['token'] ?? '';
$isValidToken = false;
$userId = null;
$showPasswordForm = false;

// Check token validity
if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = :token AND used_at IS NULL");
    $stmt->execute(['token' => $token]);
    $resetData = $stmt->fetch();

    if ($resetData) {
        if (strtotime($resetData['expires_at']) > time()) {
            $isValidToken = true;
            $userId = $resetData['user_id'];
            $showPasswordForm = true; // Show the password input form
        } else {
            $message = "The password reset link has expired.";
        }
    } else {
        $message = "Invalid or already used password reset token.";
    }
} else {
    $message = "No password reset token provided.";
}

// Handle password reset form submission
if ($isValidToken && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "Invalid request (CSRF token mismatch). Please try again.";
        $isValidToken = false;
        $showPasswordForm = false; // Hide form after security error
    } else {
        $newPassword = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($newPassword) || empty($confirmPassword)) {
            $message = "Please enter and confirm your new password.";
        } elseif (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            $message = "Your password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "Passwords do not match.";
        } else {
            // Hash the new password securely
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                // Update user's password in the users table
                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :user_id");
                $stmt->execute(['password' => $hashedPassword, 'user_id' => $userId]);

                // Mark the token as used to prevent reuse
                $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = :token");
                $stmt->execute(['token' => $token]);

                $pdo->commit();
                $message = "Your password has been reset successfully. You can now log in.";
                $showPasswordForm = false; // Hide form after successful reset

                // Optional: Redirect to a login page
                // header('Location: ' . SITE_URL . '/login.php');
                // exit();

            } catch (PDOException $e) {
                $pdo->rollBack();
                $message = "An error occurred while resetting your password. Please try again.";
                // Log the error in a real production environment
            }
        }
    }
}

// Generate new CSRF token for the form
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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

    <?php if ($showPasswordForm): ?>
        <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div>
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
            </div>
            <div>
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
            </div>
            <div>
                <button type="submit">Reset Password</button>
            </div>
        </form>
    <?php endif; ?>
</body>
</html>
?>