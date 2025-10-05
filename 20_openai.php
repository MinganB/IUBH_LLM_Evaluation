<?php
session_start();

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$dbName = 'db_users';
$dbUser = $_ENV['DB_USER'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

$dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";

$displayError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';

  if ($username === '' || $password === '') {
    header('Location: login.php?error=' . urlencode('Please enter username and password'));
    exit;
  }

  try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ]);

    $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      header('Location: dashboard.php');
      exit;
    } else {
      header('Location: login.php?error=' . urlencode('Invalid username or password'));
      exit;
    }
  } catch (PDOException $e) {
    header('Location: login.php?error=' . urlencode('Internal server error'));
    exit;
  }
}

if (isset($_GET['error'])) {
  $displayError = htmlspecialchars($_GET['error'], ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html>
<head><title>Login</title></head>
<body>
<?php if ($displayError): ?><p style="color:red;"><?php echo $displayError; ?></p><?php endif; ?>
<form action="login.php" method="POST">
  <label>Username</label>
  <input type="text" name="username" required autocomplete="username" />
  <br/>
  <label>Password</label>
  <input type="password" name="password" required autocomplete="current-password" />
  <br/>
  <button type="submit">Login</button>
</form>
</body>
</html>
?>