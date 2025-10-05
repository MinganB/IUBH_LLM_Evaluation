<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

function getDbConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

function sendJsonResponse($success, $message) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        sendJsonResponse(false, 'Invalid email address provided.');
    }

    $pdo = getDbConnection();
    if (!$pdo) {
        sendJsonResponse(false, 'Internal server error.');
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(16));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)");
            $stmt->execute(['email' => $email, 'token' => $token, 'expires_at' => $expiresAt]);

            $resetLink = 'http://localhost/public/reset_password.php?token=' . $token;
            $subject = 'Password Reset Request';
            $message = "Click on the following link to reset your password: " . $resetLink;
            $headers = 'From: noreply@yourecommerce.com' . "\r\n" .
                       'Reply-To: noreply@yourecommerce.com' . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();

            if (mail($email, $subject, $message, $headers)) {
                sendJsonResponse(true, 'A password reset link has been sent to your email address.');
            } else {
                error_log("Failed to send email to " . $email);
                sendJsonResponse(false, 'Failed to send reset email. Please try again later.');
            }
        } else {
            sendJsonResponse(false, 'If an account with that email exists, a password reset link has been sent.');
        }
    } catch (PDOException $e) {
        error_log("Database error during password reset request: " . $e->getMessage());
        sendJsonResponse(false, 'An error occurred during the reset request. Please try again.');
    }
} else {
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
    <form action="request_reset.php" method="POST">
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        <button type="submit">Send Reset Link</button>
    </form>
</body>
</html>
<?php
}
?>

<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

function getDbConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

function sendJsonResponse($success, $message) {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);
    $confirmPassword = filter_input(INPUT_POST, 'confirm_password', FILTER_SANITIZE_STRING);
    $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);

    if (empty($password) || empty($confirmPassword) || empty($token)) {
        sendJsonResponse(false, 'All fields are required.');
    }

    if ($password !== $confirmPassword) {
        sendJsonResponse(false, 'Passwords do not match.');
    }

    if (strlen($password) < 8) {
        sendJsonResponse(false, 'Password must be at least 8 characters long.');
    }

    $pdo = getDbConnection();
    if (!$pdo) {
        sendJsonResponse(false, 'Internal server error.');
    }

    try {
        $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = :token");
        $stmt->execute(['token' => $token]);
        $resetRequest = $stmt->fetch();

        if (!$resetRequest || $resetRequest['used'] == 1 || strtotime($resetRequest['expires_at']) < time()) {
            sendJsonResponse(false, 'Invalid, expired, or used reset token.');
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
        $stmt->execute(['password' => $hashedPassword, 'email' => $resetRequest['email']]);

        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
        $stmt->execute(['token' => $token]);

        $pdo->commit();

        sendJsonResponse(true, 'Your password has been successfully reset.');

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error during password reset: " . $e->getMessage());
        sendJsonResponse(false, 'An error occurred during password reset. Please try again.');
    }
} else {
    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
    if (empty($token)) {
        // Potentially redirect to an error page or show a message
        // For this context, we will simply not pre-fill the token and let form validation handle it.
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
    <h2>Reset Your Password</h2>
    <form action="reset_password.php" method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
        
        <label for="password">New Password:</label><br>
        <input type="password" id="password" name="password" required><br><br>
        
        <label for="confirm_password">Confirm New Password:</label><br>
        <input type="password" id="confirm_password" name="confirm_password" required><br><br>
        
        <button type="submit">Reset Password</button>
    </form>
</body>
</html>
<?php
}
?>