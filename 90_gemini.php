<?php
// config.php
// This file would typically be located in a directory above the web root for production security.
// For the purpose of this module, it is assumed to be available to handler files.

define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Path to the log file for password reset requests
// Ensure this directory exists and is writable by the web server.
define('LOG_FILE_PATH', __DIR__ . '/../logs/password_reset.log'); 

// Token expiration time in minutes
define('TOKEN_EXPIRATION_MINUTES', 30);

// Base URL for the password reset link sent in the email
// This should point to your front-end page where the user enters the new password.
define('SITE_URL', 'http://localhost/public/reset_password.html');

// Rate limiting settings for the password reset request endpoint
// Interval in seconds during which requests are counted
define('RATE_LIMIT_INTERVAL_SECONDS', 300); // 5 minutes
// Maximum number of requests allowed from a single IP address within the interval
define('RATE_LIMIT_MAX_REQUESTS', 5);
?>
<?php
// classes/Database.php

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
            exit();
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection(): PDO {
        return $this->pdo;
    }
}
?>
<?php
// classes/RateLimiter.php

class RateLimiter {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function checkRateLimit(string $ipAddress, int $intervalSeconds, int $maxRequests): bool {
        try {
            $currentTime = new DateTime();
            $stmt = $this->pdo->prepare("SELECT requests_count, last_request_at FROM rate_limits WHERE ip_address = ?");
            $stmt->execute([$ipAddress]);
            $row = $stmt->fetch();
            
            if ($row) {
                $lastRequestTime = new DateTime($row['last_request_at']);
                $requestsCount = (int)$row['requests_count'];

                $timeDiff = $currentTime->getTimestamp() - $lastRequestTime->getTimestamp();

                if ($timeDiff >= $intervalSeconds) {
                    $stmt = $this->pdo->prepare("UPDATE rate_limits SET requests_count = 1, last_request_at = ? WHERE ip_address = ?");
                    $stmt->execute([$currentTime->format('Y-m-d H:i:s'), $ipAddress]);
                    return true;
                } else {
                    if ($requestsCount >= $maxRequests) {
                        return false; 
                    } else {
                        $stmt = $this->pdo->prepare("UPDATE rate_limits SET requests_count = requests_count + 1, last_request_at = ? WHERE ip_address = ?");
                        $stmt->execute([$currentTime->format('Y-m-d H:i:s'), $ipAddress]);
                        return true;
                    }
                }
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO rate_limits (ip_address, requests_count, last_request_at) VALUES (?, 1, ?)");
                $stmt->execute([$ipAddress, $currentTime->format('Y-m-d H:i:s')]);
                return true;
            }
        } catch (PDOException $e) {
            error_log('RateLimiter database error: ' . $e->getMessage());
            return false;
        }
    }
}
?>
<?php
// classes/PasswordResetManager.php

class PasswordResetManager {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    private function generateToken(): string {
        return bin2hex(random_bytes(32)); 
    }

