<?php

class Database {
    private string $host = 'localhost';
    private string $db_name = 'db_users';
    private string $username = 'root';
    private string $password = '';
    private ?PDO $conn;

    public function __construct() {
        $this->conn = null;
    }

    public function getConnection(): ?PDO {
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $exception) {
            $this->conn = null;
        }
        return $this->conn;
    }
}
?>
<?php

require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$email = $_POST['email'] ?? '';

if (empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Email address is required.']);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address format.']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.']);
        exit();
    }

    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at, created_at, used) VALUES (:email, :token, :expires_at, NOW(), 0)");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':token', $token);
    $stmt->bindParam(':expires_at', $expires_at);
    $stmt->execute();

    $reset_link = "http://yourdomain.com/public/reset_password_form.php?token=" . urlencode($token);
    $subject = "Password Reset Request";
    $message = "Hello,\n\nYou have requested a password reset for your account. Please click the following link to reset your password:\n\n" . $reset_link . "\n\nThis link will expire in 1 hour.\n\nIf you did not request a password reset, please ignore this email.\n\n";
    $headers = 'From: noreply@yourdomain.com' . "\r\n" .
               'Reply-To: noreply@yourdomain.com' . "\r\n" .
               'X-Mailer: PHP/' . phpversion();

    mail($email, $subject, $message, $headers);

    echo json_encode(['success' => true, 'message' => 'If an account with that email exists, a password reset link has been sent.']);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
exit();
?>
<?php

require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($token) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Token and new password are required.']);
    exit();
}

if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit();
}

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit();
}

try {
    $stmt = $conn->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = :token LIMIT 1");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $reset_record = $stmt->fetch();

    if (!$reset_record) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
        exit();
    }

    $now = new DateTime();
    $expires_at = new DateTime($reset_record['expires_at']);

    if ($reset_record['used'] || $now > $expires_at) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
        exit();
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':email', $reset_record['email']);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Your password has been reset successfully.']);

} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
}
exit();
?>