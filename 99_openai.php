<?php
class Database {
    private $pdo;
    public function __construct(){
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'db_user';
        $pass = getenv('DB_PASSWORD') ?: '';
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new PDO($dsn, $user, $pass, $options);
    }
    public function getPdo(){ return $this->pdo; }
}
?> 


<?php
class User {
    private $pdo;
    public function __construct($pdo){
        $this->pdo = $pdo;
    }
    public function findByEmail($email){
        $stmt = $this->pdo->prepare('SELECT id, email, password FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    public function updatePasswordByEmail($email, $hashedPassword){
        $stmt = $this->pdo->prepare('UPDATE users SET password = ? WHERE email = ?');
        $stmt->execute([$hashedPassword, $email]);
        return $stmt->rowCount() > 0;
    }
    public function updatePasswordById($id, $hashedPassword){
        $stmt = $this->pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hashedPassword, $id]);
        return $stmt->rowCount() > 0;
    }
}
?> 


<?php
class PasswordReset {
    private $pdo;
    public function __construct($pdo){
        $this->pdo = $pdo;
    }
    public function invalidateTokensForEmail($email){
        $stmt = $this->pdo->prepare('UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0');
        $stmt->execute([$email]);
    }
    public function createToken($email){
        $this->invalidateTokensForEmail($email);
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $stmt = $this->pdo->prepare('INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())');
        $stmt->execute([$email, $token, $expiresAt]);
        return $token;
    }
    public function getTokenRow($token){
        $stmt = $this->pdo->prepare('SELECT * FROM password_resets WHERE token = ?');
        $stmt->execute([$token]);
        return $stmt->fetch();
    }
    public function markTokenUsed($token){
        $stmt = $this->pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = ?');
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }
    public function isTokenValid($token){
        $row = $this->getTokenRow($token);
        if(!$row) return false;
        if((int)$row['used'] === 1) return false;
        $expiresAt = strtotime($row['expires_at']);
        if(time() > $expiresAt) return false;
        return true;
    }
}
?> 


<?php
class RateLimiter {
    public static function isAllowed($ip, $windowSeconds = 900, $maxRequests = 5){
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $rateFile = $logDir . '/password_reset_rate_' . md5($ip) . '.json';
        $now = time();
        $windowStart = $now - $windowSeconds;
        $timestamps = [];
        if (file_exists($rateFile)) {
            $contents = @file_get_contents($rateFile);
            $data = json_decode($contents, true);
            if (is_array($data)) $timestamps = $data;
        }
        $timestamps = array_values(array_filter($timestamps, function($t) use ($windowStart) { return $t >= $windowStart; }));
        if (count($timestamps) >= $maxRequests) return false;
        $timestamps[] = $now;
        file_put_contents($rateFile, json_encode($timestamps), LOCK_EX);
        return true;
    }
}
?> 


<?php
class Logger {
    public static function log($message){
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        $logFile = $logDir . '/password_reset.log';
        $logLine = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        file_put_contents($logFile, $logLine, FILE_APPEND);
    }
}
?> 


<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/PasswordReset.php';
require_once __DIR__ . '/../classes/RateLimiter.php';
require_once __DIR__ . '/../classes/Logger.php';
header('Content-Type: application/json');
try {
    $pdo = (new Database())->getPdo();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!RateLimiter::isAllowed($ip)) {
        echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
        exit;
    }
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => true, 'message' => 'If the email is registered, a password reset link will be sent.']);
        Logger::log('Password reset request from ' . $ip . ' with invalid email input.');
        exit;
    }
    $userModel = new User($pdo);
    $user = $userModel->findByEmail($email);
    $token = null;
    if ($user) {
        $pwdReset = new PasswordReset($pdo);
        $token = $pwdReset->createToken($email);
        $subject = 'Password Reset Request';
        $headers = "From: no-reply@example.com\r\nContent-Type: text/plain; charset=UTF-8";
        $siteUrl = getenv('PASSWORD_RESET_SITE_URL') ?: 'https://example.com';
        $link = $siteUrl . '/public/reset_password.php?token=' . $token;
        $body = "If you requested a password reset, click the link to reset your password:\n\n$link\n\nThis link will expire in 30 minutes.";
        @mail($email, $subject, $body, $headers);
        Logger::log('Password reset token generated for ' . $email . ' IP: ' . $ip);
    } else {
        Logger::log('Password reset requested for non-existent email from IP: ' . $ip);
    }
    echo json_encode(['success' => true, 'message' => 'If the email is registered, a password reset link will be sent.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
?> 


<?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/PasswordReset.php';
require_once __DIR__ . '/../classes/Logger.php';
header('Content-Type: application/json');
try {
    $pdo = (new Database())->getPdo();
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if (empty($token) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request.']);
        exit;
    }
    $pwdReset = new PasswordReset($pdo);
    $row = $pwdReset->getTokenRow($token);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
        exit;
    }
    if ((int)$row['used'] === 1) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
        exit;
    }
    $expiresAt = strtotime($row['expires_at']);
    if (time() > $expiresAt) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $userModel = new User($pdo);
    $updated = $userModel->updatePasswordByEmail($row['email'], $hash);
    if ($updated) {
        $pwdReset->markTokenUsed($token);
        Logger::log('Password reset completed for ' . $row['email'] . ' Token: ' . $token);
        echo json_encode(['success' => true, 'message' => 'Password has been reset.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
?>