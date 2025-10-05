<?php
session_start();
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1')) {
    ini_set('session.cookie_secure', 1);
}
ini_set('session.use_strict_mode', 1);
ini_set('display_errors', '0');

$DB_HOST = 'localhost';
$DB_NAME = 'ecommerce';
$DB_USER = 'dbuser';
$DB_PASS = 'dbpass';
$DB_CHARSET = 'utf8mb4';
$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";

try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
} catch (PDOException $e) {
  http_response_code(500);
  echo 'Database connection error';
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: login.php?error=' . urlencode('Invalid CSRF token'));
    exit;
  }

  $username = isset($_POST['username']) ? trim($_POST['username']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';

  if ($username === '' || $password === '') {
    header('Location: login.php?error=' . urlencode('Please enter username and password'));
    exit;
  }

  $RATE_LIMIT_SECONDS = 60;
  if (isset($_SESSION['login_last_attempt']) && (time() - $_SESSION['login_last_attempt'] < $RATE_LIMIT_SECONDS)) {
    header('Location: login.php?error=' . urlencode('Please wait a moment and try again'));
    exit;
  }

  $stmt = $pdo->prepare('SELECT id, username, password_hash, is_active FROM users WHERE username = :username LIMIT 1');
  $stmt->execute(['username' => $username]);
  $user = $stmt->fetch();

  $authenticated = false;
  if ($user && isset($user['password_hash']) && $user['is_active']) {
    $hash = $user['password_hash'];
    if (password_verify($password, $hash)) {
      $authenticated = true;
    }
  }

  if ($authenticated) {
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['authenticated'] = true;
    $_SESSION['login_time'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    session_regenerate_id(true);
    $_SESSION['login_attempts'] = 0;
    header('Location: dashboard.php');
    exit;
  } else {
    $_SESSION['login_last_attempt'] = time();
    $_SESSION['login_attempts'] = (isset($_SESSION['login_attempts']) ? $_SESSION['login_attempts'] + 1 : 1);
    header('Location: login.php?error=' . urlencode('Invalid username or password'));
    exit;
  }
}

if (!isset($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
$error = isset($_GET['error']) ? $_GET['error'] : '';
$prefillUsername = isset($_GET['username']) ? $_GET['username'] : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
</head>
<body>
  <?php if ($error !== ''): ?>
  <div role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>
  <form method="post" action="login.php" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <div>
      <label for="username">Username</label>
      <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($prefillUsername, ENT_QUOTES, 'UTF-8'); ?>" required>
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