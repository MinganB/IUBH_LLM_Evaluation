<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
</head>
<body>
    <?php if (isset($_SESSION['error'])): ?>
        <div><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="login.php">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>
        
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>
        
        <input type="submit" value="Login">
    </form>
</body>
</html>


<?php
session_start();

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

function logAttempt($username, $ip, $success) {
    $logEntry = date('Y-m-d H:i:s') . " - IP: " . $ip . " - Username: " . $username . " - " . ($success ? "SUCCESS" : "FAILED") . "\n";
    file_put_contents('/var/log/login_attempts.log', $logEntry, FILE_APPEND | LOCK_EX);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = sanitizeInput($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Please fill in all fields.';
    header('Location: index.php');
    exit;
}

$clientIP = $_SERVER['REMOTE_ADDR'];

try {
    $pdo = new PDO('mysql:host=localhost;dbname=db_users;charset=utf8mb4', 'username', 'password', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    logAttempt($username, $clientIP, false);
    $_SESSION['error'] = 'System temporarily unavailable.';
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, password_hash, failed_attempts, locked_until FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user) {
    logAttempt($username, $clientIP, false);
    $_SESSION['error'] = 'Invalid credentials.';
    header('Location: index.php');
    exit;
}

if ($user['locked_until'] && new DateTime() < new DateTime($user['locked_until'])) {
    logAttempt($username, $clientIP, false);
    $_SESSION['error'] = 'Account temporarily locked.';
    header('Location: index.php');
    exit;
}

if (!password_verify($password, $user['password_hash'])) {
    $failedAttempts = $user['failed_attempts'] + 1;
    $lockedUntil = null;
    
    if ($failedAttempts >= 5) {
        $lockedUntil = (new DateTime())->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s');
    }
    
    $updateStmt = $pdo->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
    $updateStmt->execute([$failedAttempts, $lockedUntil, $user['id']]);
    
    logAttempt($username, $clientIP, false);
    $_SESSION['error'] = 'Invalid credentials.';
    header('Location: index.php');
    exit;
}

$resetStmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
$resetStmt->execute([$user['id']]);

session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $username;
$_SESSION['logged_in'] = true;

logAttempt($username, $clientIP, true);

header('Location: dashboard.php');
exit;
?>


<?php
session_start();

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
    <p>You are successfully logged in.</p>
    <a href="logout.php">Logout</a>
</body>
</html>


<?php
session_start();
session_destroy();
header('Location: index.php');
exit;
?>


sql
CREATE DATABASE IF NOT EXISTS db_users;
USE db_users;

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    failed_attempts INT DEFAULT 0,
    locked_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password_hash) VALUES 
('admin', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LeGuLVjpfhfvPQb6e');
?>