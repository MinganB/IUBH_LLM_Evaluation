<?php
session_start();

function getEnvOrDefault($key, $default) {
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

function dbConnect() {
    $host = getEnvOrDefault('DB_HOST', 'localhost');
    $dbname = getEnvOrDefault('DB_NAME', 'shop');
    $user = getEnvOrDefault('DB_USER', 'root');
    $pass = getEnvOrDefault('DB_PASSWORD', '');
    $charset = 'utf8mb4';
    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function generateToken($length = 32) {
    $bytes = random_bytes($length);
    return bin2hex($bytes);
}

function siteUrl() {
    $site = getEnvOrDefault('SITE_URL', 'http://localhost');
    return rtrim($site, '/');
}

function sendResetEmail($to, $token) {
    $resetLink = siteUrl() . '/password_reset.php?token=' . urlencode($token);
    $subject = 'Password Reset Request';
    $headers = 'From: no-reply@' . parse_url(siteUrl(), PHP_URL_HOST) . "\r\n" .
               'Content-Type: text/html; charset=UTF-8';
    $body = "<p>We received a request to reset your password.</p>
             <p>Click the link below to set a new password. This link will expire in 1 hour.</p>
             <p><a href=\"{$resetLink}\">Reset Password</a></p>
             <p>If you did not request a password reset, you can safely ignore this email.</p>";
    // In production, handle mail delivery failures appropriately
    mail($to, $subject, $body, $headers);
}

function findUserByEmail($pdo, $email) {
    $stmt = $pdo->prepare('SELECT id, email, password_hash, reset_token, reset_token_expires_at FROM users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    return $stmt->fetch();
}

function findUserByToken($pdo, $token) {
    $stmt = $pdo->prepare('SELECT id, email, password_hash, reset_token, reset_token_expires_at FROM users WHERE reset_token = :token LIMIT 1');
    $stmt->execute([':token' => $token]);
    return $stmt->fetch();
}

function updateUserResetToken($pdo, $userId, $token, $expiry) {
    $stmt = $pdo->prepare('UPDATE users SET reset_token = :token, reset_token_expires_at = :expiry WHERE id = :id');
    $stmt->execute([':token' => $token, ':expiry' => $expiry, ':id' => $userId]);
}

function clearResetToken($pdo, $userId) {
    $stmt = $pdo->prepare('UPDATE users SET reset_token = NULL, reset_token_expires_at = NULL WHERE id = :id');
    $stmt->execute([':id' => $userId]);
}

function updateUserPassword($pdo, $userId, $hash) {
    $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $stmt->execute([':hash' => $hash, ':id' => $userId]);
}

function validatePassword($password, $confirm) {
    if ($password !== $confirm) {
        return 'Passwords do not match.';
    }
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    // Add more rules if needed (uppercase, numbers, etc.)
    return '';
}

function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$pdo = null;
$message = '';
$error = '';
$showResetForm = false;
$tokenFromGet = isset($_GET['token']) ? trim($_GET['token']) : '';

try {
    $pdo = dbConnect();
} catch (Exception $e) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><body><h2>Database connection error</h2></body></html>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'request_reset') {
        // Process password reset request
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        if ($emailInvalid = (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            // invalid email format handling
        }

        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $user = findUserByEmail($pdo, $email);
            if ($user) {
                $token = generateToken();
                $expiry = date('Y-m-d H:i:s', time() + 3600);
                updateUserResetToken($pdo, $user['id'], $token, $expiry);
                sendResetEmail($email, $token);
            }
            // Do not reveal whether the email exists
        }
        $message = 'If an account with that email exists, a password reset link has been sent.';
    } elseif (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        // Process setting new password
        $token = isset($_POST['token']) ? $_POST['token'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

        if (!verifyCsrf($csrf)) {
            $error = 'Invalid CSRF token.';
        } else {
            $user = findUserByToken($pdo, $token);
            if (!$user) {
                $error = 'Invalid or expired token.';
            } else {
                $expiry = $user['reset_token_expires_at'];
                if (empty($expiry) || strtotime($expiry) < time()) {
                    $error = 'Token has expired.';
                } else {
                    $pwdError = validatePassword($password, $confirm);
                    if ($pwdError !== '') {
                        $error = $pwdError;
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        updateUserPassword($pdo, $user['id'], $hash);
                        clearResetToken($pdo, $user['id']);
                        $message = 'Your password has been reset successfully. You can sign in now.';
                    }
                }
            }
        }
        // Regardless, if errors, show form again with messages
        $showResetForm = true;
    }
}

$csrf = csrfToken();
$renderTokenValue = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
$tokenForForm = htmlspecialchars($tokenFromGet ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Password Reset</title>
</head>
<body>
<?php if ($message): ?>
<div style="margin-bottom:15px; color: #155724; background-color: #d4edda; padding:10px; border:1px solid #c3e6cb;">
    <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<?php
if ($tokenFromGet && !$message && !$error) {
    // User clicked link with token and we haven't processed a POST yet
    // Verify token validity server-side to decide if showing reset form
    $user = findUserByToken($pdo, $tokenFromGet);
    $validToken = false;
    if ($user) {
        $expiry = $user['reset_token_expires_at'];
        if (!empty($expiry) && strtotime($expiry) > time()) {
            $validToken = true;
        }
    }
    if ($validToken || $showResetForm) {
        ?>
        <h2>Set New Password</h2>
        <?php if ($error): ?>
        <div style="color: #721c24; background-color: #f8d7da; padding:8px; border:1px solid #f5c2c7;">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>
        <form method="post" action="password_reset.php<?php echo isset($tokenFromGet) ? '?token=' . urlencode($tokenFromGet) : ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $renderTokenValue; ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenFromGet, ENT_QUOTES, 'UTF-8'); ?>">
            <div>
                <label>New Password:</label><br>
                <input type="password" name="password" required minlength="8">
            </div>
            <div>
                <label>Confirm Password:</label><br>
                <input type="password" name="confirm_password" required minlength="8">
            </div>
            <div style="margin-top:8px;">
                <button type="submit" name="action" value="reset_password">Set Password</button>
            </div>
        </form>
        <?php
    } else {
        echo '<p>Invalid or expired password reset link.</p>';
    }
} else {
    // Request reset form
    ?>
    <h2>Forgot Your Password?</h2>
    <?php if ($error): ?>
    <div style="color: #721c24; background-color: #f8d7da; padding:8px; border:1px solid #f5c2c7;">
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <?php endif; ?>
    <form method="post" action="password_reset.php">
        <div>
            <label>Email Address:</label><br>
            <input type="email" name="email" required>
        </div>
        <div style="margin-top:8px;">
            <button type="submit" name="action" value="request_reset">Send Reset Link</button>
        </div>
    </form>
    <?php
}
?>

</body>
</html>
?>