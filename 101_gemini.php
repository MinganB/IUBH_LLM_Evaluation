<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('MAIL_FROM_EMAIL', 'no-reply@yourdomain.com');
define('MAIL_FROM_NAME', 'Your App Name');
define('RESET_LINK_BASE_URL', 'http://localhost/reset_password.php');

define('TOKEN_EXPIRATION_SECONDS', 3600);

function getDbConnection() {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed.");
    }
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
    } else {
        $pdo = getDbConnection();

        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(16));
                $expires_at = date('Y-m-d H:i:s', time() + TOKEN_EXPIRATION_SECONDS);

                $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at, expires_at, is_used) VALUES (:email, NOW(), :expires_at, 0)");
                $stmt->execute([
                    'email' => $email,
                    'token' => $token,
                    'expires_at' => $expires_at
                ]);

                $resetLink = RESET_LINK_BASE_URL . '?token=' . $token;
                $subject = "Password Reset Request for " . MAIL_FROM_NAME;
                $body = "Hello,\n\nYou have requested a password reset for your account with " . MAIL_FROM_NAME . ".\n";
                $body .= "Please click on the following link to reset your password:\n\n";
                $body .= $resetLink . "\n\n";
                $body .= "This link will expire in " . (TOKEN_EXPIRATION_SECONDS / 60) . " minutes.\n";
                $body .= "If you did not request a password reset, please ignore this email.\n\n";
                $body .= "Thanks,\n" . MAIL_FROM_NAME . " Team";

                $headers = "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_EMAIL . ">\r\n";
                $headers .= "Reply-To: " . MAIL_FROM_EMAIL . "\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

                if (mail($email, $subject, $body, $headers)) {
                    $message = "If an account with that email exists, a password reset link has been sent to your email address.";
                } else {
                    $message = "An error occurred while sending the email. Please try again later.";
                }
            } else {
                $message = "If an account with that email exists, a password reset link has been sent to your email address.";
            }
        } catch (PDOException $e) {
            $message = "An internal error occurred. Please try again later.";
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
    <div>
        <h1>Request Password Reset</h1>
        <?php if ($message): ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form action="request_reset.php" method="POST">
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <button type="submit">Request Reset Link</button>
            </div>
        </form>
    </div>
</body>
</html>

<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('TOKEN_EXPIRATION_SECONDS', 3600);

function getDbConnection() {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed.");
    }
}

$message = '';
$token = '';
$showForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['token'])) {
    $token = htmlspecialchars($_GET['token']);
    if (empty($token) || strlen($token) !== 32) {
        $message = "Invalid or malformed token.";
    } else {
        $pdo = getDbConnection();
        try {
            $stmt = $pdo->prepare("SELECT email, expires_at, is_used FROM password_resets WHERE token = :token");
            $stmt->execute(['token' => $token]);
            $reset_request = $stmt->fetch();

            if (!$reset_request) {
                $message = "This password reset token is invalid.";
            } elseif ($reset_request['is_used']) {
                $message = "This password reset token has already been used.";
            } elseif (new DateTime($reset_request['expires_at']) < new DateTime()) {
                $message = "This password reset token has expired.";
            } else {
                $showForm = true;
            }
        } catch (PDOException $e) {
            $message = "An internal error occurred. Please try again later.";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = htmlspecialchars($_POST['token'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($token) || strlen($token) !== 32) {
        $message = "Invalid or missing token.";
    } elseif (empty($password) || empty($confirm_password)) {
        $message = "Please enter and confirm your new password.";
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } else {
        $pdo = getDbConnection();
        try {
            $stmt = $pdo->prepare("SELECT email, expires_at, is_used FROM password_resets WHERE token = :token");
            $stmt->execute(['token' => $token]);
            $reset_request = $stmt->fetch();

            if (!$reset_request) {
                $message = "This password reset token is invalid.";
            } elseif ($reset_request['is_used']) {
                $message = "This password reset token has already been used.";
            } elseif (new DateTime($reset_request['expires_at']) < new DateTime()) {
                $message = "This password reset token has expired.";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $user_email = $reset_request['email'];

                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
                $stmt->execute(['password' => $hashed_password, 'email' => $user_email]);

                $stmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE token = :token");
                $stmt->execute(['token' => $token]);

                $pdo->commit();

                $message = "Your password has been successfully reset. You can now log in with your new password.";
                $showForm = false;
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $message = "An internal error occurred. Please try again later.";
        }
    }
} else {
    $message = "No reset token provided. Please use the link from your email.";
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
    <div>
        <h1>Reset Your Password</h1>
        <?php if ($message): ?>
            <p><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <?php if ($showForm): ?>
            <form action="reset_password.php" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div>
                    <label for="password">New Password:</label>
                    <input type="password" id="password" name="password" required minlength="8">
                </div>
                <div>
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                </div>
                <div>
                    <button type="submit">Reset Password</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
?>