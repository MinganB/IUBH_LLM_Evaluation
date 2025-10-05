<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('SENDER_EMAIL', 'no-reply@yourdomain.com');
define('SITE_URL', 'http://localhost/password_reset');
define('RESET_TOKEN_EXPIRY_MINUTES', 30);
define('LOG_FILE', __DIR__ . '/password_reset_log.txt');
define('MIN_RESPONSE_TIME_SECONDS', 2);

define('RATE_LIMIT_INTERVAL_SECONDS', 60);
define('RATE_LIMIT_MAX_REQUESTS', 3);

function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            logError("Database connection error: " . $e->getMessage());
            http_response_code(500);
            exit();
        }
    }
    return $pdo;
}

function logError($message) {
    file_put_contents(LOG_FILE, date('Y-m-d H:i:s') . ' [ERROR] ' . $message . PHP_EOL, FILE_APPEND);
}

function logRequest($type, $ip, $email = null, $status = null) {
    $logMessage = date('Y-m-d H:i:s') . ' [REQUEST] Type: ' . $type . ', IP: ' . $ip;
    if ($email !== null) {
        $logMessage .= ', Email: ' . $email;
    }
    if ($status !== null) {
        $logMessage .= ', Status: ' . $status;
    }
    file_put_contents(LOG_FILE, $logMessage . PHP_EOL, FILE_APPEND);
}

function sendEmail($to, $subject, $message) {
    $headers = 'From: ' . SENDER_EMAIL . "\r\n" .
               'Reply-To: ' . SENDER_EMAIL . "\r\n" .
               'MIME-Version: 1.0' . "\r\n" .
               'Content-type: text/html; charset=iso-8859-1' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();
    return mail($to, $subject, $message, $headers);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $startTime = microtime(true);
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $pdo = getDbConnection();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE request_timestamp < DATE_SUB(NOW(), INTERVAL :interval_seconds SECOND)");
        $stmt->bindValue(':interval_seconds', RATE_LIMIT_INTERVAL_SECONDS, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE ip_address = :ip AND request_timestamp > DATE_SUB(NOW(), INTERVAL :interval_seconds SECOND)");
        $stmt->bindValue(':ip', $clientIp);
        $stmt->bindValue(':interval_seconds', RATE_LIMIT_INTERVAL_SECONDS, PDO::PARAM_INT);
        $stmt->execute();
        $requestCount = $stmt->fetchColumn();

        if ($requestCount >= RATE_LIMIT_MAX_REQUESTS) {
            logRequest('Password Reset Request', $clientIp, null, 'Rate Limited');
            $pdo->rollBack();
            $sleepTime = MIN_RESPONSE_TIME_SECONDS - (microtime(true) - $startTime);
            if ($sleepTime > 0) {
                usleep($sleepTime * 1000000);
            }
            echo "A password reset request has been initiated. If an account with that email exists, you will receive an email shortly.";
            exit();
        }

        $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_address) VALUES (:ip)");
        $stmt->bindValue(':ip', $clientIp);
        $stmt->execute();

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Rate limiting DB error: " . $e->getMessage());
        http_response_code(500);
        exit();
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    if (!$email) {
        logRequest('Password Reset Request', $clientIp, 'Invalid Email', 'Failed');
        $sleepTime = MIN_RESPONSE_TIME_SECONDS - (microtime(true) - $startTime);
        if ($sleepTime > 0) {
            usleep($sleepTime * 1000000);
        }
        echo "A password reset request has been initiated. If an account with that email exists, you will receive an email shortly.";
        exit();
    }

    $userFound = false;
    $resetToken = bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . RESET_TOKEN_EXPIRY_MINUTES . ' minutes'));
    $emailSent = false;

    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user) {
            $userFound = true;
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)");
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':token', $resetToken);
            $stmt->bindValue(':expires_at', $expiresAt);
            $stmt->execute();
        } else {
            usleep(50000);
        }

        $pdo->commit();

        if ($userFound) {
            $resetLink = SITE_URL . '/reset_password.php?token=' . $resetToken;
            $subject = 'Password Reset Request';
            $message = '
                <html>
                <head>
                  <title>Password Reset</title>
                </head>
                <body>
                  <p>Hello,</p>
                  <p>You have requested a password reset for your account. Please click on the following link to reset your password:</p>
                  <p><a href="' . htmlspecialchars($resetLink) . '">' . htmlspecialchars($resetLink) . '</a></p>
                  <p>This link will expire in ' . RESET_TOKEN_EXPIRY_MINUTES . ' minutes.</p>
                  <p>If you did not request a password reset, please ignore this email.</p>
                  <p>Thank you,</p>
                  <p>Your Application Team</p>
                </body>
                </html>
            ';
            if (sendEmail($email, $subject, $message)) {
                $emailSent = true;
            } else {
                logError("Failed to send password reset email to " . $email);
            }
        }
        logRequest('Password Reset Request', $clientIp, $email, $userFound ? 'Successful' : 'Email Not Found');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logError("Password reset request DB error: " . $e->getMessage());
        $sleepTime = MIN_RESPONSE_TIME_SECONDS - (microtime(true) - $startTime);
        if ($sleepTime > 0) {
            usleep($sleepTime * 1000000);
        }
        echo "A password reset request has been initiated. If an account with that email exists, you will receive an email shortly.";
        exit();
    }

    $sleepTime = MIN_RESPONSE_TIME_SECONDS - (microtime(true) - $startTime);
    if ($sleepTime > 0) {
        usleep($sleepTime * 1000000);
    }
    echo "A password reset request has been initiated. If an account with that email exists, you will receive an email shortly.";
    exit();

} else {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
</head>
<body>
    <h2>Request Password Reset</h2>
    <form action="request_reset.php" method="post">
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div>
            <button type="submit">Send Reset Link</button>
        </div>
    </form>
</body>
</html>
<?php
}
?>