    private function logRequest(string $email, string $ipAddress): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf("[%s] IP: %s | Email: %s\n", $timestamp, $ipAddress, $email);
        file_put_contents(LOG_FILE_PATH, $logMessage, FILE_APPEND | LOCK_EX);
    }

    private function sendResetEmail(string $email, string $token): void {
        $resetLink = SITE_URL . '?email=' . urlencode($email) . '&token=' . urlencode($token);
        // In a production environment, this would use a robust email sending library (e.g., PHPMailer, Symfony Mailer)
        // and appropriate SMTP configuration.
        // For this module, we simulate sending the email by logging the link.
        error_log('Simulated password reset email sent to ' . $email . ' with link: ' . $resetLink);
        // Example for actual email sending (requires setup):
        // $subject = 'Password Reset Request';
        // $message = 'Hello, you requested a password reset. Please click the following link to reset your password: ' . $resetLink;
        // $headers = 'From: noreply@yourecommerce.com' . "\r\n" . 'Reply-To: noreply@yourecommerce.com' . "\r\n" . 'X-Mailer: PHP/' . phpversion();
        // mail($email, $subject, $message, $headers);
    }

    public function requestPasswordReset(string $email, string $ipAddress): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logRequest($email, $ipAddress); 
            return ['success' => true, 'message' => 'If an account with that email address exists, a password reset link has been sent.'];
        }

        try {
            $token = $this->generateToken();
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . TOKEN_EXPIRATION_MINUTES . ' minutes'));

            // Store token regardless of user existence to mitigate timing attacks
            $stmt = $this->pdo->prepare(
                "INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())"
            );
            $stmt->execute([$email, $token, $expiresAt]);

            $this->logRequest($email, $ipAddress);

            // Check if user exists. This is done after token creation for consistent response time.
            $userCheckStmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $userCheckStmt->execute([$email]);
            $userExists = $userCheckStmt->fetch();

            if ($userExists) {
                $this->sendResetEmail($email, $token);
            }

            return ['success' => true, 'message' => 'If an account with that email address exists, a password reset link has been sent.'];

        } catch (PDOException $e) {
            error_log('PasswordResetManager requestPasswordReset PDO error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred. Please try again.'];
        } catch (Exception $e) {
            error_log('PasswordResetManager requestPasswordReset generic error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred. Please try again.'];
        }
    }

    public function resetPassword(string $email, string $token, string $newPassword): array {
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
             return ['success' => false, 'message' => 'Invalid email address provided.'];
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT id FROM password_resets WHERE email = ? AND token = ? AND expires_at > NOW() AND used = 0"
            );
            $stmt->execute([$email, $token]);
            $resetEntry = $stmt->fetch();

            if (!$resetEntry) {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Invalid or expired password reset token.'];
            }

            $updateTokenStmt = $this->pdo->prepare(
                "UPDATE password_resets SET used = 1 WHERE id = ?"
            );
            $updateTokenStmt->execute([$resetEntry['id']]);

            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            if ($hashedPassword === false) {
                 throw new Exception("Password hashing failed.");
            }

            $updateUserStmt = $this->pdo->prepare(
                "UPDATE users SET password = ? WHERE email = ?"
            );
            $updateUserStmt->execute([$hashedPassword, $email]);

            $this->pdo->commit();
            return ['success' => true, 'message' => 'Your password has been reset successfully.'];

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log('PasswordResetManager resetPassword PDO error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred during password reset.'];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log('PasswordResetManager resetPassword generic error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'An unexpected error occurred. Please try again.'];
        }
    }
}
?>
<?php
// handlers/request_reset.php

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/Database.php';
require_once dirname(__DIR__) . '/classes/RateLimiter.php';
require_once dirname(__DIR__) . '/classes/PasswordResetManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';

$ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $rateLimiter = new RateLimiter($pdo);
    if (!$rateLimiter->checkRateLimit($ipAddress, RATE_LIMIT_INTERVAL_SECONDS, RATE_LIMIT_MAX_REQUESTS)) {
        http_response_code(429);
        echo json_encode(['success' => false, 'message' => 'Too many password reset requests. Please wait and try again.']);
        exit();
    }

    $passwordResetManager = new PasswordResetManager($pdo);
    $response = $passwordResetManager->requestPasswordReset($email, $ipAddress);
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log('Error in request_reset.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
}
?>
<?php
// handlers/reset_password.php

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/classes/Database.php';
require_once dirname(__DIR__) . '/classes/PasswordResetManager.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$token = $input['token'] ?? '';
$newPassword = $input['new_password'] ?? '';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    $passwordResetManager = new PasswordResetManager($pdo);
    $response = $passwordResetManager->resetPassword($email, $token, $newPassword);

    echo json_encode($response);

} catch (Exception $e) {
    error_log('Error in reset_password.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
}
?>