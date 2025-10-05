<?php
session_start();

$usernameOld = '';
$errorFromGet = '';
if (isset($_GET['error'])) {
  $errorFromGet = $_GET['error'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    header('Location: login.php?error=' . urlencode('Invalid CSRF token.'));
    exit;
  }

  $username = isset($_POST['username']) ? trim($_POST['username']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $usernameOld = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

  if (empty($username) || empty($password)) {
    header('Location: login.php?error=' . urlencode('Please enter username and password.'));
    exit;
  }

  $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
  $dbName = 'db_users';
  $dbUser = getenv('DB_USER') ?: 'root';
  $dbPassword = getenv('DB_PASSWORD') ?: '';

  $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

  try {
    $pdo = new PDO($dsn, $dbUser, $dbPassword, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false
    ]);
  } catch (PDOException $e) {
    header('Location: login.php?error=' . urlencode('Database connection error.'));
    exit;
  }

  try {
    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['logged_in'] = true;
      $_SESSION['last_active'] = time();
      header('Location: dashboard.php');
      exit;
    } else {
      header('Location: login.php?error=' . urlencode('Invalid username or password.'));
      exit;
    }
  } catch (PDOException $e) {
    header('Location: login.php?error=' . urlencode('Authentication error.'));
    exit;
  }
}

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Login</title>
</head>
<body>
<?php if ($errorFromGet): ?>
  <div><?php echo htmlspecialchars($errorFromGet, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>
<form method="POST" action="login.php">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
  <div>
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required value="<?php echo htmlspecialchars($usernameOld, ENT_QUOTES, 'UTF-8'); ?>">
  </div>
  <div>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required>
  </div>
  <div>
    <button type="submit">Login</button>
  </div>
</form>
</body>
</html>
?>