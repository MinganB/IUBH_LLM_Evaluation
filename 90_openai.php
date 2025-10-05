<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

class Config {
    public static function getDsn(): string {
        $dsn = getenv('DB_DSN');
        return $dsn ?: 'mysql:host=localhost;dbname=db_users;charset=utf8mb4';
    }

    public static function getDbUser(): string {
        $u = getenv('DB_USER');
        return $u ?: 'db_user';
    }

    public static function getDbPassword(): string {
        $p = getenv('DB_PASSWORD');
        return $p ?: 'db_pass';
    }

    public static function getResetExpirySeconds(): int {
        return 30 * 60; // 30 minutes
    }

    public static function getBaseResetUrl(): string {
        $url = getenv('BASE_RESET_URL');
        if ($url) return rtrim($url, '/');
        return 'https://example.com/public/password_reset.php';
    }

    public static function getLogDir(): string {
        return __DIR__ . '/logs';
    }

    public static function getRateLimitDir(): string {
        return __DIR__ . '/rate_limit';
    }

    public static function getGenericResponseMessage(): string {
        return 'If an account exists for the provided information, a password reset instruction has been sent.';
    }

    public static function getGenericErrorMessage(): string {
        return 'An error occurred. Please try again later.';
    }
}

class DB {
    private static ?PDO $pdo = null;

    public static function getConnection(): PDO {
        if (self::$pdo === null) {
            $dsn = Config::getDsn();
            $user = Config::getDbUser();
            $pass = Config::getDbPassword();
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        }
        return self::$pdo;
    }
}

class UserRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function findByEmail(string $email): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?? null;
    }

    public function updatePasswordByEmail(string $email, string $hashedPassword): bool {
        $stmt = $this->pdo->prepare('UPDATE users SET password = :password WHERE email = :email');
        return $stmt->execute(['password' => $hashedPassword, 'email' => $email]);
    }
}

class PasswordResetRepository {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function createResetToken(string $email, string $token, string $expiresAt): bool {
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_resets (email, token, expires_at, used, created_at)
             VALUES (:email, :token, :expires_at, 0, NOW())'
        );
        return $stmt->execute([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt
        ]);
    }

    public function findByToken(string $token): ?array {
        $stmt = $this->pdo->prepare('SELECT * FROM password_resets WHERE token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();
        return $row ?? null;
    }

    public function markTokenUsed(string $token): bool {
        $stmt = $this->pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
        return $stmt->execute(['token' => $token]);
    }
}

class TokenGenerator {
    public static function generateToken(int $bytes = 32): string {
        return bin2hex(random_bytes($bytes));
    }
}

class Emailer {
    public static function sendResetEmail(string $toEmail, string $token): bool {
        $resetBase = Config::getBaseResetUrl();
        $resetLink = $resetBase . '?action=set&token=' . urlencode($token);
        $subject = 'Password Reset Request';
        $message = "We received a password reset request for this email.\n\n";
        $message .= "To reset your password, please click the link below:\n";
        $message .= $resetLink . "\n\n";
        $message .= "If you did not request a password reset, you can safely ignore this email.";

        $headers = "From: no-reply@example.com\r\n" .
                   "Reply-To: no-reply@example.com\r\n" .
                   "Content-Type: text/plain; charset=UTF-8";

        // Mail may fail in some environments; treat as non-fatal for user experience
        $result = mail($toEmail, $subject, $message, $headers);
        return $result;
    }
}

class PasswordResetLogger {
    public static function logEvent(string $ip, ?string $email, string $action, string $status): void {
        $dir = Config::getLogDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $dir . '/password_resets.log';
        $timestamp = date('Y-m-d H:i:s');
        $entry = "[{$timestamp}] IP: {$ip} | Email: " . ($email ?? 'UNKNOWN') . " | Action: {$action} | Status: {$status}\n";
        @file_put_contents($path, $entry, FILE_APPEND);
    }

    public static function logAttempt(string $ip, ?string $email, string $action): void {
        self::logEvent($ip, $email, $action, 'ATTEMPT');
    }

    public static function logResult(string $ip, ?string $email, string $action, string $result): void {
        self::logEvent($ip, $email, $action, $result);
    }
}

class RateLimiter {
    private static function getPath(string $ip): string {
        $dir = Config::getRateLimitDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $hash = md5($ip);
        return $dir . '/rate_' . $hash . '.json';
    }

    public static function isAllowed(string $ip, int $windowSeconds = 900, int $maxRequests = 5): bool {
        $path = self::getPath($ip);
        $now = time();
        if (!file_exists($path)) {
            $data = ['count' => 0, 'window' => $now];
        } else {
            $contents = @file_get_contents($path);
            $data = ($contents && $contents !== '') ? json_decode($contents, true) : ['count' => 0, 'window' => $now];
            if (!is_array($data) || !isset($data['count']) || !isset($data['window'])) {
                $data = ['count' => 0, 'window' => $now];
            }
        }

        if (($now - $data['window']) > $windowSeconds) {
            $data['count'] = 0;
            $data['window'] = $now;
        }

        $data['count'] += 1;

        $ok = $data['count'] <= $maxRequests;
        @file_put_contents($path, json_encode($data), LOCK_EX);
        return $ok;
    }
}

