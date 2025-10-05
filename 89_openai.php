<?php
declare(strict_types=1);

header('Content-Type: application/json');

class Database {
    private static $pdo = null;
    public static function getConnection(): \PDO {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $db   = getenv('DB_NAME') ?: 'db_users';
            $user = getenv('DB_USER') ?: 'root';
            $pass = getenv('DB_PASS') ?: '';
            $charset = 'utf8mb4';
            $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false
            ];
            self::$pdo = new \PDO($dsn, $user, $pass, $options);
        }
        return self::$pdo;
    }
}

class PasswordResetService {
    private $db;
    public function __construct() {
        $this->db = Database::getConnection();
    }
    private function generateToken(): string {
        return bin2hex(random_bytes(32));
    }
    private function buildResetLink(string $token): string {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $path = '/public/reset_password.php?token=';
        return $scheme . '://' . $host . $path . urlencode($token);
    }
    public function requestReset(string $email): array {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.'];
        }
        try {
            $stmt = $this->db->prepare("SELECT token FROM password_resets WHERE email = ? AND used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$email]);
            $token = null;
            if ($row = $stmt->fetch()) {
                $token = $row['token'];
            } else {
                $token = $this->generateToken();
                $expiresAt = (new \DateTime('+1 hour'))->format('Y-m-d H:i:s');
                $ins = $this->db->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())");
                $ins->execute([$email, $token, $expiresAt]);
            }
            $resetLink = $this->buildResetLink($token);
            $subject = 'Password reset request';
            $body = "A password reset has been requested for this email. If you did not request, please ignore.\n\nTo reset your password, visit: $resetLink";
            @mail($email, $subject, $body, 'From: no-reply@example.com');
        } catch (\Exception $e) {
        }
        return ['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.'];
    }
    public function resetPassword(string $token, string $password, string $passwordConfirm): array {
        if ($password !== $passwordConfirm) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }
        try {
            $stmt = $this->db->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ? LIMIT 1");
            $stmt->execute([$token]);
            $row = $stmt->fetch();
            if (!$row) {
                return ['success' => false, 'message' => 'Invalid or expired token.'];
            }
            if ((int)$row['used'] === 1) {
                return ['success' => false, 'message' => 'Token has already been used.'];
            }
            $expiresAt = new \DateTime($row['expires_at']);
            $now = new \DateTime();
            if ($now > $expiresAt) {
                return ['success' => false, 'message' => 'Token has expired.'];
            }
            $email = $row['email'];
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $u = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $u->execute([$email]);
            if ($u->rowCount() > 0) {
                $updateUser = $this->db->prepare("UPDATE users SET password = ? WHERE email = ?");
                $updateUser->execute([$hash, $email]);
            }
            $mark = $this->db->prepare("UPDATE password_resets SET used = 1, used_at = NOW() WHERE token = ?");
            $mark->execute([$token]);
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'An error occurred.'];
        }
        return ['success' => true, 'message' => 'Password has been reset successfully.'];
    }
}

$service = new PasswordResetService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'request_reset') {
        $email = $_POST['email'] ?? '';
        $result = $service->requestReset($email);
        echo json_encode($result);
        exit;
    } elseif ($action === 'reset_password') {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $result = $service->resetPassword($token, $password, $passwordConfirm);
        echo json_encode($result);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        exit;
    }
} else {
    if (isset($_GET['token'])) {
        $token = $_GET['token'];
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            $row = $stmt->fetch();
            if ($row && (int)$row['used'] === 0) {
                $expiresAt = new \DateTime($row['expires_at']);
                $now = new \DateTime();
                if ($now <= $expiresAt) {
                    echo json_encode(['success' => true, 'email' => $row['email'], 'message' => 'Token is valid']);
                    exit;
                }
            }
        } catch (\Exception $e) {
        }
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'No action specified']);
        exit;
    }
}
?>