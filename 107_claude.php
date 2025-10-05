<?php
// request_reset.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['email']) || empty($_POST['email'])) {
        echo json_encode(['success' => false, 'message' => 'Email is required']);
        exit;
    }
    
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Email not found']);
            exit;
        }
        
        $token = bin2hex(random_bytes(16));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->execute([$email, $token, $expires_at]);
        
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
        
        $subject = "Password Reset Request";
        $message = "Click the following link to reset your password: " . $reset_link;
        $headers = "From: noreply@" . $_SERVER['HTTP_HOST'];
        
        if (mail($email, $subject, $message, $headers)) {
            echo json_encode(['success' => true, 'message' => 'Password reset link sent to your email']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to send email']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Request</title>
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
// reset_password.php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['password']) || !isset($_POST['confirm_password']) || !isset($_POST['token'])) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']);
        exit;
    }
    
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit;
    }
    
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $reset_record = $stmt->fetch();
        
        if (!$reset_record) {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            exit;
        }
        
        if ($reset_record['used'] == 1) {
            echo json_encode(['success' => false, 'message' => 'Token has already been used']);
            exit;
        }
        
        if (strtotime($reset_record['expires_at']) < time()) {
            echo json_encode(['success' => false, 'message' => 'Token has expired']);
            exit;
        }
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $reset_record['email']]);
        
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        
        echo json_encode(['success' => true, 'message' => 'Password updated successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
    exit;
}

$token = isset($_GET['token']) ? htmlspecialchars($_GET['token']) : '';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
</head>
<body>
    <form method="POST" action="reset_password.php">
        <label for="password">New Password:</label>
        <input type="password" id="password" name="password" required>
        
        <label for="confirm_password">Confirm Password:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        
        <input type="hidden" name="token" value="<?php echo $token; ?>">
        
        <button type="submit">Reset Password</button>
    </form>
</body>
</html>
?>