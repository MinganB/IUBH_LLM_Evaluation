<?php
// config.php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Email configuration
define('EMAIL_FROM', 'no-reply@yourdomain.com');
define('EMAIL_SUBJECT', 'Password Reset Request');
// IMPORTANT: Adjust this to your production domain and secure protocol (HTTPS)
define('PASSWORD_RESET_BASE_URL', 'http://localhost/reset_password.php'); 

// Token configuration
define('TOKEN_EXPIRATION_MINUTES', 30);
define('TOKEN_LENGTH_BYTES', 64); // Generates a 128-character hex token

// Logging configuration
// IMPORTANT: In production, ensure this log file is outside the web root and secured.
define('LOG_FILE_PATH', __DIR__ . '/password_reset.log');

// Rate Limiting configuration (per IP address for the request_reset.php endpoint)
define('RATE_LIMIT_INTERVAL_SECONDS', 3600); // 1 hour
define('RATE_LIMIT_MAX_REQUESTS', 5); // Max 5 requests per IP per hour
// IMPORTANT: For production, consider a more robust storage (e.g., Redis, database)
// This file-based storage is susceptible to race conditions under high load without robust locking.
define('RATE_LIMIT_STORAGE_FILE', __DIR__ . '/rate_limits.json');
?>
<?php
// request_reset.php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("An unexpected error occurred. Please try again later.");
}

function checkRateLimit($ip) {
    $limits = [];
    $fileHandle = @fopen(RATE_LIMIT_STORAGE_FILE, 'c+'); // Open for reading and writing, create if not exists
    if (!$fileHandle) {
        error_log("Failed to open or create rate limit file: " . RATE_LIMIT_STORAGE_FILE);
        return true; // Fail safe: don't rate limit if storage fails
    }

    flock($fileHandle, LOCK_EX); // Acquire an exclusive lock

    $fileContent = stream_get_contents($fileHandle);
    if (!empty($fileContent)) {
        $limits = json_decode($fileContent, true) ?? [];
    }

    $currentTime = time();
    $requests = $limits[$ip] ?? [];

    $requests = array_filter($requests, function($timestamp) use ($currentTime) {
        return ($currentTime - $timestamp) < RATE_LIMIT_INTERVAL_SECONDS;
    });

    if (count($requests) >= RATE_LIMIT_MAX_REQUESTS) {
        flock($fileHandle, LOCK_UN); // Release the lock
        fclose($fileHandle);
        return false; // Rate limited
    }

    $requests[] = $currentTime;
    $limits[$ip] = $requests;

    ftruncate($fileHandle, 0); // Truncate the file
    rewind($fileHandle); // Rewind to the beginning
    fwrite($fileHandle, json_encode($limits)); // Write updated data

    flock($fileHandle, LOCK_UN); // Release the lock
    fclose($fileHandle);
    return true; // Request allowed
}

