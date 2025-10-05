<?php

// classes/Database.php
class Database {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $host = 'localhost';
            $db   = 'db_users';
            $user = 'root'; // Replace with your DB user
            $pass = 'password'; // Replace with your DB password
            $charset = 'utf8mb4';

            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$pdo = new PDO($dsn, $user, $pass, $options);
            } catch (\PDOException $e) {
                // In a production environment, log the error and
                // provide a generic message to the user.
                error_log("Database connection error: " . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again later.']);
                exit();
            }
        }
        return self::$pdo;
    }
}

// classes/Logger.php
class Logger {
    const LOG_FILE = __DIR__ . '/../logs/password_resets.log';

    public static function log(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $logMessage = "[$timestamp] [IP:$ip] $message\n";
        file_put_contents(self::LOG_FILE, $logMessage, FILE_APPEND);
    }
}

// classes/RateLimiter.php
class RateLimiter {
    const RATE_LIMIT_FILE_PATH = __DIR__ . '/../logs/rate_limits.json'; // Store rate limits temporarily
    const MAX_REQUESTS = 5; // Max requests per window
    const WINDOW_SECONDS = 3600; // 1 hour window

    public static function checkAndRecord(string $ipAddress): bool {
        $currentTime = time();
        $limits = [];

        if (file_exists(self::RATE_LIMIT_FILE_PATH)) {
            $limits = json_decode(file_get_contents(self::RATE_LIMIT_FILE_PATH), true);
            if (!is_array($limits)) {
                $limits = [];
            }
        }

        // Clean up old entries
        foreach ($limits as $ip => $requests) {
            $limits[$ip] = array_filter($requests, function($time) use ($currentTime) {
                return ($currentTime - $time) < self::WINDOW_SECONDS;
            });
            if (empty($limits[$ip])) {
                unset($limits[$ip]);
            }
        }

        // Check current IP
        $requestCount = count($limits[$ipAddress] ?? []);
        if ($requestCount >= self::MAX_REQUESTS) {
            Logger::log("Rate limit exceeded for IP: $ipAddress");
            return false;
        }

        // Record new request
        $limits[$ipAddress][] = $currentTime;
        file_put_contents(self::RATE_LIMIT_FILE_PATH, json_encode($limits));

        return true;
    }
}

// classes/EmailService.php
class EmailService {
    // In a production environment, this would use a library like PHPMailer or a transactional email service.
    // For this module, we'll simulate sending by logging the email content.
    const PASSWORD_RESET_BASE_URL = 'http://localhost/public/reset_password.php'; // Replace with your actual base URL

    public static function sendResetEmail(string $toEmail, string $token): bool {
        $resetLink = self::PASSWORD_RESET_BASE_URL . '?token=' . urlencode($token);
        $subject = 'Password Reset Request';
        $message = "You have requested a password reset. Please click the following link to reset your password: $resetLink\n\nThis link will expire in 30 minutes and can only be used once.\nIf you did not request this, please ignore this email.";
        $headers = 'From: noreply@yourapp.com' . "\r\n" .
                   'Reply-To: noreply@yourapp.com' . "\r\n" .
                   'Content-Type: text/plain; charset=UTF-8' . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();

        // Simulate sending email
        Logger::log("Simulated email sent to: $toEmail. Subject: '$subject'. Link: $resetLink");
        // For actual sending, uncomment the line below and ensure mail() is configured or use a robust library
        // return mail($toEmail, $subject, $message, $headers);
        return true; // Assume success for simulation
    }
}

// classes/PasswordResetService.php
class PasswordResetService {
    const TOKEN_EXPIRY_MINUTES = 30;

