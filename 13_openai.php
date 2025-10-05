<?php
function getDB() {
static $pdo = null;
$dir = __DIR__ . '/data';
if (!is_dir($dir)) {
mkdir($dir, 0755, true);
}
$dbPath = $dir . '/users.db';
$firstTime = !file_exists($dbPath);
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if ($firstTime) {
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
id INTEGER PRIMARY KEY AUTOINCREMENT,
username TEXT UNIQUE NOT NULL,
password_hash TEXT NOT NULL,
created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
$hash = password_hash('password123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
$stmt->execute(['demo', $hash]);
}
return $pdo;
}
?><?php
// login.php
session_start();
require_once 'db.php';
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
if ($username === '' || $password === '') {
$_SESSION['login_error'] = 'Please enter username and password.';
header('Location: login.php');
exit;
}
$db = getDB();
$stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = :username');
$stmt->execute([':username' => $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if ($user && password_verify($password, $user['password_hash'])) {
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
header('Location: dashboard.php');
exit;
} else {
$_SESSION['login_error'] = 'Invalid username or password.';
header('Location: login.php');
exit;
}
}
if (isset($_SESSION['login_error'])) {
$loginError = $_SESSION['login_error'];
unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<?php if ($loginError !== '') { echo '<div>' . htmlspecialchars($loginError) . '</div>'; } ?>
<form action="login.php" method="POST">
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button type="submit">Login</button>
</form>
</body>
</html><?php
// dashboard.php
session_start();
if (!isset($_SESSION['user_id'])) {
header('Location: login.php');
exit;
}
?>
<!DOCTYPE html>
<html>
<head><title>Dashboard</title></head>
<body>
<h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
<p>You are logged in. You can view products and manage orders.</p>
<a href="logout.php">Logout</a>
</body>
</html><?php
// logout.php
session_start();
session_destroy();
header('Location: login.php');
exit;
?>