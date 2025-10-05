<?php
$pdo = null;
try {
  $host = getenv('DB_HOST') ?: '127.0.0.1';
  $dbName = 'db_users';
  $user = getenv('DB_USER') ?: 'root';
  $pass = getenv('DB_PASS') ?: '';
  $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Database connection error']);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success'=>false,'message'=>'Invalid email address']);
    exit;
  }
  $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
  $stmt->execute(['email'=>$email]);
  $user = $stmt->fetch();
  if (!$user) {
    echo json_encode(['success'=>true,'message'=>'If the email exists, a reset link has been sent.']);
    exit;
  }
  $token = bin2hex(random_bytes(16));
  $expires_at = date('Y-m-d H:i:s', time() + 3600);
  $created_at = date('Y-m-d H:i:s');
  $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, used, created_at) VALUES (:email, :token, :expires_at, 0, :created_at)");
  $stmt->execute(['email'=>$email, 'token'=>$token, 'expires_at'=>$expires_at, 'created_at'=>$created_at]);
  $resetLink = "https://yourdomain.com/public/reset_password.php?token=$token";
  $subject = "Password Reset Request";
  $message = "A password reset has been requested for your account. To reset your password, visit: $resetLink";
  $headers = "From: no-reply@yourdomain.com\r\n";
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
  mail($email, $subject, $message, $headers);
  echo json_encode(['success'=>true,'message'=>'If the email exists, a reset link has been sent.']);
  exit;
}
?>
<!DOCTYPE html>
<html><head><title>Request Password Reset</title></head>
<body>
<h2>Request Password Reset</h2>
<form method="POST" action="request_reset.php">
  <input type="email" name="email" required placeholder="Email address" />
  <button type="submit">Send Reset Link</button>
</form>
</body></html>
<?php
?>

<?php
$pdo = null;
try {
  $host = getenv('DB_HOST') ?: '127.0.0.1';
  $dbName = 'db_users';
  $user = getenv('DB_USER') ?: 'root';
  $pass = getenv('DB_PASS') ?: '';
  $dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>'Database connection error']);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token = isset($_POST['token']) ? $_POST['token'] : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
  if (empty($token)) { echo json_encode(['success'=>false,'message'=>'Token is required']); exit; }
  if (empty($password) || strlen($password) < 8) { echo json_encode(['success'=>false,'message'=>'Password must be at least 8 characters']); exit; }
  if ($password !== $confirm) { echo json_encode(['success'=>false,'message'=>'Passwords do not match']); exit; }
  $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_resets WHERE token = :token");
  $stmt->execute(['token'=>$token]);
  $row = $stmt->fetch();
  if (!$row) { echo json_encode(['success'=>false,'message'=>'Invalid or expired token']); exit; }
  if ($row['used'] == 1) { echo json_encode(['success'=>false,'message'=>'Token has already been used']); exit; }
  if (strtotime($row['expires_at']) < time()) { echo json_encode(['success'=>false,'message'=>'Token has expired']); exit; }
  $email = $row['email'];
  $hashed = password_hash($password, PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
  $stmt->execute(['password'=>$hashed, 'email'=>$email]);
  $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = :token");
  $stmt->execute(['token'=>$token]);
  echo json_encode(['success'=>true,'message'=>'Password has been reset successfully']);
  exit;
}
$tokenFromGet = isset($_GET['token']) ? $_GET['token'] : '';
?>
<!DOCTYPE html><html><head><title>Reset Password</title></head><body>
<h2>Reset Password</h2>
<form method="POST" action="reset_password.php">
  <label>New Password</label><br/>
  <input type="password" name="password" required placeholder="New password" /><br/>
  <label>Confirm Password</label><br/>
  <input type="password" name="confirm_password" required placeholder="Confirm password" /><br/>
  <label>Token</label><br/>
  <input type="text" name="token" value="<?php echo htmlspecialchars($tokenFromGet); ?>" required /><br/>
  <button type="submit">Reset Password</button>
</form>
</body></html>
?>