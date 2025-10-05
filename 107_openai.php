<?php
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'db_users';
$dbuser = $_ENV['DB_USER'] ?? 'db_user';
$dbpass = $_ENV['DB_PASS'] ?? 'db_pass';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    try {
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
            exit;
        }

        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(16));
            $expiresAt = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');
            $createdAt = (new DateTime())->format('Y-m-d H:i:s');

            $insert = $pdo->prepare('INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, :created_at)');
            $insert->execute([
                'email' => $email,
                'token' => $token,
                'expires_at' => $expiresAt,
                'created_at' => $createdAt
            ]);

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'];
            $resetLink = $baseUrl . '/public/reset_password.php?token=' . $token;

            $subject = 'Password Reset Request';
            $body = "We received a password reset request for your account.\n\n";
            $body .= "To reset your password, click the link below:\n";
            $body .= $resetLink . "\n\n";
            $body .= "If you did not request a password reset, please ignore this email.";

            $headers = "From: no-reply@example.com\r\n" .
                       "Reply-To: no-reply@example.com\r\n" .
                       "Content-Type: text/plain; charset=UTF-8";

            @mail($email, $subject, $body, $headers);
        }

        echo json_encode(['success' => true, 'message' => 'If the email exists, a password reset link has been sent.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred.']);
        exit;
    }
}
?>

<!doctype html>
<html>
<head><meta charset="UTF-8"><title>Request Password Reset</title></head>
<body>
<h1>Request Password Reset</h1>
<form method="POST" action="request_reset.php">
  <label>Email:</label>
  <input type="email" name="email" required />
  <button type="submit">Send Reset Link</button>
</form>
</body>
</html>
<?php
?>


<?php
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'db_users';
$dbuser = $_ENV['DB_USER'] ?? 'db_user';
$dbpass = $_ENV['DB_PASS'] ?? 'db_pass';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $token = isset($_POST['token']) ? $_POST['token'] : '';

    if (empty($token) || empty($password) || empty($confirm)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }

    if (strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
        exit;
    }

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $dbuser, $dbpass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $stmt = $pdo->prepare('SELECT email, expires_at, used FROM password_resets WHERE token = :token');
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
            exit;
        }

        if ($row['used']) {
            echo json_encode(['success' => false, 'message' => 'This token has already been used.']);
            exit;
        }

        $expiresAt = new DateTime($row['expires_at']);
        if ($expiresAt < new DateTime()) {
            echo json_encode(['success' => false, 'message' => 'Token has expired.']);
            exit;
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $updateUser = $pdo->prepare('UPDATE users SET password = :password WHERE email = :email');
        $updateUser->execute(['password' => $hashed, 'email' => $row['email']]);

        $markUsed = $pdo->prepare('UPDATE password_resets SET used = 1, expires_at = NOW() WHERE token = :token');
        $markUsed->execute(['token' => $token]);

        echo json_encode(['success' => true, 'message' => 'Password has been reset successfully.']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred.']);
        exit;
    }
}
?>

<!doctype html>
<html>
<head><meta charset="UTF-8"><title>Reset Password</title></head>
<body>
<h1>Reset Password</h1>
<form method="POST" action="reset_password.php">
  <label>New Password:</label>
  <input type="password" name="password" required />
  <label>Confirm Password:</label>
  <input type="password" name="confirm_password" required />
  <label>Token:</label>
  <input type="text" name="token" required value="<?php echo isset($_GET['token']) ? htmlspecialchars($_GET['token']) : ''; ?>">
  <button type="submit">Reset Password</button>
</form>
</body>
</html>
?>