    public static function requestPasswordReset(string $email): array {
        Logger::log("Password reset request received for email: $email");

        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        if (!RateLimiter::checkAndRecord($ip)) {
            return ['success' => false, 'message' => 'Too many requests. Please try again later.'];
        }

        $pdo = Database::getConnection();
        $response = ['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.'];

        // Consistent response time to prevent timing attacks
        $startTime = microtime(true);

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $userExists = (bool)$stmt->fetch();

        // Always generate a token and attempt to store/send, even if user doesn't exist,
        // to keep response time consistent. The email will only be sent if the user exists.
        $token = bin2hex(random_bytes(32)); // Cryptographically secure token
        $expiresAt = date('Y-m-d H:i:s', time() + (self::TOKEN_EXPIRY_MINUTES * 60));
        $createdAt = date('Y-m-d H:i:s');

        // Store the token (always, but associate with email only if user exists for logging)
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, ?, ?)"
            );
            $stmt->execute([$email, $token, $expiresAt, 0, $createdAt]);
        } catch (PDOException $e) {
            Logger::log("Error storing password reset token for email $email: " . $e->getMessage());
            // Fail silently or return generic error, but still maintain timing.
            // For now, let it proceed to simulate email send logic.
        }


        if ($userExists) {
            EmailService::sendResetEmail($email, $token);
            Logger::log("Password reset link sent to $email with token: $token");
        } else {
            Logger::log("Password reset requested for non-existent email: $email");
            // Simulate email sending time for non-existent users
            usleep(rand(100000, 500000)); // 100-500ms
        }

        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;
        $minExpectedTime = 0.5; // Example minimum response time in seconds
        if ($elapsedTime < $minExpectedTime) {
            usleep(($minExpectedTime - $elapsedTime) * 1000000);
        }

        return $response;
    }

    public static function resetPassword(string $token, string $newPassword): array {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare(
            "SELECT email, expires_at, used FROM password_resets WHERE token = ?"
        );
        $stmt->execute([$token]);
        $resetRequest = $stmt->fetch();

        if (!$resetRequest || $resetRequest['used'] || strtotime($resetRequest['expires_at']) < time()) {
            Logger::log("Invalid, used, or expired password reset token: $token");
            return ['success' => false, 'message' => 'Invalid or expired token.'];
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($hashedPassword === false) {
            Logger::log("Failed to hash new password for token: $token");
            return ['success' => false, 'message' => 'Failed to process your request. Please try again.'];
        }

        try {
            $pdo->beginTransaction();

            // Update user's password
            $stmt = $pdo->prepare(
                "UPDATE users SET password = ? WHERE email = ?"
            );
            $stmt->execute([$hashedPassword, $resetRequest['email']]);

            // Mark token as used
            $stmt = $pdo->prepare(
                "UPDATE password_resets SET used = 1 WHERE token = ?"
            );
            $stmt->execute([$token]);

            $pdo->commit();
            Logger::log("Password successfully reset for email: " . $resetRequest['email'] . " using token: $token");
            return ['success' => true, 'message' => 'Your password has been reset successfully.'];
        } catch (PDOException $e) {
            $pdo->rollBack();
            Logger::log("Database error during password reset for token $token: " . $e->getMessage());
            return ['success' => false, 'message' => 'A server error occurred during password reset. Please try again.'];
        }
    }
}

// public/request_reset.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!isset($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit();
}

$email = trim($_POST['email']);

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/RateLimiter.php';
require_once __DIR__ . '/../classes/EmailService.php';
require_once __DIR__ . '/../classes/PasswordResetService.php';

$response = PasswordResetService::requestPasswordReset($email);

echo json_encode($response);

// public/reset_password.php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

if (!isset($_GET['token']) || empty($_GET['token'])) {
    echo json_encode(['success' => false, 'message' => 'Password reset token is missing.']);
    exit();
}

if (!isset($_POST['new_password']) || empty($_POST['new_password'])) {
    echo json_encode(['success' => false, 'message' => 'New password cannot be empty.']);
    exit();
}

$token = trim($_GET['token']);
$newPassword = $_POST['new_password'];

// Basic password strength check (can be expanded)
if (strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit();
}

require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Logger.php';
require_once __DIR__ . '/../classes/EmailService.php'; // Not directly used but good practice to include if part of shared module
require_once __DIR__ . '/../classes/PasswordResetService.php';

$response = PasswordResetService::resetPassword($token, $newPassword);

echo json_encode($response);
?>