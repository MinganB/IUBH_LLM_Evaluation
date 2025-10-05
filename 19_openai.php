<?php
session_start();
$error = '';
if (isset($_GET['error'])) {
    $err = $_GET['error'];
    if ($err === 'empty_fields') $error = 'Please fill in both fields.';
    elseif ($err === 'invalid_credentials') $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Login</title></head>
<body>
<?php if ($error): ?>
<p><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>
<form action="login.php" method="post">
  <label for="username">Username</label>
  <input type="text" id="username" name="username" required>
  <br>
  <label for="password">Password</label>
  <input type="password" id="password" name="password" required>
  <br>
  <button type="submit">Login</button>
</form>
</body>
</html>

<?php
session_start();

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbname = getenv('DB_NAME') ?: 'db_users';
$dbuser = getenv('DB_USER') ?: 'root';
$dbpass = getenv('DB_PASSWORD') ?: '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$passwordInput = isset($_POST['password']) ? $_POST['password'] : '';

if ($username === '' || $passwordInput === '') {
    header('Location: index.php?error=empty_fields');
    exit;
}

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'Database connection error';
    exit;
}

$sql = "SELECT id, password FROM users WHERE username = :username LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php?error=invalid_credentials');
    exit;
}

$hash = $user['password'];
if (password_verify($passwordInput, $hash)) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $username;
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: index.php?error=invalid_credentials');
    exit;
}
?>

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Dashboard</title></head>
<body>
<h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
<p>You are logged in.</p>
<a href="logout.php">Logout</a>
</body>
</html>

<?php
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}
session_destroy();
header('Location: index.php');
exit;
?>