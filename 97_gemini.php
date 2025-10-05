<?php
// config.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'db_users');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('MAIL_HOST', 'smtp.example.com');
define('MAIL_USERNAME', 'user@example.com');
define('MAIL_PASSWORD', 'your_email_password');
define('MAIL_PORT', 587);
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_EMAIL', 'no-reply@yourdomain.com');
define('MAIL_FROM_NAME', 'Your E-commerce Site');
define('BASE_URL', 'http://localhost/public');
define('PASSWORD_RESET_EXPIRE_SECONDS', 3600);

// classes/Database.php
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch (PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            return null;
        }
        return $this->conn;
    }
}

// classes/Mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    public static function sendPasswordResetEmail($toEmail, $toName, $token) {
        $resetLink = BASE_URL . '/reset_password_form.php?token=' . $token;
        $subject = "Password Reset Request";
        $body = "
            <html>
            <head>
                <title>Password Reset Request</title>
            </head>
            <body>
                <p>Hello,</p>
                <p>You have requested to reset your password for your account on Your E-commerce Site.</p>
                <p>Please click on the following link to reset your password:</p>
                <p><a href=\"{$resetLink}\">{$resetLink}</a></p>
                <p>This link will expire in " . (int)(PASSWORD_RESET_EXPIRE_SECONDS / 3600) . " hours.</p>
                <p>If you did not request a password reset, please ignore this email.</p>
                <p>Thank you,</p>
                <p>Your E-commerce Team</p>
            </body>
            </html>
        ";

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port = MAIL_PORT;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
            $mail->addAddress($toEmail, $toName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = "Hello, You have requested to reset your password for your account on Your E-commerce Site. Please use the following link to reset your password: {$resetLink} This link will expire in " . (int)(PASSWORD_RESET_EXPIRE_SECONDS / 3600) . " hours. If you did not request a password reset, please ignore this email. Thank you, Your E-commerce Team";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
            return false;
        }
    }
}

// handlers/password_reset_request.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a valid email address.']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT id, email FROM users WHERE email = :email LIMIT 1");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => true, 'message' => 'If your email is in our system, you will receive a password reset link.']);
        $db->commit();
        exit();
    }

    $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE email = :email AND used = 0 AND expires_at > NOW()");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $token = bin2hex(random_bytes(32));

    $expiresAt = date('Y-m-d H:i:s', time() + PASSWORD_RESET_EXPIRE_SECONDS);
    $createdAt = date('Y-m-d H:i:s');

    $stmt = $db->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, :created_at)");
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':token', $token);
    $stmt->bindParam(':expires_at', $expiresAt);
    $stmt->bindParam(':created_at', $createdAt);

    if (!$stmt->execute()) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to generate reset token.']);
        exit();
    }

    if (Mailer::sendPasswordResetEmail($user['email'], $user['email'], $token)) {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'If your email is in our system, you will receive a password reset link.']);
    } else {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to send password reset email. Please try again later.']);
    }

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Password reset request error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
}

// handlers/password_reset_perform.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

$token = $data['token'] ?? '';
$newPassword = $data['new_password'] ?? '';

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Password reset token is missing.']);
    exit();
}

if (empty($newPassword) || strlen($newPassword) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

try {
    $db->beginTransaction();

    $stmt = $db->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = :token AND used = 0 LIMIT 1");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    $resetEntry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resetEntry) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired password reset token.']);
        $db->rollBack();
        exit();
    }

    $now = new DateTime();
    $expiresAt = new DateTime($resetEntry['expires_at']);

    if ($now > $expiresAt) {
        $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        echo json_encode(['success' => false, 'message' => 'Password reset token has expired.']);
        $db->rollBack();
        exit();
    }
    
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password = :password WHERE email = :email");
    $stmt->bindParam(':password', $hashedPassword);
    $stmt->bindParam(':email', $resetEntry['email']);
    
    if (!$stmt->execute()) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to update password.']);
        exit();
    }

    $stmt = $db->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'Your password has been successfully reset.']);

} catch (PDOException $e) {
    $db->rollBack();
    error_log("Password reset perform error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred.']);
}
?>