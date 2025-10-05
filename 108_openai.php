<?php
declare(strict_types=1);

function getPDO(): PDO {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'db_users';
    $user = getenv('DB_USER') ?: 'db_user';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function respond_json(bool $success, string $message): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function generate_token(): string {
    return bin2hex(random_bytes(16));
}

function log_password_reset_request(string $ip, string $email): void {
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) { mkdir($logDir, 0777, true); }
    $logFile = $logDir . '/password_reset.log';
    $entry = json_encode(['time' => date('c'), 'ip' => $ip, 'email' => $email, 'action' => 'password_reset_request']);
    $fp = fopen($logFile, 'a');
    if ($fp) {
        if (flock($fp, LOCK_EX)) {
            fwrite($fp, $entry . PHP_EOL);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }
}

function is_rate_limited(string $ip): bool {
    $logDir = __DIR__ . '/../../logs';
    $logFile = $logDir . '/password_reset.log';
    $limit = 5;
    $window = 3600;
    if (!is_file($logFile)) { return false; }
    $now = time();
    $count = 0;
    $fp = fopen($logFile, 'r');
    if (!$fp) { return false; }
    if (flock($fp, LOCK_SH)) {
        while (($line = fgets($fp)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $data = json_decode($line, true);
            if (!$data) continue;
            if (isset($data['ip']) && $data['ip'] === $ip && isset($data['time'])) {
                $t = strtotime($data['time']);
                if ($t !== false && ($now - $t) <= $window) {
                    $count++;
                    if ($count >= $limit) {
                        flock($fp, LOCK_UN);
                        fclose($fp);
                        return true;
                    }
                }
            }
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond_json(false, 'Invalid request.');
    }
    if (is_rate_limited($_SERVER['REMOTE_ADDR'])) {
        respond_json(false, 'Too many requests. Please try again later.');
    }
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $userExists = (bool)$stmt->fetch();

        $token = generate_token();
        $expiresAt = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');
        log_password_reset_request($_SERVER['REMOTE_ADDR'], $email);

        if ($userExists) {
            $stmt = $pdo->prepare('INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, NOW())');
            $stmt->execute(['email' => $email, 'token' => $token, 'expires_at' => $expiresAt]);

            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $link = $scheme . '://' . $host . '/public/reset_password.php?token=' . $token;

            $subject = 'Password reset request';
            $message = "To reset your password, click the link below:\n\n$link\n\nThis link expires in 30 minutes.";
            $headers = 'From: no-reply@' . $host . "\r\n";
            @mail($email, $subject, $message, $headers);
        }

        respond_json(true, 'If an account with that email exists, a password reset link has been sent.');
    } catch (Exception $e) {
        respond_json(false, 'An error occurred. Please try again later.');
    }
} else {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Request Password Reset</title>
    </head>
    <body>
        <h2>Request Password Reset</h2>
        <form method="POST" action="request_reset.php">
            <label>Email:</label>
            <input type="email" name="email" required />
            <button type="submit">Send Reset Link</button>
        </form>
    </body>
    </html>
    <?php
}
?>


<?php
declare(strict_types=1);

function getPDO(): PDO {
    $host = getenv('DB_HOST') ?: 'localhost';
    $db   = getenv('DB_NAME') ?: 'db_users';
    $user = getenv('DB_USER') ?: 'db_user';
    $pass = getenv('DB_PASS') ?: '';
    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    return new PDO($dsn, $user, $pass, $options);
}

function respond_json(bool $success, string $message): void {
    header('Content-Type: application/json');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (empty($token)) {
        respond_json(false, 'Invalid request.');
    }

    if ($password !== $confirm || empty($password)) {
        respond_json(false, 'Passwords do not match.');
    }

    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT email, expires_at, used FROM password_resets WHERE token = :token LIMIT 1');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row || (int)$row['used'] === 1) {
            respond_json(false, 'Invalid or expired token.');
        }

        $expiresAt = new DateTime($row['expires_at']);
        $now = new DateTime();
        if ($now > $expiresAt) {
            respond_json(false, 'Invalid or expired token.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE email = :email');
        $stmt->execute(['password' => $hash, 'email' => $row['email']]);

        $stmt = $pdo->prepare('UPDATE password_resets SET used = 1 WHERE token = :token');
        $stmt->execute(['token' => $token]);

        respond_json(true, 'Password has been reset.');
    } catch (Exception $e) {
        respond_json(false, 'An error occurred. Please try again later.');
    }
} else {
    $token = isset($_GET['token']) ? $_GET['token'] : '';
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Reset Password</title>
    </head>
    <body>
        <h2>Reset Password</h2>
        <form method="POST" action="reset_password.php">
            <label>New Password:</label>
            <input type="password" name="password" required />
            <br/>
            <label>Confirm Password:</label>
            <input type="password" name="confirm_password" required />
            <br/>
            <label>Token:</label>
            <input type="text" name="token" required value="<?php echo htmlspecialchars($token); ?>" />
            <br/>
            <button type="submit">Reset Password</button>
        </form>
    </body>
    </html>
    <?php
}
?>