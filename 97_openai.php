<?php
// request_password_reset.php
header('Content-Type: application/json');

class Database {
    private $pdo;
    public function __construct() {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbName = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    public function getConnection() {
        return $this->pdo;
    }
}

try {
    $emailInput = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $emailInput = isset($_POST['email']) ? trim($_POST['email']) : '';
        if (!empty($emailInput) && !filter_var($emailInput, FILTER_VALIDATE_EMAIL)) {
            $emailInput = '';
        }
    }
    if (empty($emailInput)) {
        echo json_encode(['success' => true, 'message' => 'If the email exists, a password reset link has been sent.']);
        exit;
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $stmtUser = $pdo->prepare('SELECT id, email FROM users WHERE email = :email LIMIT 1');
    $stmtUser->execute([':email' => $emailInput]);
    $user = $stmtUser->fetch();

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $stmtInsert = $pdo->prepare('INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, NOW())');
    $stmtInsert->execute([':email' => $emailInput, ':token' => $token, ':expires_at' => $expiresAt]);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $resetLink = $protocol . '://' . $host . '/public/reset_password.php?token=' . urlencode($token);

    $to = $emailInput;
    $subject = 'Password Reset Request';
    $message = "We received a password reset request for your account. To reset your password, click the link below:\n\n$resetLink\n\nIf you did not request this, please ignore.";
    $headers = "From: no-reply@example.com\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    @mail($to, $subject, $message, $headers);

    echo json_encode(['success' => true, 'message' => 'If the email exists, a password reset link has been sent.']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}
?>


<?php
// reset_password.php
header('Content-Type: application/json');

class Database {
    private $pdo;
    public function __construct() {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $dbName = getenv('DB_NAME') ?: 'db_users';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
    public function getConnection() {
        return $this->pdo;
    }
}

function respond($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, 'Invalid request method.');
    }

    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($token) || empty($password)) {
        respond(false, 'Missing token or password.');
    }

    $password = trim($password);
    if (strlen($password) < 8) {
        respond(false, 'Password must be at least 8 characters.');
    }

    $db = new Database();
    $pdo = $db->getConnection();

    $stmtReset = $pdo->prepare('SELECT email, token, expires_at, used FROM password_resets WHERE token = :token LIMIT 1');
    $stmtReset->execute([':token' => $token]);
    $reset = $stmtReset->fetch();

    if (!$reset) {
        respond(false, 'Invalid or expired token.');
    }

    if ((int)$reset['used'] === 1) {
        respond(false, 'Token has already been used.');
    }

    $expiresAt = strtotime($reset['expires_at']);
    if ($expiresAt === false || time() > $expiresAt) {
        respond(false, 'Token has expired.');
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);

    $stmtUpdateUser = $pdo->prepare('UPDATE users SET password = :pwd WHERE email = :email');
    $stmtUpdateUser->execute([':pwd' => $hashed, ':email' => $reset['email']]);

    $stmtMarkUsed = $pdo->prepare('UPDATE password_resets SET used = 1, updated_at = NOW() WHERE token = :token');
    $stmtMarkUsed->execute([':token' => $token]);

    respond(true, 'Password has been reset successfully.');
} catch (Exception $e) {
    respond(false, 'Internal server error.');
}
?>