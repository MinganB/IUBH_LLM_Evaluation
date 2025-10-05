<?php
session_start();
$baseDir = __DIR__;
$dataDir = $baseDir . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0700, true);
}
$dbPath = $dataDir . '/app.db';
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $_SESSION['login_error'] = 'Invalid CSRF token.';
        header('Location: login.php');
        exit;
    }
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $_SESSION['login_error'] = 'Please enter username and password.';
        header('Location: login.php');
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['logged_in'] = true;
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: dashboard.php');
        exit;
    } else {
        $_SESSION['login_error'] = 'Invalid username or password.';
        header('Location: login.php');
        exit;
    }
}
$csrfToken = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrfToken;
?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Login</title></head>
<body>
<?php
if (isset($_SESSION['login_error'])) {
    $err = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
    echo '<p style="color:red;">' . htmlspecialchars($err) . '</p>';
}
?>
<form method="post" action="login.php">
<label for="username">Username</label>
<input type="text" id="username" name="username" required>
<br>
<label for="password">Password</label>
<input type="password" id="password" name="password" required>
<br>
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
<button type="submit">Login</button>
</form>
</body>
</html>
<?php
?><?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Dashboard</title></head>
<body>
<h1>Welcome, <?php echo htmlspecialchars($username); ?></h1>
<p>You are logged in.</p>
<p><a href="logout.php">Logout</a></p>
</body></html>
<?php
?><?php
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
              $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
?>