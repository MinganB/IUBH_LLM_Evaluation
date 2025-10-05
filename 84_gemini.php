<?php

// --- Configuration Constants ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Token expiration time in minutes
define('TOKEN_EXPIRATION_MINUTES', 30);

// Rate limiting: X attempts within Y seconds for password reset requests
define('RATE_LIMIT_DURATION_SECONDS', 300); // 5 minutes
define('RATE_LIMIT_MAX_ATTEMPTS', 5);

// Secure log file path (ensure this directory is writable by the web server but not publicly accessible)
define('LOG_FILE_PATH', __DIR__ . '/password_reset_log.log');

// Consistent response time simulation for timing attack prevention
define('MIN_RESPONSE_TIME_MS', 500); // Minimum 500ms response time

// --- Database Connection Function ---
function getDbConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the actual error internally, but do not expose it
            error_log("Database connection failed: " . $e->getMessage());
            http_response_code(500);
            exit("An internal server error occurred.");
        }
    }
    return $pdo;
}

// --- Utility Functions ---

function generateSecureToken(): string
{
    return bin2hex(random_bytes(32)); // 64-character hex string
}

function sendResetEmail(string $email, string $token): void
{
    // In a production environment, you would use a dedicated email sending library
    // (e.g., PHPMailer, Symfony Mailer) or a transactional email service (e.g., SendGrid, Mailgun).
    // This is a placeholder for demonstration purposes.

    $resetLink = "https://yourdomain.com/reset_password.php?token=" . urlencode($token);
    $subject = "Password Reset Request";
    $message = "Dear User,\n\nYou have requested a password reset. Please click the following link to set a new password:\n\n" . $resetLink . "\n\nThis link will expire in " . TOKEN_EXPIRATION_MINUTES . " minutes.\nIf you did not request this, please ignore this email.\n\nThank you,\nYour Website Team";
    $headers = "From: no-reply@yourdomain.com\r\n";
    $headers .= "Reply-To: no-reply@yourdomain.com\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";

    // Simulate sending email
    // mail($email, $subject, $message, $headers);
    error_log("Simulated email sent to: " . $email . " with reset link: " . $resetLink);
}

function logPasswordResetEvent(string $type, array $data): void
{
    $logMessage = "[" . date('Y-m-d H:i:s') . "] " . $type . ": " . json_encode($data) . "\n";
    file_put_contents(LOG_FILE_PATH, $logMessage, FILE_APPEND | LOCK_EX);
}

function getClientIp(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // Take only the first IP if there are multiple (e.g., from proxies)
    $ip = explode(',', $ip)[0];
    // Basic validation
    return filter_var($ip, FILTER_VALIDATE_IP) ?: 'UNKNOWN';
}

function simulateProcessingDelay(float $startTime): void
{
    $elapsedTimeMs = (microtime(true) - $startTime) * 1000;
    $delayNeededMs = MIN_RESPONSE_TIME_MS - $elapsedTimeMs;

    if ($delayNeededMs > 0) {
        usleep((int)($delayNeededMs * 1000));
    }
}

// --- Rate Limiting Functions ---

