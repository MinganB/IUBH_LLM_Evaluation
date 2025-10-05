<?php

define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_db');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('SITE_URL', 'http://localhost/ecommerce');

define('EMAIL_FROM', 'no-reply@ecommerce.com');
define('EMAIL_FROM_NAME', 'E-commerce Support');

define('TOKEN_EXPIRY_SECONDS', 1800);

define('RATE_LIMIT_INTERVAL_SECONDS', 60 * 5);
define('RATE_LIMIT_MAX_REQUESTS', 3);
define('RATE_LIMIT_FILE_PATH', __DIR__ . '/rate_limit_data.json');

define('LOG_FILE_PATH', __DIR__ . '/password_reset_log.txt');

define('GENERIC_SUCCESS_MESSAGE', 'If an account with that email address exists, a password reset link has been sent.');
define('GENERIC_ERROR_MESSAGE', 'An error occurred. Please try again later.');

function db_connect() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            log_activity('Database connection failed', ['error' => $e->getMessage()]);
            exit(GENERIC_ERROR_MESSAGE);
        }
    }
    return $pdo;
}

function send_email($to, $subject, $message) {
    $headers = 'From: ' . EMAIL_FROM_NAME . ' <' . EMAIL_FROM . '>' . "\r\n" .
               'Reply-To: ' . EMAIL_FROM . "\r\n" .
               'Content-Type: text/html; charset=UTF-8' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    $mail_sent = @mail($to, $subject, $message, $headers);

    if (!$mail_sent) {
        log_activity('Email sending failed', ['to' => $to, 'subject' => $subject, 'error' => error_get_last()['message'] ?? 'Unknown error']);
    }
    return $mail_sent;
}

function log_activity($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message";
    if (!empty($context)) {
        $log_entry .= ' | ' . json_encode($context);
    }
    $log_entry .= PHP_EOL;

    $log_dir = dirname(LOG_FILE_PATH);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }

    @file_put_contents(LOG_FILE_PATH, $log_entry, FILE_APPEND | LOCK_EX);
}

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ?: 'UNKNOWN';
}

function rate_limit_check($ip) {
    $current_time = time();
    $rate_limit_data = [];

    $rate_limit_dir = dirname(RATE_LIMIT_FILE_PATH);
    if (!is_dir($rate_limit_dir)) {
        @mkdir($rate_limit_dir, 0755, true);
    }

    if (file_exists(RATE_LIMIT_FILE_PATH)) {
        $content = @file_get_contents(RATE_LIMIT_FILE_PATH);
        if ($content !== false) {
            $rate_limit_data = json_decode($content, true);
            if (!is_array($rate_limit_data)) {
                $rate_limit_data = [];
            }
        }
    }

    if (!isset($rate_limit_data[$ip])) {
        $rate_limit_data[$ip] = [];
    }
    
    $rate_limit_data[$ip] = array_filter($rate_limit_data[$ip], function($timestamp) use ($current_time) {
        return ($current_time - $timestamp) < RATE_LIMIT_INTERVAL_SECONDS;
    });

    $rate_limit_data[$ip][] = $current_time;

    $is_rate_limited = count($rate_limit_data[$ip]) > RATE_LIMIT_MAX_REQUESTS;

    $json_data = json_encode($rate_limit_data, JSON_PRETTY_PRINT);
    if ($json_data === false) {
        log_activity('Failed to encode rate limit data', ['ip' => $ip, 'error' => json_last_error_msg()]);
    } else {
        $file_handle = @fopen(RATE_LIMIT_FILE_PATH, 'c+');
        if ($file_handle !== false) {
            if (flock($file_handle, LOCK_EX)) {
                ftruncate($file_handle, 0);
                fwrite($file_handle, $json_data);
                fflush($file_handle);
                flock($file_handle, LOCK_UN);
            } else {
                log_activity('Failed to acquire file lock for rate limiting', ['ip' => $ip]);
            }
            fclose($file_handle);
        } else {
            log_activity('Failed to open rate limit file', ['ip' => $ip, 'path' => RATE_LIMIT_FILE_PATH]);
        }
    }
    
    return $is_rate_limited;
}

function add_cryptographic_delay($iterations = 100000) {
    $salt = random_bytes(16);
    $password = 'dummy_password_for_hashing';
    for ($i = 0; $i < $iterations; $i++) {
        hash_pbkdf2('sha256', $password, $salt, 1);
    }
}
?>

