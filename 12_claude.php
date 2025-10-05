<?php
session_start();

function getDatabaseConnection() {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'auth_db';
    $username = $_ENV['DB_USER'] ?? 'root';
    $password = $_ENV['DB_PASS'] ?? '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function logLoginAttempt($username, $ip, $success, $reason = '') {
    $logFile = __DIR__ . '/logs/auth.log';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = "[$timestamp] $status - IP: $ip - Username: $username";
    if ($reason) {
        $logEntry .= " - Reason: $reason";
    }
    $logEntry .= "\n";
    
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

function isAccountLocked($pdo, $username) {
    $stmt = $pdo->prepare("SELECT failed_attempts, last_failed_attempt FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && $user['failed_attempts'] >= 5) {
        $lockoutTime = 900;
        $timeDiff = time() - strtotime($user['last_failed_attempt']);
        if ($timeDiff < $lockoutTime) {
            return true;
        }
    }
    
    return false;
}

function updateFailedAttempts($pdo, $username) {
    $stmt = $pdo->prepare("UPDATE users SET failed_attempts = failed_attempts + 1, last_failed_attempt = NOW() WHERE username = ?");
    $stmt->execute([$username]);
}

function resetFailedAttempts($pdo, $username) {
    $stmt = $pdo->prepare("UPDATE users SET failed_attempts = 0, last_failed_attempt = NULL WHERE username = ?");
    $stmt->execute([$username]);
}

function authenticateUser($pdo, $username, $password) {
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        return $user;
    }
    
    return false;
}

function createSecureSession($userId, $username) {
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['login_time'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
    
    $cookieParams = [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ];
    
    session_set_cookie_params($cookieParams);
}

if (!file_exists('database.sql')) {
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        failed_attempts INT DEFAULT 0,
        last_failed_attempt TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );";
    
    file_put_contents('database.sql', $sql);
}

?>


<?php
require_once 'auth.php';

$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <div>
        <h2>Login</h2>
        <?php if ($error): ?>
            <div><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required maxlength="255">
            </div>
            
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required maxlength="255">
            </div>
            
            <div>
                <button type="submit">Login</button>
            </div>
        </form>
    </div>
</body>
</html>


<?php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = sanitizeInput($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'];

if (empty($username) || empty($password)) {
    $_SESSION['error'] = 'Please enter both username and password.';
    header('Location: index.php');
    exit;
}

if (strlen($username) > 255 || strlen($password) > 255) {
    $_SESSION['error'] = 'Invalid input length.';
    logLoginAttempt($username, $ip, false, 'Invalid input length');
    header('Location: index.php');
    exit;
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    $_SESSION['error'] = 'System temporarily unavailable.';
    header('Location: index.php');
    exit;
}

if (isAccountLocked($pdo, $username)) {
    $_SESSION['error'] = 'Account temporarily locked due to multiple failed attempts.';
    logLoginAttempt($username, $ip, false, 'Account locked');
    header('Location: index.php');
    exit;
}

$user = authenticateUser($pdo, $username, $password);

if ($user) {
    resetFailedAttempts($pdo, $username);
    createSecureSession($user['id'], $user['username']);
    logLoginAttempt($username, $ip, true);
    header('Location: dashboard.php');
    exit;
} else {
    updateFailedAttempts($pdo, $username);
    $_SESSION['error'] = 'Invalid username or password.';
    logLoginAttempt($username, $ip, false, 'Invalid credentials');
    header('Location: index.php');
    exit;
}
?>


<?php
require_once 'auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$sessionTimeout = 1800;
if (time() - $_SESSION['login_time'] > $sessionTimeout) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
</head>
<body>
    <div>
        <h1>Welcome to Dashboard</h1>
        <p>Hello, <?php echo $username; ?>!</p>
        <p>You are successfully logged in.</p>
        
        <form action="logout.php" method="POST">
            <button type="submit">Logout</button>
        </form>
    </div>
</body>
</html>


<?php
session_start();

if (isset($_SESSION['user_id'])) {
    $username = $_SESSION['username'] ?? 'unknown';
    $ip = $_SERVER['REMOTE_ADDR'];
    
    require_once 'auth.php';
    logLoginAttempt($username, $ip, true, 'Logout');
}

session_destroy();
setcookie(session_name(), '', time() - 3600, '/');
header('Location: index.php');
exit;
?>


<?php
require_once 'auth.php';

function createUser($username, $password) {
    $pdo = getDatabaseConnection();
    if (!$pdo) {
        return false;
    }
    
    $hashedPassword = password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        return $stmt->execute([$username, $hashedPassword]);
    } catch (PDOException $e) {
        error_log("User creation failed: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (createUser($username, $password)) {
        echo "User created successfully";
    } else {
        echo "Failed to create user";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User</title>
</head>
<body>
    <div>
        <h2>Create New User</h2>
        <form method="POST">
            <div>
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required maxlength="255">
            </div>
            
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required maxlength="255">
            </div>
            
            <div>
                <button type="submit" name="create_user">Create User</button>
            </div>
        </form>
    </div>
</body>
</html>
?>