function logPasswordResetRequest($email, $ip, $status) {
    $logMessage = sprintf(
        "[%s] IP: %s, Email: %s, Status: %s%s",
        date('Y-m-d H:i:s'),
        $ip,
        $email,
        $status,
        PHP_EOL
    );
    file_put_contents(LOG_FILE_PATH, $logMessage, FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $userIp = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    logPasswordResetRequest($email, $userIp, 'Attempt');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        logPasswordResetRequest($email, $userIp, 'Invalid Email Format');
        echo "If an account with that email exists, a password reset link has been sent.";
        exit;
    }

    if (!checkRateLimit($userIp)) {
        logPasswordResetRequest($email, $userIp, 'Rate Limited');
        echo "Too many password reset requests from your IP address. Please try again later.";
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    $foundUser = false;
    $userId = null;
    if ($user) {
        $foundUser = true;
        $userId = $user['id'];
    }

    if ($foundUser) {
        try {
            $token = bin2hex(random_bytes(TOKEN_LENGTH_BYTES));
        } catch (Exception $e) {
            error_log("CSPRNG error: " . $e->getMessage());
            echo "An unexpected error occurred. Please try again later.";
            logPasswordResetRequest($email, $userIp, 'Token Generation Failed');
            exit;
        }

        $expiresAt = date('Y-m-d H:i:s', time() + (TOKEN_EXPIRATION_MINUTES * 60));

        try {
            // Delete any existing unused tokens for this user
            $stmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE user_id = :user_id AND is_used = 0 AND expires_at > NOW()");
            $stmt->execute([':user_id' => $userId]);

            // Store the new token in the database
            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
            $stmt->execute([
                ':user_id' => $userId,
                ':token' => $token,
                ':expires_at' => $expiresAt
            ]);

            $resetLink = PASSWORD_RESET_BASE_URL . "?token=" . $token;
            $message = "Click the following link to reset your password: " . $resetLink;
            $headers = 'From: ' . EMAIL_FROM . "\r\n" .
                       'Reply-To: ' . EMAIL_FROM . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();

            // In production, consider a robust email sending library (e.g., PHPMailer)
            // and a transactional email service (e.g., SendGrid, Mailgun)
            $mailSent = mail($email, EMAIL_SUBJECT, $message, $headers);

            if ($mailSent) {
                logPasswordResetRequest($email, $userIp, 'Email Sent');
            } else {
                logPasswordResetRequest($email, $userIp, 'Email Send Failed');
                error_log("Failed to send password reset email to " . $email);
            }
        } catch (PDOException $e) {
            error_log("Database error during token storage or update: " . $e->getMessage());
            logPasswordResetRequest($email, $userIp, 'Database Error during Token Storage');
        }
    } else {
        logPasswordResetRequest($email, $userIp, 'Email Not Found');
    }

    echo "If an account with that email exists, a password reset link has been sent.";
    exit;
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
    <h1>Request Password Reset</h1>
    <form action="request_reset.php" method="POST">
        <label for="email">Enter your email address:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        <button type="submit">Send Reset Link</button>
    </form>
</body>
</html>
<?php
// reset_password.php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("An unexpected error occurred. Please try again later.");
}

$token = $_GET['token'] ?? null;
$message = '';
$showForm = false;

if ($token) {
    $stmt = $pdo->prepare("SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, pr.is_used, u.email
                           FROM password_resets pr
                           JOIN users u ON pr.user_id = u.id
                           WHERE pr.token = :token");
    $stmt->execute([':token' => $token]);
    $resetData = $stmt->fetch();

    if ($resetData) {
        $currentTime = new DateTime();
        $expirationTime = new DateTime($resetData['expires_at']);

        if ($resetData['is_used']) {
            $message = "This password reset link has already been used.";
        } elseif ($currentTime > $expirationTime) {
            $message = "This password reset link has expired.";
            $updateStmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE id = :reset_id");
            $updateStmt->execute([':reset_id' => $resetData['reset_id']]);
        } else {
            $showForm = true;
        }
    } else {
        $message = "Invalid password reset token.";
    }
} else {
    $message = "Password reset token is missing.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password']) && isset($_POST['confirm_password']) && isset($_POST['token'])) {
    if ($_POST['token'] !== $token) {
        $message = "Security error: Token mismatch.";
        $showForm = false;
    } else {
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];

        if (empty($newPassword) || empty($confirmPassword)) {
            $message = "Please enter and confirm your new password.";
        } elseif ($newPassword !== $confirmPassword) {
            $message = "Passwords do not match.";
        } elseif (strlen($newPassword) < 8) {
            $message = "Password must be at least 8 characters long.";
        } else {
            $stmt = $pdo->prepare("SELECT pr.id AS reset_id, pr.user_id, pr.expires_at, pr.is_used
                                   FROM password_resets pr
                                   WHERE pr.token = :token AND pr.is_used = 0 AND pr.expires_at > NOW()");
            $stmt->execute([':token' => $token]);
            $revalidatedResetData = $stmt->fetch();

            if ($revalidatedResetData) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                try {
                    $pdo->beginTransaction();

                    $updateUserStmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :user_id");
                    $updateUserStmt->execute([
                        ':password' => $hashedPassword,
                        ':user_id' => $revalidatedResetData['user_id']
                    ]);

                    $updateTokenStmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE id = :reset_id");
                    $updateTokenStmt->execute([':reset_id' => $revalidatedResetData['reset_id']]);

                    $pdo->commit();

                    $message = "Your password has been reset successfully. You can now log in with your new password.";
                    $showForm = false;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Password reset database error: " . $e->getMessage());
                    $message = "An error occurred while resetting your password. Please try again.";
                }
            } else {
                $message = "Invalid or expired password reset token. Please request a new one.";
                $showForm = false;
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
    <title>Reset Password</title>
</head>
<body>
    <h1>Reset Your Password</h1>

    <?php if ($message): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <?php if ($showForm): ?>
        <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <label for="new_password">New Password:</label><br>
            <input type="password" id="new_password" name="new_password" required><br><br>
            <label for="confirm_password">Confirm New Password:</label><br>
            <input type="password" id="confirm_password" name="confirm_password" required><br><br>
            <button type="submit">Reset Password</button>
        </form>
    <?php endif; ?>

    <?php if (!$showForm && !$message): ?>
        <p>Please use the link from your email to reset your password.</p>
    <?php endif; ?>
</body>
</html>
?>