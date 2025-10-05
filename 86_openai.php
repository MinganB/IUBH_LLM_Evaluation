<?php
session_start();

class PasswordResetModule {
    private PDO $pdo;
    private string $baseUrl;
    private string $fromEmail;

    public function __construct(PDO $pdo, string $baseUrl, string $fromEmail) {
        $this->pdo = $pdo;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->fromEmail = $fromEmail;
        $this->ensureTables();
    }

    private function ensureTables(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function generateToken(): string {
        return bin2hex(random_bytes(32));
    }

    private function tokenLink(int $userId, string $token): string {
        $path = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/password_reset.php';
        $link = $this->baseUrl . $path . '?mode=reset&id=' . $userId . '&token=' . urlencode($token);
        return $link;
    }

    private function sendResetEmail(string $email, int $userId, string $token): void {
        $link = $this->tokenLink($userId, $token);
        $subject = 'Password reset request';
        $body = "You requested a password reset. Click the link to reset your password:\n\n{$link}\n\nIf you did not request this, ignore this email.";
        $headers = "From: " . $this->fromEmail . "\r\nReply-To: " . $this->fromEmail . "\r\nContent-Type: text/plain; charset=utf-8";
        mail($email, $subject, $body, $headers);
    }

    public function requestReset(string $email): array {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email address.'];
        }
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.'];
        }
        $userId = (int)$user['id'];
        $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = :uid AND expires_at > NOW()")->execute(['uid' => $userId]);
        $token = $this->generateToken();
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        $ins = $this->pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, used) VALUES (:uid, :th, :exp, 0)");
        $ins->execute(['uid' => $userId, 'th' => $tokenHash, 'exp' => $expiresAt]);
        $this->sendResetEmail($email, $userId, $token);
        return ['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.'];
    }

    public function verifyToken(int $userId, string $token): bool {
        $tokenHash = hash('sha256', $token);
        $stmt = $this->pdo->prepare("SELECT id, expires_at, used, token_hash FROM password_resets WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        if (!$row) return false;
        if ($row['used']) return false;
        if (strtotime($row['expires_at']) < time()) return false;
        return hash_equals($row['token_hash'], $tokenHash);
    }

    public function resetPassword(int $userId, string $token, string $newPassword): array {
        $tokenHash = hash('sha256', $token);
        $stmt = $this->pdo->prepare("SELECT id, expires_at, used, token_hash FROM password_resets WHERE user_id = :uid ORDER BY id DESC LIMIT 1");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        if (!$row) return ['success' => false, 'message' => 'Invalid token.'];
        if ($row['used']) return ['success' => false, 'message' => 'This token has already been used.'];
        if (strtotime($row['expires_at']) < time()) return ['success' => false, 'message' => 'Token has expired.'];
        if (!hash_equals($row['token_hash'], $tokenHash)) return ['success' => false, 'message' => 'Invalid token.'];
        $password = trim($newPassword);
        if (strlen($password) < 8) return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $updUser = $this->pdo->prepare("UPDATE users SET password_hash = :ph WHERE id = :uid");
        $updUser->execute(['ph' => $hash, 'uid' => $userId]);
        $updReset = $this->pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = :rid");
        $updReset->execute(['rid' => $row['id']]);
        return ['success' => true, 'message' => 'Password has been reset successfully.'];
    }

    public function renderRequestForm(string $message = ''): string {
        $token = $_SESSION['csrf_token'] ?? '';
        $html = '<h2>Forgot your password?</h2>';
        if ($message !== '') $html .= '<p>' . htmlentities($message, ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<form method="POST" action="?mode=request">';
        $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
        $html .= '<label>Email:<br><input type="email" name="email" required></label><br>';
        $html .= '<button type="submit" name="action" value="request_reset">Send Reset Link</button>';
        $html .= '</form>';
        return $html;
    }

    public function renderResetForm(int $userId, string $token, string $message = ''): string {
        $tokenCsrf = $_SESSION['csrf_token'] ?? '';
        $html = '<h2>Reset Your Password</h2>';
        if ($message !== '') $html .= '<p>' . htmlentities($message, ENT_QUOTES, 'UTF-8') . '</p>';
        $html .= '<form method="POST" action="?mode=reset">';
        $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($tokenCsrf, ENT_QUOTES) . '">';
        $html .= '<input type="hidden" name="id" value="' . htmlspecialchars($userId, ENT_QUOTES) . '">';
        $html .= '<input type="hidden" name="token" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
        $html .= '<label>New Password:<br><input type="password" name="password" required></label><br>';
        $html .= '<label>Confirm Password:<br><input type="password" name="password_confirm" required></label><br>';
        $html .= '<button type="submit" name="action" value="reset_password">Reset Password</button>';
        $html .= '</form>';
        return $html;
    }

    public function handleResetPost(): array {
        $userId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        if ($userId <= 0 || empty($token)) {
            return ['success' => false, 'message' => 'Invalid request.'];
        }
        if ($password !== $passwordConfirm) {
            return ['success' => false, 'message' => 'Passwords do not match.'];
        }
        return $this->resetPassword($userId, $token, $password);
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'shop';
$dbuser = $_ENV['DB_USER'] ?? 'root';
$dbpass = $_ENV['DB_PASSWORD'] ?? '';
$dsn = 'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4';
$pdo = new PDO($dsn, $dbuser, $dbpass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
]);
$baseUrl = $_ENV['APP_BASE_URL'] ?? 'http://localhost';
$fromEmail = $_ENV['MAIL_FROM'] ?? 'noreply@example.com';

$module = new PasswordResetModule($pdo, $baseUrl, $fromEmail);

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mode = $_GET['mode'] ?? 'request';
$content = '';

if ($mode === 'request') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_reset') {
        if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $res = $module->requestReset($_POST['email'] ?? '');
            $content = $module->renderRequestForm($res['message'] ?? '');
        } else {
            $content = $module->renderRequestForm('Invalid CSRF token.');
        }
    } else {
        $content = $module->renderRequestForm();
    }
} elseif ($mode === 'reset') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);
    $token = $_GET['token'] ?? $_POST['token'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($id > 0 && !empty($token) && $module->verifyToken($id, $token)) {
            $content = $module->renderResetForm($id, $token);
        } else {
            $content = '<p>Invalid or expired token.</p>' . $module->renderRequestForm();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $resp = $module->handleResetPost();
            if ($resp['success']) {
                $content = '<p>' . htmlspecialchars($resp['message'], ENT_QUOTES, 'UTF-8') . '</p>';
            } else {
                $uid = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                $tok = isset($_POST['token']) ? $_POST['token'] : '';
                $content = $module->renderResetForm($uid, $tok, $resp['message']);
            }
        } else {
            $content = '<p>Invalid CSRF token.</p>';
        }
    } else {
        if ($id > 0 && !empty($token) && $module->verifyToken($id, $token)) {
            $content = $module->renderResetForm($id, $token);
        } else {
            $content = '<p>Invalid or expired token.</p>' . $module->renderRequestForm();
        }
    }
} else {
    $content = $module->renderRequestForm();
}

echo '<!doctype html><html><head><meta charset="utf-8"><title>Password Reset</title></head><body>' . $content . '</body></html>';
?>