<?php
// /public/request_reset.php

require_once '../handlers/PasswordResetHandler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    $handler = new PasswordResetHandler();
    $response = $handler->requestReset($email);
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <form method="POST" action="request_reset.php">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required>
        <button type="submit">Send Reset Link</button>
    </form>
</body>
</html>


<?php
// /public/reset_password.php

require_once '../handlers/PasswordResetHandler.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';
    
    $handler = new PasswordResetHandler();
    $response = $handler->resetPassword($token, $password, $confirmPassword);
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

$token = $_GET['token'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <form method="POST" action="reset_password.php">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        
        <label for="password">New Password:</label>
        <input type="password" id="password" name="password" required>
        
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        
        <button type="submit">Reset Password</button>
    </form>
</body>
</html>


<?php
// /handlers/PasswordResetHandler.php

require_once '../classes/Database.php';
require_once '../classes/EmailService.php';

class PasswordResetHandler
{
    private $db;
    private $emailService;
    
    public function __construct()
    {
        $this->db = new Database();
        $this->emailService = new EmailService();
    }
    
    public function requestReset($email)
    {
        if (empty($email)) {
            return ['success' => false, 'message' => 'Email is required'];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        try {
            $pdo = $this->db->getConnection();
            
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Email not found'];
            }
            
            $token = bin2hex(random_bytes(16));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt->execute([$email, $token, $expiresAt]);
            
            $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
            
            $emailSent = $this->emailService->sendPasswordResetEmail($email, $resetLink);
            
            if ($emailSent) {
                return ['success' => true, 'message' => 'Password reset link sent to your email'];
            } else {
                return ['success' => false, 'message' => 'Failed to send reset email'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'An error occurred. Please try again'];
        }
    }
    
    public function resetPassword($token, $password, $confirmPassword)
    {
        if (empty($token) || empty($password) || empty($confirmPassword)) {
            return ['success' => false, 'message' => 'All fields are required'];
        }
        
        if ($password !== $confirmPassword) {
            return ['success' => false, 'message' => 'Passwords do not match'];
        }
        
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters long'];
        }
        
        try {
            $pdo = $this->db->getConnection();
            
            $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
            $stmt->execute([$token]);
            $resetRecord = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$resetRecord) {
                return ['success' => false, 'message' => 'Invalid or expired token'];
            }
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashedPassword, $resetRecord['email']]);
            
            $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            
            $pdo->commit();
            
            return ['success' => true, 'message' => 'Password reset successfully'];
            
        } catch (Exception $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            return ['success' => false, 'message' => 'An error occurred. Please try again'];
        }
    }
}


<?php
// /classes/Database.php

class Database
{
    private $host = 'localhost';
    private $dbname = 'db_users';
    private $username = 'root';
    private $password = '';
    private $pdo;
    
    public function __construct()
    {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname}",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    public function getConnection()
    {
        return $this->pdo;
    }
}


<?php
// /classes/EmailService.php

class EmailService
{
    public function sendPasswordResetEmail($email, $resetLink)
    {
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $resetLink;
        $headers = "From: noreply@yourdomain.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        return mail($email, $subject, $message, $headers);
    }
}
?>