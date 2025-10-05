<?php
class Database {
    private static $pdo = null;
    public static function getConnection() {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $db = getenv('DB_NAME') ?: 'db_users';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            self::$pdo = new PDO($dsn, $user, $pass, $options);
        }
        return self::$pdo;
    }
}
?><?php
require_once __DIR__ . '/../classes/Database.php';
class PasswordResetHandler {
    private $db;
    private $baseUrl;
    public function __construct() {
        $this->db = Database::getConnection();
        $this->baseUrl = $this->determineBaseUrl();
    }

    private function determineBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
        $scriptDir = rtrim($scriptDir, '/');
        if ($scriptDir === '' || $scriptDir === '/' ) {
            $base = $protocol . '://' . $host;
        } else {
            $base = $protocol . '://' . $host . $scriptDir;
        }
        return $base;
    }

    public function requestReset(string $email): array {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success'=>false, 'message'=>'Invalid email address.'];
        }
        $stmt = $this->db->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email'=>$email]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['success'=>true, 'message'=>'If the email exists, a password reset link has been sent.'];
        }
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
        $insert = $this->db->prepare('INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, NOW())');
        $insert->execute([':email'=>$email, ':token'=>$token, ':expires_at'=>$expiresAt]);
        $resetLink = $this->baseUrl . '/public/reset_password.php?token=' . urlencode($token);
        $subject = 'Password Reset Request';
        $message = "We received a password reset request for your account.\n\nPlease click the link below to reset your password:\n$resetLink\n\nIf you did not request this, please ignore this email.";
        $headers = 'From: no-reply@' . $_SERVER['HTTP_HOST'] . "\r\n" .
                   'Reply-To: no-reply@' . $_SERVER['HTTP_HOST'] . "\r\n" .
                   'Content-Type: text/plain; charset=utf-8';
        mail($email, $subject, $message, $headers);
        return ['success'=>true, 'message'=>'If the email exists, a password reset link has been sent.'];
    }

    public function validateToken(string $token): ?string {
        $stmt = $this->db->prepare('SELECT email, expires_at, used FROM password_resets WHERE token = :token LIMIT 1');
        $stmt->execute([':token'=>$token]);
        $row = $stmt->fetch();
        if (!$row) return null;
        if ($row['used'] == 1) return null;
        $expiresAt = DateTime::createFromFormat('Y-m-d H:i:s', $row['expires_at']);
        if (!$expiresAt || $expiresAt < new DateTime()) {
            return null;
        }
        return $row['email'];
    }

    public function resetPassword(string $token, string $newPassword): array {
        $email = $this->validateToken($token);
        if (!$email) {
            return ['success'=>false, 'message'=>'Invalid or expired token.'];
        }
        if (strlen($newPassword) < 8) {
            return ['success'=>false, 'message'=>'Password must be at least 8 characters.'];
        }
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateUser = $this->db->prepare('UPDATE users SET password = :password WHERE email = :email');
        $updateUser->execute([':password'=>$hash, ':email'=>$email]);
        $markUsed = $this->db->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
        $markUsed->execute([':token'=>$token]);
        return ['success'=>true, 'message'=>'Password has been updated successfully.'];
    }
}
?><?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../handlers/PasswordResetHandler.php';
header('Content-Type: application/json');
$handler = new PasswordResetHandler();
$input = json_decode(file_get_contents('php://input'), true);
$email = $_POST['email'] ?? ($input['email'] ?? null);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($email)) {
    echo json_encode(['success'=>false, 'message'=>'Invalid request.']); 
    http_response_code(400);
    exit;
}
$result = $handler->requestReset($email);
echo json_encode($result);
?><?php
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../handlers/PasswordResetHandler.php';
header('Content-Type: application/json');
$handler = new PasswordResetHandler();
$input = json_decode(file_get_contents('php://input'), true);
$token = $_POST['token'] ?? ($input['token'] ?? $_GET['token'] ?? null);
$password = $_POST['password'] ?? ($input['password'] ?? null);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($token) || empty($password)) {
    echo json_encode(['success'=>false, 'message'=>'Invalid request.']); 
    http_response_code(400);
    exit;
}
$result = $handler->resetPassword($token, $password);
echo json_encode($result);
?>