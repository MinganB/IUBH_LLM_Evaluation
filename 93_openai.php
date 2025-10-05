<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/password_reset.log';
function log_event(string $message): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $message\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
function getPdo(): PDO {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'app';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$rateDir = __DIR__ . '/rate_limit';
if (!is_dir($rateDir)) {
    mkdir($rateDir, 0700, true);
}
$now = time();
$window = 15 * 60;
$maxRequests = 5;
$rateFile = $rateDir . '/' . md5($ip) . '.json';
$history = [];
if (file_exists($rateFile)) {
    $content = @file_get_contents($rateFile);
    $history = $content ? json_decode($content, true) : [];
}
$history = array_values(array_filter($history, function($t) use ($now, $window) {
    return ($now - $t) <= $window;
}));
if (count($history) >= $maxRequests) {
    http_response_code(429);
    log_event("Rate limit exceeded for IP $ip");
    echo json_encode(['status' => 'ok', 'message' 'If an account with that email exists, a password reset link has been sent.']);
    exit;
}
$history[] = $now;
file_put_contents($rateFile, json_encode($history));

$start = microtime(true);
$email = '';
$input = file_get_contents('php://input');
if ($input) {
    $data = json_decode($input, true);
    if (is_array($data) && isset($data['email'])) {
        $email = trim(strtolower((string)$data['email']));
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email === '' && isset($_POST['email'])) {
    $email = strtolower(trim($_POST['email']));
}
$responseMessage = 'If an account with that email exists, a password reset link has been sent.';
if ($email !== '') {
    try {
        $pdo = getPdo();
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user) {
            $user_id = (int)$user['id'];
            $del = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :uid');
            $del->execute([':uid' => $user_id]);
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires_at = (new DateTime())->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s');
            $ins = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, created_at, expires_at) VALUES (:uid, :t, NOW(), :exp)');
            $ins->execute([':uid' => $user_id, ':t' => $token_hash, ':exp' => $expires_at]);
            $baseUrl = rtrim(getenv('BASE_URL') ?: 'https://example.com', '/');
            $resetLink = $baseUrl . '/reset_password.php?uid=' . urlencode((string)$user_id) . '&token=' . urlencode($token);
            $to = $email;
            $subject = 'Password Reset';
            $headers = "From: no-reply@" . parse_url($baseUrl, PHP_URL_HOST) . "\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $body = "You requested a password reset. If you did, use the following link to reset your password. This link expires in 30 minutes.\n\n" . $resetLink;
            mail($to, $subject, $body, $headers);
            log_event("Password reset token generated for user_id=$user_id from IP=$ip");
            $responseMessage = 'If an account with that email exists, a password reset link has been sent.';
        } else {
            log_event("Password reset requested for unknown email from IP=$ip");
        }
    } catch (Exception $e) {
        log_event("Password reset request error: " . $e->getMessage());
    }
}
$duration = microtime(true) - $start;
$minDuration = 0.5;
if ($duration < $minDuration) {
    usleep(($minDuration - $duration) * 1000000);
}
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => $responseMessage]);
?>


<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/password_reset.log';
function log_event(string $message): void {
    global $logFile;
    $ts = date('Y-m-d H:i:s');
    $line = "[$ts] $message\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
function getPdo(): PDO {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'app';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';
    $dsn  = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$start = microtime(true);
$input = file_get_contents('php://input');
$uid = null;
$token = '';
$newPassword = '';

if ($input) {
    $data = json_decode($input, true);
    if (is_array($data)) {
        if (isset($data['uid'])) $uid = $data['uid'];
        if (isset($data['token'])) $token = $data['token'];
        if (isset($data['password'])) $newPassword = $data['password'];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($uid) && isset($_POST['uid'])) {
    $uid = $_POST['uid'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = $_POST['token'];
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $newPassword = $_POST['password'];
}
$responseMessage = 'If the reset link is valid, your password has been updated.';
try {
    if ($uid && $token && $newPassword) {
        $pdo = getPdo();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :uid LIMIT 1');
        $stmt->execute([':uid' => (int)$uid]);
        $userExists = (bool)$stmt->fetch();
        if (!$userExists) {
            log_event("Password reset attempt with invalid user_id $uid from IP $ip");
        } else {
            $stmt = $pdo->prepare('SELECT token_hash, expires_at FROM password_reset_tokens WHERE user_id = :uid LIMIT 1');
            $stmt->execute([':uid' => (int)$uid]);
            $row = $stmt->fetch();
            if ($row) {
                $tokenHash = $row['token_hash'];
                $expiresAt = $row['expires_at'];
                $now = (new DateTime())->format('Y-m-d H:i:s');
                if (strtotime($now) <= strtotime($expiresAt) && hash_equals($tokenHash, hash('sha256', $token))) {
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare('UPDATE users SET password_hash = :pw WHERE id = :uid');
                    $upd->execute([':pw' => $passwordHash, ':uid' => (int)$uid]);
                    $del = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :uid');
                    $del->execute([':uid' => (int)$uid]);
                    log_event("Password reset successful for user_id $uid from IP $ip");
                } else {
                    log_event("Password reset token invalid or expired for user_id $uid from IP $ip");
                }
            } else {
                log_event("Password reset token not found for user_id $uid from IP $ip");
            }
        }
    } else {
        log_event("Incomplete password reset submission from IP $ip");
    }
} catch (Exception $e) {
    log_event("Password reset error: " . $e->getMessage());
}
$duration = microtime(true) - $start;
$minDuration = 0.5;
if ($duration < $minDuration) {
    usleep(($minDuration - $duration) * 1000000);
}
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['status' => 'ok', 'message' => $responseMessage]);
?>