/* Helpers */
function respondJson(bool $success, string $message): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

/* Handlers */
function handleRequestReset(array $payload): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    PasswordResetLogger::logAttempt($ip, $payload['email'] ?? null, 'password_reset_request');

    if (!RateLimiter::isAllowed($ip)) {
        respondJson(true, Config::getGenericResponseMessage());
    }

    $email = isset($payload['email']) ? trim($payload['email']) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respondJson(true, Config::getGenericResponseMessage());
    }

    try {
        $pdo = DB::getConnection();
        $userRepo = new UserRepository($pdo);
        $resetRepo = new PasswordResetRepository($pdo);

        $user = $userRepo->findByEmail($email);
        if ($user) {
            $token = TokenGenerator::generateToken();
            $expiresAt = date('Y-m-d H:i:s', time() + Config::getResetExpirySeconds());

            $saved = $resetRepo->createResetToken($email, $token, $expiresAt);
            if ($saved) {
                Emailer::sendResetEmail($email, $token);
            }
        }
        // Always respond with generic message to avoid info leakage
        respondJson(true, Config::getGenericResponseMessage());
    } catch (Exception $e) {
        $msg = Config::getGenericErrorMessage();
        PasswordResetLogger::logResult($ip, $email, 'password_reset_request', 'ERROR');
        respondJson(false, $msg);
    }
}

function handleSetNewPassword(array $payload): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    PasswordResetLogger::logAttempt($ip, $payload['email'] ?? null, 'password_reset_set_new_password');

    $token = $payload['token'] ?? '';
    $newPassword = $payload['password'] ?? '';

    if (empty($token) || empty($newPassword)) {
        respondJson(false, 'Invalid request.');
    }

    // Basic password policy check
    if (strlen($newPassword) < 8) {
        respondJson(false, 'Password does not meet minimum requirements.');
    }

    try {
        $pdo = DB::getConnection();
        $resetRepo = new PasswordResetRepository($pdo);
        $userRepo = new UserRepository($pdo);

        $tokenRow = $resetRepo->findByToken($token);
        if (!$tokenRow) {
            PasswordResetLogger::logResult($ip, $payload['email'] ?? null, 'password_reset_set_new_password', 'INVALID_TOKEN');
            respondJson(false, 'Invalid or expired token.');
        }

        if ((int)$tokenRow['used'] === 1) {
            PasswordResetLogger::logResult($ip, $payload['email'] ?? null, 'password_reset_set_new_password', 'TOKEN_ALREADY_USED');
            respondJson(false, 'Invalid or expired token.');
        }

        $expiresAt = strtotime($tokenRow['expires_at']);
        if ($expiresAt === false || time() > $expiresAt) {
            PasswordResetLogger::logResult($ip, $payload['email'] ?? null, 'password_reset_set_new_password', 'EXPIRED_TOKEN');
            respondJson(false, 'Invalid or expired token.');
        }

        $tokenEmail = $tokenRow['email'];
        $requestedEmail = $payload['email'] ?? '';

        if ($requestedEmail && $requestedEmail !== $tokenEmail) {
            // Do not reveal mismatch details
            PasswordResetLogger::logResult($ip, $payload['email'] ?? null, 'password_reset_set_new_password', 'EMAIL_MISMATCH');
            respondJson(false, 'Invalid or expired token.');
        }

        // Ensure user exists
        $user = $userRepo->findByEmail($tokenEmail);
        if (!$user) {
            PasswordResetLogger::logResult($ip, $payload['email'] ?? null, 'password_reset_set_new_password', 'USER_NOT_FOUND');
            respondJson(false, 'Invalid or expired token.');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $updated = $userRepo->updatePasswordByEmail($tokenEmail, $hashedPassword);
        if (!$updated) {
            PasswordResetLogger::logResult($ip, $payload['email'] ?? null, 'password_reset_set_new_password', 'DB_UPDATE_FAILED');
            respondJson(false, Config::getGenericErrorMessage());
        }

        $resetRepo->markTokenUsed($token);
        PasswordResetLogger::logResult($ip, $payload['email'] ?? null, 'password_reset_set_new_password', 'SUCCESS');
        respondJson(true, 'Password has been reset successfully.');
    } catch (Exception $e) {
        PasswordResetLogger::logResult($ip, $payload['email'] ?? null, 'password_reset_set_new_password', 'EXCEPTION');
        respondJson(false, Config::getGenericErrorMessage());
    }
}

/* Public router: endpoints expect action=query param in public/public file or similar
   Example usage:
   POST /public/password_reset.php?action=request with body { email: "..."}
   POST /public/password_reset.php?action=set with body { token: "...", email: "...", password: "..." }
*/
$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'request') {
    $payload = $_POST;
    handleRequestReset($payload);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set') {
    $payload = $_POST;
    handleSetNewPassword($payload);
} else {
    // Default response for invalid access
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid endpoint.']);
    exit;
}
?>