<?php
function respondJson($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function getClientIP() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim(array_shift($parts));
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    return $ip;
}
function getPDO() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $dsn = getenv('DB_DSN') ?: 'mysql:host=localhost;dbname=ecommerce;charset=utf8mb4';
    $dbUser = getenv('DB_USER') ?: 'dbuser';
    $dbPass = getenv('DB_PASS') ?: 'dbpass';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
    } catch (Exception $e) {
        http_response_code(500);
        exit;
    }
    return $pdo;
}
function ensureTables(PDO $pdo) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS password_reset_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            action VARCHAR(32) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    ];
    foreach ($queries as $sql) {
        $pdo->exec($sql);
    }
}
function logEvent($message) {
    $logFile = __DIR__ . '/password_reset.log';
    $entry = '[' . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
function sendEmail($to, $subject, $body) {
    $headers = "From: no-reply@{$_SERVER['HTTP_HOST']}\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    return mail($to, $subject, $body, $headers);
}
function generateToken() {
    return bin2hex(random_bytes(32));
}
function handleRequestReset(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respondJson(['status' => 'error', 'message' => 'Method not allowed'], 405);
    }
    $ip = getClientIP();
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    logEvent("Password reset request attempt from IP $ip for Email '$email'");

    $MAX_REQUESTS = 5;
    $WINDOW_MINUTES = 15;

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM password_reset_attempts WHERE ip = ? AND action = 'reset_request' AND created_at >= (NOW() - INTERVAL :mins MINUTE)");
        $stmt->bindValue(':mins', $WINDOW_MINUTES, PDO::PARAM_INT);
        $stmt->execute(['ip' => $ip]);
        $result = $stmt->fetch();
        $count = $result['c'] ?? 0;

        if ((int)$count >= $MAX_REQUESTS) {
            respondJson(['status' => 'ok', 'message' => 'If an account exists, a password reset link will be sent.']);
        }

        $insert = $pdo->prepare("INSERT INTO password_reset_attempts (ip, action, created_at) VALUES (?, 'reset_request', NOW())");
        $insert->execute([$ip]);

        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $userStmt->execute([$email]);
            $user = $userStmt->fetch();
            if ($user && !empty($user['id'])) {
                $userId = (int)$user['id'];
                $token = generateToken();
                $tokenHash = hash('sha256', $token);
                $expiresAt = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');
                $insertReset = $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())");
                $insertReset->execute([$userId, $tokenHash, $expiresAt]);

                $baseUrl = rtrim(getenv('APP_BASE_URL') ?: 'https://example.com', '/');
                $resetLink = $baseUrl . '/password-reset/confirm?uid=' . urlencode((string)$userId) . '&token=' . urlencode($token);
                $subject = 'Password Reset Request';
                $body = "To reset your password, use the following link:\n\n$resetLink\n\nThis link will expire in 30 minutes.";
                @sendEmail($email, $subject, $body);
            }
        }

        respondJson(['status' => 'ok', 'message' => 'If an account exists, a password reset link will be sent.']);
    } catch (Exception $e) {
        respondJson(['status' => 'ok', 'message' => 'If an account exists, a password reset link will be sent.']);
    }
}
function handleResetConfirm(PDO $pdo) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respondJson(['status' => 'error', 'message' => 'Method not allowed'], 405);
    }
    $uid = isset($_POST['uid']) ? (int)$_POST['uid'] : 0;
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if ($uid <= 0 || empty($token) || empty($password)) {
        respondJson(['status' => 'error', 'message' => 'Invalid request'], 400);
    }

    try {
        $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
        $userStmt->execute([$uid]);
        $user = $userStmt->fetch();
        if (!$user) {
            respondJson(['status' => 'error', 'message' => 'Invalid token or user'], 400);
        }

        $tokenHash = hash('sha256', $token);
        $resetStmt = $pdo->prepare("SELECT id, expires_at, used FROM password_resets WHERE user_id = ? AND token_hash = ? LIMIT 1");
        $resetStmt->execute([$uid, $tokenHash]);
        $row = $resetStmt->fetch();
        if (!$row) {
            respondJson(['status' => 'error', 'message' => 'Invalid or expired token'], 400);
        }

        if ((int)$row['used'] === 1) {
            respondJson(['status' => 'error', 'message' => 'Invalid or expired token'], 400);
        }

        $expiresAt = DateTime::createFromFormat('Y-m-d H:i:s', $row['expires_at']);
        if ($expiresAt === false || $expiresAt < new DateTime()) {
            respondJson(['status' => 'error', 'message' => 'Invalid or expired token'], 400);
        }

        if (strlen($password) < 8) {
            respondJson(['status' => 'error', 'message' => 'Password does not meet requirements'], 400);
        }

        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $updateUser = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $updateUser->execute([$newHash, $uid]);
        $updateReset = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
        $updateReset->execute([$row['id']]);

        respondJson(['status' => 'ok', 'message' => 'Password has been reset.']);
    } catch (Exception $e) {
        respondJson(['status' => 'error', 'message' => 'Invalid token or error occurred'], 400);
    }
}
$pdo = getPDO();
ensureTables($pdo);
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if (strpos($path, '/password-reset/') === 0) {
    if ($path === '/password-reset/request') {
        handleRequestReset($pdo);
    } elseif ($path === '/password-reset/confirm') {
        handleResetConfirm($pdo);
    } else {
        respondJson(['status' => 'error', 'message' => 'Not Found'], 404);
    }
} else {
    respondJson(['status' => 'error', 'message' => 'Endpoint not found'], 404);
}
?>