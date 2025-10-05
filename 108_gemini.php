<?php
// config.php - This file should be placed in the project root,
//             one level above the '/public' directory.

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'root');
define('DB_PASS', 'password'); // IMPORTANT: Use a strong password and restrict DB user permissions in production

// Password Reset Configuration
define('PASSWORD_RESET_TOKEN_LENGTH_BYTES', 32); // For a 64-char hex token
define('PASSWORD_RESET_TOKEN_EXPIRATION_MINUTES', 30);
define('PASSWORD_RESET_EMAIL_FROM', 'no-reply@ecommerce.com');
define('PASSWORD_RESET_SUBJECT', 'Password Reset Request');

// Adjust this to your actual website's base URL.
// For example: 'https://yourdomain.com'
// If running locally, it might be 'http://localhost/public' or 'http://localhost/your_project/public'
define('SITE_URL', 'http://localhost/public'); 

// Logging configuration
// This path will be relative to the config.php file's location (project root).
define('LOG_FILE', __DIR__ . '/password_reset.log');

// Rate Limiting configuration (per IP address)
define('RATE_LIMIT_DURATION', 3600); // Window of 1 hour (in seconds)
define('RATE_LIMIT_MAX_REQUESTS', 5); // Max 5 requests per IP within the duration
// This path will be relative to the config.php file's location (project root).
define('RATE_LIMIT_FILE', __DIR__ . '/rate_limit.json'); 

// Helper for database connection
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            log_message('CRITICAL', 'Database connection failed: ' . $e->getMessage());
            send_json_response(false, 'A system error occurred. Please try again later.', 500);
        }
    }
    return $pdo;
}

// Helper for logging
function log_message($level, $message, $ip = null) {
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
    $log_entry = sprintf("[%s] [%s] [%s] %s%s", $timestamp, $ip_address, $level, $message, PHP_EOL);
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// Helper for JSON response
function send_json_response($success, $message, $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit();
}

// Rate Limiting helper functions
function rate_limit_check() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $current_time = time();

    $requests = [];
    if (file_exists(RATE_LIMIT_FILE)) {
        $file_content = file_get_contents(RATE_LIMIT_FILE);
        if ($file_content !== false && ($decoded = json_decode($file_content, true)) !== null) {
            $requests = $decoded;
        }
    }

    if (isset($requests[$ip])) {
        $requests[$ip] = array_filter($requests[$ip], function($timestamp) use ($current_time) {
            return ($current_time - $timestamp) < RATE_LIMIT_DURATION;
        });
    } else {
        $requests[$ip] = [];
    }

    if (count($requests[$ip]) >= RATE_LIMIT_MAX_REQUESTS) {
        log_message('WARNING', 'Rate limit exceeded for IP: ' . $ip);
        return false;
    }

    $requests[$ip][] = $current_time;
    file_put_contents(RATE_LIMIT_FILE, json_encode($requests), LOCK_EX);
    return true;
}

function send_reset_email($recipient_email, $token) {
    $reset_link = SITE_URL . '/reset_password.php?token=' . urlencode($token);
    $subject = PASSWORD_RESET_SUBJECT;
    $message = "Dear User," . PHP_EOL . PHP_EOL
             . "You have requested a password reset. Please click on the following link to reset your password:" . PHP_EOL
             . $reset_link . PHP_EOL . PHP_EOL
             . "This link will expire in " . PASSWORD_RESET_TOKEN_EXPIRATION_MINUTES . " minutes." . PHP_EOL
             . "If you did not request a password reset, please ignore this email." . PHP_EOL . PHP_EOL
             . "Regards," . PHP_EOL
             . "Your E-commerce Team";

    $headers = 'From: ' . PASSWORD_RESET_EMAIL_FROM . "\r\n" .
               'Reply-To: ' . PASSWORD_RESET_EMAIL_FROM . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    $mail_sent = @mail($recipient_email, $subject, $message, $headers);

    if (!$mail_sent) {
        log_message('ERROR', 'Failed to send password reset email to ' . $recipient_email . '. Check mail server configuration.');
    }
    return $mail_sent;
}
?>
<?php
// public/request_reset.php - This file should be placed in the '/public' directory.

require_once __DIR__ . '/../config.php'; // Adjust path if config.php is in a different location

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_message('INFO', 'Password reset request received.', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');

    if (!rate_limit_check()) {
        send_json_response(false, 'Too many requests. Please try again later.', 429);
    }

    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        log_message('WARNING', 'Invalid email format provided for password reset.', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        send_json_response(false, 'Invalid email address provided.', 400);
    }

    $pdo = get_db_connection();
    $user_exists = false;
    $user_email_for_token = $email;

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $user_exists = true;
        }

        $token = bin2hex(random_bytes(PASSWORD_RESET_TOKEN_LENGTH_BYTES));
        $expires_at = date('Y-m-d H:i:s', time() + (PASSWORD_RESET_TOKEN_EXPIRATION_MINUTES * 60));

        if ($user_exists) {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "INSERT INTO password_resets (email, token, expires_at, used) VALUES (:email, :token, :expires_at, FALSE)"
            );
            $stmt->execute([
                'email' => $user_email_for_token,
                'token' => $token,
                'expires_at' => $expires_at,
            ]);
            $pdo->commit();

            send_reset_email($user_email_for_token, $token);
            log_message('INFO', 'Password reset token generated and email sent to ' . $user_email_for_token, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        } else {
            // Timing attack mitigation: Perform dummy work to simulate the work done
            // when a user exists, making response time less indicative of user existence.
            password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
            log_message('INFO', 'Password reset requested for non-existent email: ' . $email, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        }

        send_json_response(true, 'If an account with that email exists, a password reset link has been sent.');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        log_message('ERROR', 'Database error during password reset request: ' . $e->getMessage() . ' for email: ' . $email, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        send_json_response(false, 'An unexpected error occurred. Please try again later.', 500);
    } catch (Exception $e) {
        log_message('ERROR', 'General error during password reset request: ' . $e->getMessage() . ' for email: ' . $email, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        send_json_response(false, 'An unexpected error occurred. Please try again later.', 500);
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
    <h1>Request Password Reset</h1>
    <form action="request_reset.php" method="POST">
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br><br>
        <button type="submit">Send Reset Link</button>
    </form>
    <div id="response"></div>
    <script>
        document.querySelector('form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const responseDiv = document.getElementById('response');
            responseDiv.textContent = '';
            responseDiv.style.color = '';

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                responseDiv.textContent = data.message;
                responseDiv.style.color = data.success ? 'green' : 'red';
            } catch (error) {
                console.error('Error:', error);
                responseDiv.textContent = 'An error occurred while processing your request.';
                responseDiv.style.color = 'red';
            }
        });
    </script>
</body>
</html>
<?php
}
?>
<?php
// public/reset_password.php - This file should be placed in the '/public' directory.