function isRateLimited(string $ipAddress): bool
{
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("DELETE FROM password_reset_rate_limits WHERE timestamp < DATE_SUB(NOW(), INTERVAL :duration SECOND)");
    $stmt->bindValue(':duration', RATE_LIMIT_DURATION_SECONDS, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_rate_limits WHERE ip_address = :ip_address");
    $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetchColumn();

    return $count >= RATE_LIMIT_MAX_ATTEMPTS;
}

function recordRateLimitAttempt(string $ipAddress): void
{
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("INSERT INTO password_reset_rate_limits (ip_address, timestamp) VALUES (:ip_address, NOW())");
    $stmt->bindValue(':ip_address', $ipAddress, PDO::PARAM_STR);
    $stmt->execute();
}

// --- Main Password Reset Module Functions ---

function handleRequestPasswordReset(): void
{
    $startTime = microtime(true);
    $ipAddress = getClientIp();

    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['message' => 'Method Not Allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        // Log invalid email attempt, but return generic success to user for security
        logPasswordResetEvent('invalid_email_format', ['ip' => $ipAddress, 'provided_email' => ($input['email'] ?? 'N/A')]);
        simulateProcessingDelay($startTime);
        echo json_encode(['message' => 'If an account with that email address exists, a password reset link will be sent.']);
        return;
    }

    logPasswordResetEvent('reset_request_attempt', ['ip' => $ipAddress, 'email' => $email]);

    if (isRateLimited($ipAddress)) {
        logPasswordResetEvent('rate_limited', ['ip' => $ipAddress, 'email' => $email]);
        simulateProcessingDelay($startTime);
        echo json_encode(['message' => 'Too many requests. Please try again later.']);
        return;
    }

    recordRateLimitAttempt($ipAddress);

    $pdo = getDbConnection();
    $userFound = false;
    $userId = null;

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user) {
            $userFound = true;
            $userId = $user['id'];
        }

        if ($userFound) {
            $token = generateSecureToken();
            $expiresAt = date('Y-m-d H:i:s', time() + TOKEN_EXPIRATION_MINUTES * 60);

            // Invalidate any existing tokens for this user to ensure single active token
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL AND expires_at > NOW()");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (:user_id, :token, :expires_at)");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':token', $token, PDO::PARAM_STR);
            $stmt->bindValue(':expires_at', $expiresAt, PDO::PARAM_STR);
            $stmt->execute();
            $pdo->commit();

            sendResetEmail($email, $token);
            logPasswordResetEvent('reset_email_sent', ['ip' => $ipAddress, 'email' => $email, 'user_id' => $userId]);
        } else {
            // For timing attack prevention, perform a similar amount of work
            // without actually interacting with the password_resets table.
            // A simple sleep or dummy DB operation can be used.
            // We already have a consistent delay mechanism at the end, so this is fine.
        }

    } catch (PDOException $e) {
        logPasswordResetEvent('db_error_request_reset', ['ip' => $ipAddress, 'email' => $email, 'error' => $e->getMessage()]);
        // Do not expose error to user
    } finally {
        simulateProcessingDelay($startTime);
        // Always return a generic success message to prevent timing attacks and email enumeration
        echo json_encode(['message' => 'If an account with that email address exists, a password reset link will be sent.']);
    }
}

function handleSetNewPassword(): void
{
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['message' => 'Method Not Allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $token = $input['token'] ?? '';
    $newPassword = $input['password'] ?? '';

    // Basic password validation
    if (empty($token) || empty($newPassword) || strlen($newPassword) < 8) {
        echo json_encode(['message' => 'Invalid request or password does not meet requirements.']);
        return;
    }

    $pdo = getDbConnection();

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT user_id, expires_at, used_at FROM password_resets WHERE token = :token");
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();
        $resetRequest = $stmt->fetch();

        if (!$resetRequest) {
            echo json_encode(['message' => 'Invalid or expired token.']);
            $pdo->rollBack();
            logPasswordResetEvent('invalid_token', ['token' => $token, 'reason' => 'not_found']);
            return;
        }

        if (new DateTime($resetRequest['expires_at']) < new DateTime() || $resetRequest['used_at'] !== null) {
            echo json_encode(['message' => 'Invalid or expired token.']);
            $pdo->rollBack();
            logPasswordResetEvent('invalid_token', ['token' => $token, 'reason' => 'expired_or_used', 'expires_at' => $resetRequest['expires_at'], 'used_at' => $resetRequest['used_at']]);
            // Mark token as used if it's not already, even if expired, to prevent reuse attempts
            $updateStmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = :token");
            $updateStmt->bindValue(':token', $token, PDO::PARAM_STR);
            $updateStmt->execute();
            return;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :user_id");
        $stmt->bindValue(':password', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $resetRequest['user_id'], PDO::PARAM_INT);
        $stmt->execute();

        // Mark the token as used
        $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = :token");
        $stmt->bindValue(':token', $token, PDO::PARAM_STR);
        $stmt->execute();

        $pdo->commit();
        logPasswordResetEvent('password_reset_success', ['user_id' => $resetRequest['user_id'], 'token_used' => $token]);
        echo json_encode(['message' => 'Your password has been reset successfully. You can now log in with your new password.']);

    } catch (PDOException $e) {
        $pdo->rollBack();
        logPasswordResetEvent('db_error_set_password', ['token' => $token, 'error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['message' => 'An error occurred during password reset. Please try again.']);
    }
}

// --- API Router / Entry Point ---

// This structure assumes you're routing based on query parameters or path.
// For example:
// - POST /reset.php?action=request -> handleRequestPasswordReset()
// - POST /reset.php?action=set -> handleSetNewPassword()

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'request':
        handleRequestPasswordReset();
        break;
    case 'set':
        handleSetNewPassword();
        break;
    default:
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['message' => 'Invalid action.']);
        break;
}

?>