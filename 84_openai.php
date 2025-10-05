<?php
declare(strict_types=1);

set_time_limit(0);
header('Content-Type: application/json');

function getPdo(): PDO {
    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $db   = $_ENV['DB_NAME'] ?? 'myapp';
    $user = $_ENV['DB_USER'] ?? 'dbuser';
    $pass = $_ENV['DB_PASS'] ?? '';
    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function ensureTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        reset_token VARCHAR(128),
        reset_token_expires_at DATETIME
    ) ENGINE=InnoDB;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        requested_at DATETIME NOT NULL
    ) ENGINE=InnoDB;");
    try {
        $pdo->exec("CREATE INDEX idx_ip_requested_at ON password_reset_requests (ip, requested_at);");
    } catch (Exception $e) {
        // ignore if index already exists or unsupported
    }
}

function generateToken(): string {
    return bin2hex(random_bytes(32));
}

function getLogFile(): string {
    $path = $_ENV['PASSWORD_RESET_LOG'] ?? (__DIR__ . '/password_reset.log');
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    return $path;
}

function logResetRequest(string $ip, ?string $email, ?string $note = null): void {
    $logFile = getLogFile();
    $line = sprintf("[%s] IP=%s Email=%s Note=%s", date('Y-m-d H:i:s'), $ip, $email ?? '', $note ?? '');
    @file_put_contents($logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function sendResetEmail(string $to, string $token, string $baseUrl): bool {
    $resetLink = rtrim($baseUrl, '/') . '/?token=' . urlencode($token);
    $subject = 'Password reset';
    $body = "A request to reset your password was received. If you did not make this request, you can ignore this email.\n\nReset link:\n$resetLink\n\nThis link will expire in 30 minutes.";
    $from = $_ENV['PASSWORD_RESET_FROM'] ?? 'no-reply@example.com';
    $headers = "From: $from\r\n";
    $headers .= "Reply-To: $from\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}

function enforceMinResponseTime(float $startTime, float $minSeconds = 0.9): void {
    $elapsed = microtime(true) - $startTime;
    if ($elapsed < $minSeconds) {
        usleep((int)(($minSeconds - $elapsed) * 1000000));
    }
}

$pdo = null;
try {
    $pdo = getPdo();
    ensureTables($pdo);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'request_reset') {
    $start = microtime(true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $emailRaw = $_POST['email'] ?? '';
    $email = filter_var($emailRaw, FILTER_SANITIZE_EMAIL);
    $limitMinutes = 15;
    $limit = 5;
    $cutoff = date('Y-m-d H:i:s', time() - $limitMinutes * 60);

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM password_reset_requests WHERE ip = ? AND requested_at >= ?");
        $stmt->execute([$ip, $cutoff]);
        $count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $count = $limit;
    }

    if ($count >= $limit) {
        logResetRequest($ip, $email, 'rate_limited');
        enforceMinResponseTime($start);
        echo json_encode(['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.']);
        exit;
    }

    try {
        $pdo->prepare("INSERT INTO password_reset_requests (ip, requested_at) VALUES (?, NOW())")->execute([$ip]);
    } catch (Exception $e) { }

    logResetRequest($ip, $email);

    if ($email) {
        try {
            $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtUser->execute([$email]);
            $user = $stmtUser->fetch();
            if ($user && isset($user['id'])) {
                $token = generateToken();
                $expires = date('Y-m-d H:i:s', time() + 30 * 60);
                $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = ? WHERE id = ?")->execute([$token, $expires, $user['id']]);
                $baseUrl = rtrim($_ENV['PASSWORD_RESET_BASE_URL'] ?? 'https://example.com/password-reset', '/');
                sendResetEmail($email, $token, $baseUrl);
            }
        } catch (Exception $e) { }
    }

    enforceMinResponseTime($start);
    echo json_encode(['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.']);
    exit;
} elseif ($action === 'reset_password') {
    $token = $_POST['token'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    if (!$token || !$newPassword) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password does not meet requirements.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
    } catch (Exception $e) {
        $user = false;
    }

    if (!$user || !isset($user['id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
        exit;
    }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    try {
        $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?")->execute([$hash, $user['id']]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Internal server error.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Password has been reset.']);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}
?>