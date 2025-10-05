<?php
class PasswordResetService
{
    private PDO $pdo;
    private string $resetBaseUrl;
    private int $expiryMinutes;
    private ?callable $mailer;

    public function __construct(PDO $pdo, string $resetBaseUrl, int $expiryMinutes = 60, ?callable $mailer = null)
    {
        $this->pdo = $pdo;
        $this->resetBaseUrl = rtrim($resetBaseUrl, '/') . '/';
        $this->expiryMinutes = max(1, $expiryMinutes);
        $this->mailer = $mailer;
        $this->ensureTables();
    }

    private function ensureTables(): void
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS password_resets (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NOT NULL,
            token VARCHAR(128) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_token (token),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ";
        try {
            $this->pdo->exec($sql);
        } catch (Throwable $e) {
            $alt = "
            CREATE TABLE IF NOT EXISTS password_resets (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                token VARCHAR(128) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_token (token),
                INDEX idx_user (user_id)
            )";
            $this->pdo->exec($alt);
        }
    }

    public function requestReset(string $email): bool
    {
        $email = trim(strtolower($email));
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return true;
        }
        $userId = (int)$user['id'];
        $token = bin2hex(random_bytes(32));
        $expiresAt = (new DateTime("+{$this->expiryMinutes} minutes"))->format('Y-m-d H:i:s');
        $insert = $this->pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $insert->execute([$userId, $token, $expiresAt]);
        $resetLink = $this->resetBaseUrl . '?token=' . urlencode($token);
        $subject = "Password reset request";
        $body = "A password reset was requested for your account.\n\nPlease visit the following link to set a new password:\n{$resetLink}\n\nThis link will expire in {$this->expiryMinutes} minutes.\nIf you did not request this, you can ignore this email.";
        $this->sendEmail($email, $subject, $body);
        return true;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                SELECT pr.id AS pr_id, pr.user_id, pr.expires_at
                FROM password_resets pr
                WHERE pr.token = ? AND pr.used_at IS NULL
                FOR UPDATE
            ");
            $stmt->execute([$token]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$record) {
                $this->pdo->rollBack();
                return false;
            }
            $expiresAt = new DateTime($record['expires_at']);
            if ($expiresAt < new DateTime()) {
                $this->pdo->rollBack();
                return false;
            }
            if (strlen($newPassword) < 8) {
                $this->pdo->rollBack();
                return false;
            }
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateUser = $this->pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $updateUser->execute([$hash, (int)$record['user_id']]);
            $updateToken = $this->pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
            $updateToken->execute([$token]);
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    private function sendEmail(string $to, string $subject, string $body): void
    {
        if ($this->mailer) {
            call_user_func($this->mailer, $to, $subject, $body);
            return;
        }
        $headers = "From: no-reply@example.com\r\n" .
                   "Reply-To: no-reply@example.com\r\n" .
                   "Content-Type: text/plain; charset=UTF-8\r\n";
        mail($to, $subject, $body, $headers);
    }
}
?>