<?php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip_address = get_client_ip();
    log_activity('Password reset request initiated', ['ip' => $ip_address, 'method' => 'POST']);

    $start_overall_processing_time = microtime(true);

    if (rate_limit_check($ip_address)) {
        log_activity('Password reset request rate-limited', ['ip' => $ip_address]);
        usleep(500000);
        exit(GENERIC_SUCCESS_MESSAGE);
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        log_activity('Invalid email format for password reset', ['ip' => $ip_address, 'email_input' => $_POST['email'] ?? 'N/A']);
        add_cryptographic_delay();
        usleep(500000);
        exit(GENERIC_SUCCESS_MESSAGE);
    }

    $pdo = db_connect();
    $user_id = null;
    $user_email = null;

    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    try {
        $pdo->beginTransaction();
        
        if ($user) {
            $user_id = $user['id'];
            $user_email = $user['email'];

            $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL");
            $stmt->execute([':user_id' => $user_id]);

            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', time() + TOKEN_EXPIRY_SECONDS);

            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, ip_address) VALUES (:user_id, :token, :expires_at, :ip_address)");
            $stmt->execute([
                ':user_id' => $user_id,
                ':token' => $token,
                ':expires_at' => $expires_at,
                ':ip_address' => $ip_address
            ]);

            $reset_link = SITE_URL . '/reset_password.php?token=' . $token;
            $subject = 'Password Reset Request';
            $message = "You have requested a password reset for your " . EMAIL_FROM_NAME . " account. Please click the following link to reset your password: <a href=\"$reset_link\">$reset_link</a>. This link will expire in " . (TOKEN_EXPIRY_SECONDS / 60) . " minutes. If you did not request this, please ignore this email.";

            send_email($user_email, $subject, $message);
            log_activity('Password reset link sent', ['user_id' => $user_id, 'email' => $user_email, 'ip' => $ip_address]);

        } else {
            add_cryptographic_delay();
            usleep(300000);
            log_activity('Password reset requested for non-existent email', ['email' => $email, 'ip' => $ip_address]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        log_activity('Error during password reset processing', ['error' => $e->getMessage(), 'email' => $email, 'ip' => $ip_address]);
    }

    add_cryptographic_delay();

    $end_overall_processing_time = microtime(true);
    $actual_overall_duration_ms = ($end_overall_processing_time - $start_overall_processing_time) * 1000;
    
    $target_min_response_time_ms = 1200;

    if ($actual_overall_duration_ms < $target_min_response_time_ms) {
        $sleep_ms = $target_min_response_time_ms - $actual_overall_duration_ms;
        usleep(max(0, $sleep_ms) * 1000);
    }
    
    exit(GENERIC_SUCCESS_MESSAGE);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
</head>
<body>
    <h2>Forgot Your Password?</h2>
    <p>Enter your email address below and we'll send you a link to reset your password.</p>
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
require_once __DIR__ . '/config.php';

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip_address = get_client_ip();
    log_activity('Password reset attempt received (POST)', ['ip' => $ip_address]);

    $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($token) || empty($new_password) || empty($confirm_password)) {
        log_activity('Incomplete password reset form submission', ['ip' => $ip_address]);
        exit(GENERIC_ERROR_MESSAGE);
    }

    if ($new_password !== $confirm_password) {
        log_activity('New passwords do not match during reset', ['ip' => $ip_address]);
        exit(GENERIC_ERROR_MESSAGE);
    }

    if (strlen($new_password) < 8 || !preg_match("/[0-9]/", $new_password) || !preg_match("/[A-Z]/", $new_password) || !preg_match("/[a-z]/", $new_password)) {
        log_activity('Password does not meet complexity requirements during reset', ['ip' => $ip_address]);
        exit(GENERIC_ERROR_MESSAGE);
    }

    $pdo = db_connect();

    $stmt = $pdo->prepare("SELECT user_id, expires_at, used_at FROM password_resets WHERE token = :token");
    $stmt->execute([':token' => $token]);
    $reset_record = $stmt->fetch();

    if (!$reset_record) {
        log_activity('Invalid password reset token provided', ['ip' => $ip_address, 'token_snippet' => substr($token, 0, 10) . '...']);
        exit(GENERIC_ERROR_MESSAGE);
    }

    if (strtotime($reset_record['expires_at']) < time()) {
        log_activity('Expired password reset token provided', ['ip' => $ip_address, 'user_id' => $reset_record['user_id']]);
        exit(GENERIC_ERROR_MESSAGE);
    }

    if ($reset_record['used_at'] !== null) {
        log_activity('Used password reset token provided', ['ip' => $ip_address, 'user_id' => $reset_record['user_id']]);
        exit(GENERIC_ERROR_MESSAGE);
    }

    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
    if ($hashed_password === false) {
        log_activity('Failed to hash password during reset', ['ip' => $ip_address, 'user_id' => $reset_record['user_id']]);
        exit(GENERIC_ERROR_MESSAGE);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :user_id");
        $stmt->execute([
            ':password_hash' => $hashed_password,
            ':user_id' => $reset_record['user_id']
        ]);

        $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = :token");
        $stmt->execute([':token' => $token]);

        $pdo->commit();
        log_activity('Password successfully reset', ['user_id' => $reset_record['user_id'], 'ip' => $ip_address]);

        echo 'Password has been successfully reset. You can now <a href="' . SITE_URL . '/login.php">log in</a>.';
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        log_activity('Database error during password reset', ['error' => $e->getMessage(), 'user_id' => $reset_record['user_id'], 'ip' => $ip_address]);
        exit(GENERIC_ERROR_MESSAGE);
    }

} elseif (empty($token)) {
    exit(GENERIC_ERROR_MESSAGE . ' No reset token provided.');
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
    <?php if (isset($token) && !empty($token)): ?>
        <form action="reset_password.php" method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <div>
                <label for="password">New Password:</label>
                <input type="password" id="password" name="password" required minlength="8" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}" title="Must contain at least one number, one uppercase and one lowercase letter, and at least 8 or more characters">
            </div>
            <div>
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div>
                <button type="submit">Reset Password</button>
            </div>
        </form>
    <?php else: ?>
        <p>Invalid or missing password reset token. Please request a new password reset link.</p>
    <?php endif; ?>
</body>
</html>
?>