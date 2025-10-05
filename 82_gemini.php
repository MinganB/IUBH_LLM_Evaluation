<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('SITE_URL', 'http://localhost/password_reset_module.php');

define('EMAIL_FROM', 'no-reply@yourdomain.com');
define('EMAIL_FROM_NAME', 'Your Application');

define('TOKEN_EXPIRY_SECONDS', 3600);

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

function redirect($url, $messageType = null, $message = null) {
    if ($messageType && $message) {
        $_SESSION['flash_message'] = ['type' => $messageType, 'text' => $message];
    }
    header("Location: " . $url);
    exit();
}

function flashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return '<div class="alert alert-' . htmlspecialchars($message['type']) . '">' . htmlspecialchars($message['text']) . '</div>';
    }
    return '';
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function sendPasswordResetEmail($userEmail, $resetToken) {
    $resetLink = SITE_URL . '?action=reset&token=' . urlencode($resetToken);
    $subject = 'Password Reset Request';
    $message = "Dear User,\n\n"
             . "You have requested to reset your password. Please click on the following link to set a new password:\n"
             . $resetLink . "\n\n"
             . "This link will expire in " . (TOKEN_EXPIRY_SECONDS / 60) . " minutes.\n"
             . "If you did not request a password reset, please ignore this email.\n\n"
             . "Regards,\n"
             . EMAIL_FROM_NAME;

    $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_FROM . ">\r\n"
             . "Reply-To: " . EMAIL_FROM . "\r\n"
             . "X-Mailer: PHP/" . phpversion();

    return mail($userEmail, $subject, $message, $headers);
}

function renderHeader($title = 'Password Reset') {
    generateCsrfToken(); 
    return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body>
    <div>';
}

function renderFooter() {
    return '    </div>
</body>
</html>';
}

function handleRequestResetPage($pdo) {
    $message = flashMessage();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            redirect(SITE_URL . '?action=request', 'danger', 'Invalid CSRF token. Please try again.');
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirect(SITE_URL . '?action=request', 'danger', 'Please enter a valid email address.');
        }

        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $userId = $user['id'];
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY_SECONDS);

            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);

            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
            $stmt->execute([
                'user_id' => $userId,
                'token' => $resetToken,
                'expires_at' => $expiresAt
            ]);

            if (sendPasswordResetEmail($user['email'], $resetToken)) {
                redirect(SITE_URL . '?action=request', 'success', 'If an account with that email exists, a password reset link has been sent to your email address.');
            } else {
                redirect(SITE_URL . '?action=request', 'danger', 'Failed to send password reset email. Please try again later.');
            }
        } else {
            redirect(SITE_URL . '?action=request', 'success', 'If an account with that email exists, a password reset link has been sent to your email address.');
        }
    }

    echo renderHeader('Request Password Reset');
    echo $message;
    echo '<h2>Request Password Reset</h2>
    <form action="' . SITE_URL . '?action=request" method="POST">
        <input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">
        <div>
            <label for="email">Email Address:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <br>
        <button type="submit">Send Reset Link</button>
    </form>';
    echo renderFooter();
}

function handleSetNewPasswordPage($pdo) {
    $message = flashMessage();
    $token = $_GET['token'] ?? '';
    $validToken = false;
    $userId = null;

    if (empty($token)) {
        redirect(SITE_URL . '?action=request', 'danger', 'Password reset token is missing.');
    }

    $stmt = $pdo->prepare("SELECT pr.user_id, pr.expires_at FROM password_resets pr WHERE pr.token = :token");
    $stmt->execute(['token' => $token]);
    $resetData = $stmt->fetch();

    if ($resetData) {
        if (strtotime($resetData['expires_at']) > time()) {
            $validToken = true;
            $userId = $resetData['user_id'];
        } else {
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
            $stmt->execute(['token' => $token]);
            redirect(SITE_URL . '?action=request', 'danger', 'Password reset link has expired. Please request a new one.');
        }
    } else {
        redirect(SITE_URL . '?action=request', 'danger', 'Invalid password reset token.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
            redirect(SITE_URL . '?action=reset&token=' . urlencode($token), 'danger', 'Invalid CSRF token. Please try again.');
        }

        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($password) || empty($confirmPassword)) {
            redirect(SITE_URL . '?action=reset&token=' . urlencode($token), 'danger', 'Please enter and confirm your new password.');
        }
        if ($password !== $confirmPassword) {
            redirect(SITE_URL . '?action=reset&token=' . urlencode($token), 'danger', 'Passwords do not match.');
        }
        if (strlen($password) < 8) {
            redirect(SITE_URL . '?action=reset&token=' . urlencode($token), 'danger', 'Password must be at least 8 characters long.');
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
            $stmt->execute(['password' => $hashedPassword, 'id' => $userId]);

            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = :token");
            $stmt->execute(['token' => $token]);

            $pdo->commit();
            redirect(SITE_URL . '?action=success', 'success', 'Your password has been reset successfully! You can now log in with your new password.');
        } catch (Exception $e) {
            $pdo->rollBack();
            redirect(SITE_URL . '?action=reset&token=' . urlencode($token), 'danger', 'An error occurred while resetting your password. Please try again.');
        }
    }

    if ($validToken) {
        echo renderHeader('Set New Password');
        echo $message;
        echo '<h2>Set New Password</h2>
        <form action="' . SITE_URL . '?action=reset&token=' . urlencode($token) . '" method="POST">
            <input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">
            <div>
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            <br>
            <div>
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>
            <br>
            <button type="submit">Set Password</button>
        </form>';
        echo renderFooter();
    }
}

function handleSuccessPage() {
    $message = flashMessage();
    echo renderHeader('Success');
    echo $message;
    echo '<h2>Operation Successful</h2>
    <p>Your request has been processed.</p>
    <p><a href="' . SITE_URL . '?action=request">Request another reset</a></p>';
    echo renderFooter();
}

$action = $_GET['action'] ?? 'request';

switch ($action) {
    case 'request':
        handleRequestResetPage($pdo);
        break;
    case 'reset':
        handleSetNewPasswordPage($pdo);
        break;
    case 'success':
        handleSuccessPage();
        break;
    default:
        redirect(SITE_URL . '?action=request', 'danger', 'Invalid action requested.');
        break;
}

?>