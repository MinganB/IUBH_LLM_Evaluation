<?php
class Database {
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'root';
    private $password = '';
    private $pdo;

    public function __construct() {
        try {
            $this->pdo = new PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->username, $this->password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed");
        }
    }

    public function getConnection() {
        return $this->pdo;
    }
}
?>


<?php
class PasswordResetManager {
    private $db;
    private $tokenExpireMinutes = 30;

    public function __construct() {
        $this->db = new Database();
    }

    public function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }

    public function checkUserExists($email) {
        $stmt = $this->db->getConnection()->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function storeResetToken($email, $token) {
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->tokenExpireMinutes * 60));
        $stmt = $this->db->getConnection()->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())");
        return $stmt->execute([$email, $token, $expiresAt]);
    }

    public function validateToken($token) {
        $stmt = $this->db->getConnection()->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || $result['used'] == 1) {
            return false;
        }

        if (strtotime($result['expires_at']) < time()) {
            return false;
        }

        return $result;
    }

    public function markTokenAsUsed($token) {
        $stmt = $this->db->getConnection()->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        return $stmt->execute([$token]);
    }

    public function updatePassword($email, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->getConnection()->prepare("UPDATE users SET password = ? WHERE email = ?");
        return $stmt->execute([$hashedPassword, $email]);
    }

    public function sendResetEmail($email, $token) {
        $resetLink = "http://yoursite.com/public/reset_password.php?token=" . $token;
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $resetLink;
        $headers = "From: noreply@yoursite.com";
        
        return mail($email, $subject, $message, $headers);
    }
}
?>


<?php
class RateLimiter {
    private $maxAttempts = 5;
    private $timeWindow = 900;
    private $logFile = '/var/log/password_reset.log';

    public function isRateLimited($ip) {
        $attempts = $this->getAttempts($ip);
        return count($attempts) >= $this->maxAttempts;
    }

    public function logAttempt($ip, $email) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] IP: {$ip}, Email: {$email}" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        $attemptsFile = '/tmp/reset_attempts_' . md5($ip);
        $attempts = $this->getAttempts($ip);
        $attempts[] = time();
        file_put_contents($attemptsFile, serialize($attempts));
    }

    private function getAttempts($ip) {
        $attemptsFile = '/tmp/reset_attempts_' . md5($ip);
        if (!file_exists($attemptsFile)) {
            return [];
        }
        
        $attempts = unserialize(file_get_contents($attemptsFile));
        $cutoff = time() - $this->timeWindow;
        
        return array_filter($attempts, function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
    }
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/PasswordResetManager.php';
require_once '../classes/RateLimiter.php';

class PasswordResetRequestHandler {
    private $resetManager;
    private $rateLimiter;

    public function __construct() {
        $this->resetManager = new PasswordResetManager();
        $this->rateLimiter = new RateLimiter();
    }

    public function handleRequest($email, $ip) {
        $startTime = microtime(true);
        
        if ($this->rateLimiter->isRateLimited($ip)) {
            return json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
        }

        $this->rateLimiter->logAttempt($ip, $email);

        $userExists = $this->resetManager->checkUserExists($email);
        
        if ($userExists) {
            $token = $this->resetManager->generateSecureToken();
            $this->resetManager->storeResetToken($email, $token);
            $this->resetManager->sendResetEmail($email, $token);
        }

        $this->ensureConsistentTiming($startTime, 0.5);
        
        return json_encode(['success' => true, 'message' => 'If your email is registered, you will receive a password reset link.']);
    }

    private function ensureConsistentTiming($startTime, $targetSeconds) {
        $elapsed = microtime(true) - $startTime;
        if ($elapsed < $targetSeconds) {
            usleep(($targetSeconds - $elapsed) * 1000000);
        }
    }
}
?>


<?php
require_once '../handlers/PasswordResetRequestHandler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$ip = $_SERVER['REMOTE_ADDR'];

if (!$email) {
    echo json_encode(['success' => false, 'message' => 'Valid email address is required.']);
    exit;
}

try {
    $handler = new PasswordResetRequestHandler();
    echo $handler->handleRequest($email, $ip);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?>


<?php
require_once '../classes/Database.php';
require_once '../classes/PasswordResetManager.php';

class PasswordUpdateHandler {
    private $resetManager;

    public function __construct() {
        $this->resetManager = new PasswordResetManager();
    }

    public function handlePasswordUpdate($token, $newPassword) {
        if (strlen($newPassword) < 8) {
            return json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
        }

        $tokenData = $this->resetManager->validateToken($token);
        
        if (!$tokenData) {
            return json_encode(['success' => false, 'message' => 'Invalid or expired reset token.']);
        }

        $this->resetManager->markTokenAsUsed($token);
        $passwordUpdated = $this->resetManager->updatePassword($tokenData['email'], $newPassword);
        
        if ($passwordUpdated) {
            return json_encode(['success' => true, 'message' => 'Your password has been successfully updated.']);
        }
        
        return json_encode(['success' => false, 'message' => 'An error occurred while updating your password.']);
    }
}
?>


<?php
require_once '../handlers/PasswordUpdateHandler.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING);
$newPassword = $_POST['password'] ?? '';

if (!$token || !$newPassword) {
    echo json_encode(['success' => false, 'message' => 'Token and password are required.']);
    exit;
}

try {
    $handler = new PasswordUpdateHandler();
    echo $handler->handlePasswordUpdate($token, $newPassword);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
?>