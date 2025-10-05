<?php
session_start();

function isSafeRedirect(string $url): bool {
  if (empty($url)) return false;
  $parts = parse_url($url);
  if ($parts === false) return false;
  if (isset($parts['scheme']) || isset($parts['host'])) return false;
  return true;
}

$host = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'ecommerce';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$host;dbname=$dbName;charset=utf8mb4";

$pdo = null;
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
} catch (PDOException $e) {
  $pdo = null;
}

$error = '';
$csrf_token = $_SESSION['csrf_token'] ?? null;
$redirect = '/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  if (isset($_GET['redirect']) && isSafeRedirect($_GET['redirect'])) {
    $redirect = $_GET['redirect'];
  }
  if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: ' . $redirect);
    exit;
  }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['redirect']) && isSafeRedirect($_POST['redirect'])) {
    $redirect = $_POST['redirect'];
  } else {
    $redirect = '/dashboard.php';
  }

  $email = isset($_POST['email']) ? trim($_POST['email']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $csrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

  if (empty($csrf) || empty($csrf_token) || $csrf !== $csrf_token) {
    $error = 'Invalid CSRF token.';
  } elseif (!$pdo) {
    $error = 'Unable to connect to authentication service.';
  } elseif (empty($email) || empty($password)) {
    $error = 'Please enter email and password.';
  } else {
    $stmt = $pdo->prepare('SELECT id, email, name, password_hash, is_active, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && $user['is_active']) {
      if (password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['last_active'] = time();
        session_regenerate_id(true);
        header('Location: ' . $redirect);
        exit;
      } else {
        $error = 'Incorrect email or password.';
      }
    } else {
      $error = 'Incorrect email or password.';
    }
  }

  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  $csrf_token = $_SESSION['csrf_token'];
} else {
  header('HTTP/1.1 405 Method Not Allowed');
  echo 'Method Not Allowed';
  exit;
}

if (empty($csrf_token)) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  $csrf_token = $_SESSION['csrf_token'];
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Login</title></head>
<body>
<?php if (!empty($error)): ?>
<div><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>
<form method="post" action="">
  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
  <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
  <label>Email</label>
  <input type="email" name="email" required autofocus><br>
  <label>Password</label>
  <input type="password" name="password" required><br>
  <button type="submit">Login</button>
</form>
</body>
</html>
?>