require_once __DIR__ . '/../config.php'; // Adjust path if config.php is in a different location

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    log_message('INFO', 'Password reset attempt received via POST.', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');

    $token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $password = filter_input(INPUT_POST, 'password');
    $confirm_password = filter_input(INPUT_POST, 'confirm_password');

    if (empty($token) || empty($password) || empty($confirm_password)) {
        log_message('WARNING', 'Missing required fields for password reset.', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        send_json_response(false, 'All fields are required.', 400);
    }

    if ($password !== $confirm_password) {
        log_message('WARNING', 'Passwords do not match during reset attempt.', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        send_json_response(false, 'New password and confirmation password do not match.', 400);
    }

    if (strlen($password) < 8) {
        log_message('WARNING', 'Password too short during reset attempt.', $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        send_json_response(false, 'Password must be at least 8 characters long.', 400);
    }

    $pdo = get_db_connection();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT email, expires_at, used FROM password_resets WHERE token = :token FOR UPDATE"
        );
        $stmt->execute(['token' => $token]);
        $reset_record = $stmt->fetch();

        if (!$reset_record) {
            log_message('WARNING', 'Invalid password reset token provided: ' . $token, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
            $pdo->rollback();
            send_json_response(false, 'Invalid or expired password reset token.', 400);
        }

        if ($reset_record['used']) {
            log_message('WARNING', 'Used password reset token provided: ' . $token . ' for email: ' . $reset_record['email'], $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
            $pdo->rollback();
            send_json_response(false, 'Invalid or expired password reset token.', 400);
        }

        if (new DateTime() > new DateTime($reset_record['expires_at'])) {
            log_message('WARNING', 'Expired password reset token provided: ' . $token . ' for email: ' . $reset_record['email'], $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
            $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE token = :token");
            $stmt->execute(['token' => $token]);
            $pdo->rollback();
            send_json_response(false, 'Invalid or expired password reset token.', 400);
        }

        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
        $stmt->execute([
            'password' => $hashed_password,
            'email' => $reset_record['email']
        ]);

        $stmt = $pdo->prepare("UPDATE password_resets SET used = TRUE WHERE token = :token");
        $stmt->execute(['token' => $token]);

        $pdo->commit();
        log_message('INFO', 'Password successfully reset for email: ' . $reset_record['email'], $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        send_json_response(true, 'Your password has been reset successfully.');

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollback();
        }
        log_message('ERROR', 'Database error during password reset: ' . $e->getMessage() . ' for token: ' . $token, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        send_json_response(false, 'An unexpected error occurred. Please try again later.', 500);
    } catch (Exception $e) {
        log_message('ERROR', 'General error during password reset: ' . $e->getMessage() . ' for token: ' . $token, $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN');
        send_json_response(false, 'An unexpected error occurred. Please try again later.', 500);
    }

} else {
    $token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body>
    <h1>Reset Password</h1>
    <form action="reset_password.php" method="POST">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        <label for="password">New Password:</label><br>
        <input type="password" id="password" name="password" required minlength="8"><br><br>
        <label for="confirm_password">Confirm New Password:</label><br>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8"><br><br>
        <button type="submit">Reset Password</button>
    </form>
    <div id="response"></div>
    <script>
        document.querySelector('form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const responseDiv = document.getElementById('response');
            responseDiv.textContent = '';
            responseDiv.style.color = '';

            const password = formData.get('password');
            const confirmPassword = formData.get('confirm_password');

            if (password !== confirmPassword) {
                responseDiv.textContent = 'Passwords do not match.';
                responseDiv.style.color = 'red';
                return;
            }
            if (password.length < 8) {
                responseDiv.textContent = 'Password must be at least 8 characters long.';
                responseDiv.style.color = 'red';
                return;
            }

            try {
                const response = await fetch(form.action, {
                    method: form.method,
                    body: formData,
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();
                responseDiv.textContent = data.message;
                responseDiv.style.color = data.success ? 'green' : 'red';
            } catch (error) {
                console.error('Error:', error);
                responseDiv.textContent = 'An error occurred while processing your request.';
                responseDiv.style.color = 'red';
            }
        });
    </script>
</body>
</html>
<?php
}
?>