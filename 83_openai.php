<?php
class PasswordResetModule {
    private PDO $pdo;
    private string $resetLinkBase;
    private int $tokenTtl;
    private int $tokenBytes;

    public function __construct(PDO $pdo, string $resetLinkBase, int $tokenTtlSeconds = 3600, int $tokenBytes = 32) {
        $this->pdo = $pdo;
        $this->resetLinkBase = rtrim($resetLinkBase, '/');
        $this->tokenTtl = $tokenTtlSeconds;
        $this->tokenBytes = $tokenBytes;
    }

    public function initializeSchema(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS password_resets (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
    }

    public function requestReset(string $email): bool {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $userId = (int)$row['id'];
                $this->pdo->prepare("DELETE FROM password_resets WHERE user_id = :uid")->execute([':uid' => $userId]);

                $token = bin2hex(random_bytes($this->tokenBytes));
                $tokenHash = hash('sha256', $token);
                $now = (new DateTime())->format('Y-m-d H:i:s');
                $expiresAt = (new DateTime("+{$this->tokenTtl} seconds"))->format('Y-m-d H:i:s');

                $ins = $this->pdo->prepare("
                    INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
                    VALUES (:uid, :th, :exp, :now)
                ");
                $ins->execute([':uid' => $userId, ':th' => $tokenHash, ':exp' => $expiresAt, ':now' => $now]);

                $this->pdo->commit();

                $this->sendResetEmail($email, $token);
            } else {
                $this->pdo->commit();
            }

            return true;
        } catch (Throwable $e) {
            try { $this->pdo->rollBack(); } catch (Throwable $e2) {}
            return false;
        }
    }

    public function resetPassword(string $token, string $password, string $passwordConfirm): bool {
        if ($password !== $passwordConfirm) return false;
        if (strlen($password) < 8) return false;
        if (empty($token)) return false;

        $tokenHash = hash('sha256', $token);

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                SELECT pr.user_id
                FROM password_resets pr
                WHERE pr.token_hash = :th
                  AND pr.expires_at > NOW()
                  AND pr.used_at IS NULL
                LIMIT 1
            ");
            $stmt->execute([':th' => $tokenHash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $this->pdo->rollBack();
                return false;
            }

            $userId = (int)$row['user_id'];
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            $upd = $this->pdo->prepare("UPDATE users SET password_hash = :ph, updated_at = NOW() WHERE id = :uid");
            $upd->execute([':ph' => $passwordHash, ':uid' => $userId]);

            $now = (new DateTime())->format('Y-m-d H:i:s');
            $mark = $this->pdo->prepare("UPDATE password_resets SET used_at = :now WHERE token_hash = :th");
            $mark->execute([':now' => $now, ':th' => $tokenHash]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            try { $this->pdo->rollBack(); } catch (Throwable $e2) {}
            return false;
        }
    }

    private function sendResetEmail(string $toEmail, string $token): void {
        $subject = "Password reset request";
        $link = $this->resetLinkBase . "?token=" . urlencode($token);
        $message = "We received a password reset request for this account.\n\n" .
                   "Reset your password using the link below:\n" .
                   $link . "\n\n" .
                   "If you did not request this, you can ignore this email.";
        $headers = "From: no-reply@example.com\r\n" .
                   "Content-Type: text/plain; charset=utf-8\r\n";
        mail($toEmail, $subject, $message, $headers);
    }
}
?>