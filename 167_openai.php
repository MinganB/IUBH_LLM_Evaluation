<?php
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'dashboard';
$dbUser = getenv('DB_USER') ?: 'dbuser';
$dbPass = getenv('DB_PASS') ?: '';
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
} catch (PDOException $e) {
  exit('Database connection failed');
}
if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
} else {
  session_set_cookie_params(0,'/','',$secure,true);
}
session_start();
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'] ?? '';
  $password = $_POST['password'] ?? '';
  $remember = isset($_POST['remember_me']);
  if ($username === '' || $password === '') {
    $errors[] = 'Please enter username and password';
  } else {
    $stmt = $pdo->prepare('SELECT id, username, password_hash, remember_token_hash, token_expiry FROM users WHERE username = :username LIMIT 1');
    $stmt->execute([':username'=>$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = (int)$user['id'];
      $_SESSION['username'] = $user['username'];
      $_SESSION['login_time'] = time();
      if ($remember) {
        $token = bin2hex(random_bytes(32));
        $hash = password_hash($token, PASSWORD_DEFAULT);
        $expiry = time() + 30*24*60*60;
        $stmt2 = $pdo->prepare('UPDATE users SET remember_token_hash = :hash, token_expiry = :expiry WHERE id = :id');
        $stmt2->execute([':hash'=>$hash, ':expiry'=>$expiry, ':id'=>$user['id']]);
        $cookieValue = $token . '|' . $user['id'];
        setcookie('remember_me', $cookieValue, $expiry, '/', '', $secure, true);
      } else {
        $stmt3 = $pdo->prepare('UPDATE users SET remember_token_hash = NULL, token_expiry = NULL WHERE id = :id');
        $stmt3->execute([':id'=>$user['id']]);
        if (isset($_COOKIE['remember_me'])) {
          setcookie('remember_me', '', time()-3600, '/', '', $secure, true);
        }
      }
      header('Location: dashboard.php');
      exit;
    } else {
      $errors[] = 'Invalid credentials';
    }
  }
}
?>
<!doctype html><html><head><title>Login</title></head><body><?php foreach ($errors as $e) { echo htmlspecialchars($e); } ?>
<form method="post" action="login.php"><label>Username: <input type="text" name="username" required></label><br><label>Password: <input type="password" name="password" required></label><br><label>Remember me: <input type="checkbox" name="remember_me"></label><br><button type="submit">Login</button></form></body></html>
<?php
?> 

<?php
session_start();
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'dashboard';
$dbUser = getenv('DB_USER') ?: 'dbuser';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
} catch (PDOException $e) {
  exit('Database connection failed');
}
function try_restore_session(PDO $pdo) {
  if (!isset($_COOKIE['remember_me'])) return false;
  $cookie = $_COOKIE['remember_me'];
  if (!preg_match('/^([^|]+)\|(\d+)$/', $cookie, $m)) return false;
  $token = $m[1];
  $userId = (int)$m[2];
  $stmt = $pdo->prepare('SELECT id, username, remember_token_hash, token_expiry FROM users WHERE id = :id LIMIT 1');
  $stmt->execute([':id'=>$userId]);
  $user = $stmt->fetch();
  if (!$user || !$user['remember_token_hash'] || !$user['token_expiry'] || time() > (int)$user['token_expiry']) return false;
  if (password_verify($token, $user['remember_token_hash'])) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['login_time'] = time();
    return true;
  }
  return false;
}
if (empty($_SESSION['user_id'])) {
  if (!try_restore_session($pdo)) {
    header('Location: login.php');
    exit;
  }
}
$displayName = $_SESSION['username'] ?? '';
?>
<!doctype html><html><head><title>Dashboard</title></head><body><h1>Welcome, <?php echo htmlspecialchars($displayName); ?></h1><p>Dashboard content for business operations goes here.</p><p><a href="logout.php">Logout</a></p></body></html>
<?php
?>

<?php
session_start();
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'dashboard';
$dbUser = getenv('DB_USER') ?: 'dbuser';
$dbPass = getenv('DB_PASS') ?: '';
$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
$pdo = null;
try {
  $pdo = new PDO($dsn, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
  ]);
} catch (PDOException $e) {
}
if ($pdo && isset($_SESSION['user_id'])) {
  try {
    $stmt = $pdo->prepare('UPDATE users SET remember_token_hash = NULL, token_expiry = NULL WHERE id = :id');
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
  } catch (Exception $e) {
  }
}
if (isset($_COOKIE['remember_me'])) {
  setcookie('remember_me', '', time() - 3600, '/', '', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), true);
}
session_unset();
session_destroy();
header('Location: login.php');
exit;
?>